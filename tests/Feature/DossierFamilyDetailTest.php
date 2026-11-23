<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Enums\AssureStatut;
use App\Models\AdminUser;
use App\Models\Assure;
use App\Models\Dossier;
use App\Support\AdminPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Page Dossiers : la liste ne porte que le résumé de chaque famille (elle pesait plusieurs Mo pour un
 * gestionnaire) ; le détail complet se charge à l'ouverture, limité aux dossiers visibles par l'agent.
 */
class DossierFamilyDetailTest extends TestCase
{
    use RefreshDatabase;

    private function gestionnaire(string $nom): AdminUser
    {
        return AdminUser::query()->create([
            'nom' => $nom, 'prenom' => 'Test', 'email' => strtolower($nom).'@cama.bf', 'password' => 'motdepasse-solide',
            'role' => AdminRole::Gestionnaire, 'permissions' => AdminPermissions::presetForRole(AdminRole::Gestionnaire), 'actif' => true,
        ]);
    }

    private function family(string $gestionnaire): Assure
    {
        $assure = Assure::query()->create([
            'nom' => 'TRAORE', 'prenom' => 'Issa', 'matricule' => 'M-1', 'numero_cim' => 'C-1', 'numero_cama' => 'K-1',
            'email' => 'issa@example.bf', 'password' => 'mot-de-passe-solide', 'statut' => AssureStatut::Actif, 'journal' => [],
        ]);
        Dossier::query()->create([
            'assure_id' => $assure->id, 'ref' => 'REF-F1', 'nom' => 'TRAORE', 'prenom' => 'Awa', 'lien' => 'Conjoint(e)',
            'statut' => 'Soumis', 'gestionnaire' => $gestionnaire, 'journal' => [['date' => '01/11/2026 10:00', 'libelle' => 'Soumis']],
            'pieces' => [['type' => 'Acte de mariage', 'statut' => 'Soumise', 'path' => 'dossiers/secret/acte.pdf', 'filename' => 'acte.pdf']],
        ]);

        return $assure;
    }

    public function test_the_list_only_carries_the_family_summary(): void
    {
        $gest = $this->gestionnaire('Kabore');
        $this->family($gest->display_name);

        $this->actingAs($gest, 'admin')->get(route('admin.dossiers'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('families.0.detailLoaded', false)
                ->where('families.0.dossiers.0.statut', 'Soumis')
                ->where('families.0.dossiers.0.lien', 'Conjoint(e)')
                ->missing('families.0.assureProfile')
                ->missing('families.0.exportProfile')
                ->missing('families.0.membresExport')
                ->missing('families.0.dossiers.0.pieces')
                ->missing('families.0.dossiers.0.journal'));
    }

    public function test_the_detail_is_complete_and_never_exposes_the_internal_file_path(): void
    {
        $gest = $this->gestionnaire('Kabore');
        $assure = $this->family($gest->display_name);

        $response = $this->actingAs($gest, 'admin')->getJson(route('admin.dossiers.family', $assure))
            ->assertOk()
            ->assertJsonPath('family.detailLoaded', true)
            ->assertJsonPath('family.assureUuid', $assure->uuid)
            ->assertJsonPath('family.assureProfile.matricule', 'M-1')
            ->assertJsonPath('family.membresExport.0.prenom', 'Awa')
            ->assertJsonPath('family.dossiers.0.pieces.0.hasFile', true)
            ->assertJsonPath('family.dossiers.0.journal.0.libelle', 'Soumis');

        $this->assertStringNotContainsString('dossiers/secret/acte.pdf', $response->getContent());
    }

    public function test_an_agent_cannot_read_a_family_he_does_not_see(): void
    {
        $proprietaire = $this->gestionnaire('Kabore');
        $autre = $this->gestionnaire('Ouedraogo');
        $assure = $this->family($proprietaire->display_name);

        $this->actingAs($autre, 'admin')->getJson(route('admin.dossiers.family', $assure))->assertNotFound();
        $this->actingAs($autre, 'admin')->getJson('/admin/dossiers/familles/'.$assure->id)->assertNotFound();
    }
}
