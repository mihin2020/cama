<?php

namespace Tests\Feature;

use App\Enums\AssureStatut;
use App\Models\AdminUser;
use App\Models\Assure;
use App\Models\Dossier;
use App\Models\PlatformSetting;
use App\Services\AdminDossierService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Validation à deux niveaux : seule la validation d'un GESTIONNAIRE passe ensuite par un superviseur.
 * Quand un superviseur valide (y compris un dossier qui lui est affecté), c'est définitif.
 */
class DossierTwoLevelValidationTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    private function account(string $nom, string $role): AdminUser
    {
        return AdminUser::query()->create([
            'nom' => $nom, 'prenom' => 'Test', 'email' => strtolower($nom).'@cama.bf', 'password' => 'motdepasse-solide',
            'role' => $role, 'actif' => true,
        ])->fresh();
    }

    private function dossier(?string $gestionnaire): Dossier
    {
        $this->seq++;
        $assure = Assure::query()->create([
            'nom' => 'Assure', 'prenom' => "N{$this->seq}", 'matricule' => "MAT-{$this->seq}", 'numero_cim' => 'C', 'numero_cama' => "K{$this->seq}",
            'email' => "a{$this->seq}@example.bf", 'password' => 'password', 'statut' => AssureStatut::Actif, 'journal' => [],
        ]);

        return Dossier::query()->create([
            'assure_id' => $assure->id, 'ref' => "REF-{$this->seq}", 'nom' => 'Membre', 'prenom' => 'Test', 'lien' => 'Enfant',
            'statut' => 'Soumis', 'gestionnaire' => $gestionnaire, 'journal' => [],
        ]);
    }

    private function twoLevels(bool $on): void
    {
        PlatformSetting::query()->updateOrCreate(['key' => 'validation_2niveaux'], ['value' => $on]);
    }

    private function service(): AdminDossierService
    {
        return app(AdminDossierService::class);
    }

    public function test_a_dossier_assigned_to_a_supervisor_is_validated_directly_by_him(): void
    {
        $this->twoLevels(true);
        $a = $this->account('Alpha', 'superviseur');
        $dossier = $this->dossier($a->display_name);

        // Aucun second contrôle : sa validation est définitive, même avec la double validation activée.
        $this->service()->validate($dossier, $a);

        $this->assertSame('Validé', $dossier->fresh()->statut);
    }

    public function test_a_gestionnaires_dossier_still_goes_through_a_supervisor(): void
    {
        $this->twoLevels(true);
        $gestionnaire = $this->account('Gest', 'gestionnaire');
        $superviseur = $this->account('Chef', 'superviseur');
        $dossier = $this->dossier($gestionnaire->display_name);

        $this->service()->validate($dossier, $gestionnaire);
        $this->assertSame('En attente supervision', $dossier->fresh()->statut);

        $this->service()->validate($dossier->fresh(), $superviseur);
        $this->assertSame('Validé', $dossier->fresh()->statut);
    }

    public function test_a_supervisor_validating_someone_elses_dossier_is_unchanged(): void
    {
        $this->twoLevels(true);
        $superviseur = $this->account('Chef', 'superviseur');
        $dossier = $this->dossier('Lt. Autre Gestionnaire');

        $this->service()->validate($dossier, $superviseur);

        $this->assertSame('Validé', $dossier->fresh()->statut);
    }

    public function test_without_two_levels_a_supervisor_validates_his_own_dossier_directly(): void
    {
        $this->twoLevels(false);
        $a = $this->account('Alpha', 'superviseur');
        $dossier = $this->dossier($a->display_name);

        $this->service()->validate($dossier, $a);

        $this->assertSame('Validé', $dossier->fresh()->statut);
    }
}
