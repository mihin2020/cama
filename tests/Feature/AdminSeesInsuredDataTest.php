<?php

namespace Tests\Feature;

use App\Enums\AssureStatut;
use App\Models\AdminUser;
use App\Models\Assure;
use App\Models\Dossier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/** Tout ce que l'assuré a saisi est visible de l'administration (selon ses permissions). */
class AdminSeesInsuredDataTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): AdminUser
    {
        return AdminUser::query()->create([
            'nom' => 'Admin', 'prenom' => 'Test', 'email' => 'admin@cama.bf', 'password' => 'motdepasse-solide',
            'role' => 'administrateur', 'actif' => true,
        ]);
    }

    private function assureWithMember(): Assure
    {
        $assure = Assure::query()->create([
            'nom' => 'TRAORE', 'prenom' => 'Issouf', 'sexe' => 'Masculin', 'matricule' => 'M-9', 'numero_informatique' => 'INF-1', 'grade' => 'Sergent',
            'categorie' => 'Sous-officier', 'numero_cim' => 'CIM-1', 'numero_cama' => 'CAMA-1', 'numero_iup' => 'IUP-1', 'armee' => 'Terre',
            'region' => '1ère', 'corps' => 'RI', 'service' => 'Transmissions', 'section' => 'S1', 'sous_section' => 'SS1', 'telephone' => '70000000',
            'email' => 'issouf@example.bf', 'personne_a_prevenir' => 'Aminata', 'tel_personne_a_prevenir' => '71111111',
            'password' => 'mot-de-passe-solide', 'statut' => AssureStatut::Actif, 'journal' => [],
        ]);

        Dossier::query()->create([
            'assure_id' => $assure->id, 'ref' => 'REF-8', 'nom' => 'Aïcha', 'prenom' => 'Traoré', 'lien' => 'Conjoint(e)', 'statut' => 'Soumis',
            'journal' => [], 'wizard_meta' => ['nationalite' => 'Burkinabè', 'profession' => 'Enseignante', 'lieu_residence' => 'Ouagadougou'],
        ]);

        return $assure;
    }

    public function test_the_assures_screen_sends_every_registration_field_and_the_member_details(): void
    {
        $this->assureWithMember();

        $this->actingAs($this->admin(), 'admin')->get(route('admin.assures'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('assures.0.exportProfile.grade', 'Sergent')
                ->where('assures.0.exportProfile.categorie', 'Sous-officier')
                ->where('assures.0.exportProfile.numeroInformatique', 'INF-1')
                ->where('assures.0.exportProfile.numeroIup', 'IUP-1')
                ->where('assures.0.exportProfile.armee', 'Terre')
                ->where('assures.0.exportProfile.sousSection', 'SS1')
                ->where('assures.0.exportProfile.personneAPrevenir', 'Aminata')
                ->where('assures.0.exportProfile.telPersonneAPrevenir', '71111111')
                ->where('assures.0.membresExport.0.nationalite', 'Burkinabè')
                ->where('assures.0.membresExport.0.profession', 'Enseignante')
                ->where('assures.0.membresExport.0.lieuResidence', 'Ouagadougou'));
    }

    public function test_the_registrations_screen_shows_the_fif_number(): void
    {
        $assure = $this->assureWithMember();
        $assure->update(['statut' => AssureStatut::EnAttenteValidation]);
        $numero = app(\App\Services\FifProfileService::class)->numero($assure);
        $this->assertNotEmpty($numero);

        $this->actingAs($this->admin(), 'admin')->get(route('admin.inscriptions'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('registrations.0.numeroFif', $numero));
    }
}
