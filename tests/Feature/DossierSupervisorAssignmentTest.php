<?php

namespace Tests\Feature;

use App\Enums\AssureStatut;
use App\Models\Role;
use App\Models\AdminUser;
use App\Models\Assure;
use App\Models\Dossier;
use App\Models\PlatformSetting;
use App\Services\AdminDossierService;
use App\Services\DossierAffectationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Validation à deux niveaux : le dossier validé par un gestionnaire est confié à UN superviseur. */
class DossierSupervisorAssignmentTest extends TestCase
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

    private function dossier(string $gestionnaire, ?Assure $assure = null): Dossier
    {
        $this->seq++;
        $assure ??= Assure::query()->create([
            'nom' => 'Assure', 'prenom' => "N{$this->seq}", 'matricule' => "MAT-{$this->seq}", 'numero_cim' => 'C', 'numero_cama' => "K{$this->seq}",
            'email' => "a{$this->seq}@example.bf", 'password' => 'password', 'statut' => AssureStatut::Actif, 'journal' => [],
        ]);

        return Dossier::query()->create([
            'assure_id' => $assure->id, 'ref' => "REF-{$this->seq}", 'nom' => 'Membre', 'prenom' => 'Test', 'lien' => 'Enfant',
            'statut' => 'Soumis', 'gestionnaire' => $gestionnaire, 'journal' => [],
        ]);
    }

    private function service(): AdminDossierService
    {
        return app(AdminDossierService::class);
    }

    protected function setUp(): void
    {
        parent::setUp();
        PlatformSetting::query()->updateOrCreate(['key' => 'validation_2niveaux'], ['value' => true]);
    }

    public function test_level_one_validation_goes_to_the_least_loaded_supervisor(): void
    {
        $gest = $this->account('Gest', 'gestionnaire');
        $alpha = $this->account('Alpha', 'superviseur');
        $beta = $this->account('Beta', 'superviseur');

        // Alpha a déjà un dossier en supervision : le suivant va à Beta.
        $this->dossier($gest->display_name)->update(['statut' => 'En attente supervision', 'superviseur' => $alpha->display_name]);

        $dossier = $this->dossier($gest->display_name);
        $this->service()->validate($dossier, $gest);

        $dossier->refresh();
        $this->assertSame('En attente supervision', $dossier->statut);
        $this->assertSame($beta->display_name, $dossier->superviseur);
        $journal = $dossier->journal;
        $this->assertStringContainsString("affecté à {$beta->display_name}", end($journal)['libelle']);
    }

    public function test_the_same_family_stays_with_the_same_supervisor(): void
    {
        $gest = $this->account('Gest', 'gestionnaire');
        $this->account('Alpha', 'superviseur');
        $this->account('Beta', 'superviseur');

        $premier = $this->dossier($gest->display_name);
        $this->service()->validate($premier, $gest);
        $second = $this->dossier($gest->display_name, $premier->assure);
        $this->service()->validate($second, $gest);

        $this->assertSame($premier->fresh()->superviseur, $second->fresh()->superviseur);
    }

    public function test_only_accounts_with_the_receive_supervision_permission_are_candidates(): void
    {
        $chef = $this->account('Chef', 'superviseur');
        $sans = Role::query()->create(['slug' => 'sup-bis', 'nom' => 'Sup bis', 'rang' => 20, 'systeme' => false, 'actif' => true,
            'permissions' => ['dossiers.view', 'dossiers.supervise']]);
        AdminUser::query()->create(['nom' => 'Bis', 'prenom' => 'Test', 'email' => 'bis@cama.bf', 'password' => 'motdepasse-solide',
            'role' => 'sup-bis', 'actif' => true]);

        $names = array_keys(app(DossierAffectationService::class)->supervisorAccounts());

        $this->assertSame([$chef->display_name], $names);
    }

    public function test_without_any_supervisor_the_dossier_goes_to_the_common_queue(): void
    {
        $gest = $this->account('Gest', 'gestionnaire');
        $dossier = $this->dossier($gest->display_name);

        $this->service()->validate($dossier, $gest);

        $this->assertSame('En attente supervision', $dossier->fresh()->statut);
        $this->assertNull($dossier->fresh()->superviseur);
    }

    public function test_manual_reassignment_and_validation_of_the_dossier(): void
    {
        $gest = $this->account('Gest', 'gestionnaire');
        $this->account('Alpha', 'superviseur');
        $beta = $this->account('Beta', 'superviseur');
        $dossier = $this->dossier($gest->display_name);
        $this->service()->validate($dossier, $gest);

        $this->service()->assignSupervisorToFamily($dossier->assure_id, $beta->display_name, $beta);
        $this->assertSame($beta->display_name, $dossier->fresh()->superviseur);

        $this->expectException(ValidationException::class);
        $this->service()->assignSupervisorToFamily($dossier->assure_id, $gest->display_name, $beta);
    }

    public function test_a_supervisor_without_view_all_only_sees_the_dossiers_confided_to_him(): void
    {
        $gest = $this->account('Gest', 'gestionnaire');
        $role = Role::query()->create(['slug' => 'sup-perso', 'nom' => 'Sup perso', 'rang' => 20, 'systeme' => false, 'actif' => true,
            'permissions' => ['dossiers.view', 'dossiers.process', 'dossiers.supervise', 'dossiers.receive_supervision']]);
        $perso = AdminUser::query()->create(['nom' => 'Perso', 'prenom' => 'Test', 'email' => 'perso@cama.bf', 'password' => 'motdepasse-solide',
            'role' => 'sup-perso', 'actif' => true])->fresh();

        $mien = $this->dossier($gest->display_name);
        $this->service()->validate($mien, $gest);
        $autre = $this->dossier($gest->display_name);
        $autre->update(['statut' => 'En attente supervision', 'superviseur' => 'Quelqu\'un d\'autre']);

        $this->assertSame($perso->display_name, $mien->fresh()->superviseur);
        $this->assertTrue($mien->fresh()->isVisibleTo($perso));
        $this->assertFalse($autre->fresh()->isVisibleTo($perso));
        $this->assertSame([$mien->id], Dossier::query()->visibleTo($perso)->pluck('id')->all());
    }
}
