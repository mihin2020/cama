<?php

namespace Tests\Feature;

use App\Enums\AssureStatut;
use App\Models\AdminUser;
use App\Models\Assure;
use App\Models\Dossier;
use App\Models\PlatformSetting;
use App\Models\Role;
use App\Services\DossierAffectationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Qui reçoit des dossiers : un compte actif dont le rôle donne « Recevoir des dossiers à traiter » et
 * « Traiter les dossiers ». Le critère est la permission, pas le nom du rôle.
 */
class DossierAssignmentPermissionTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    private function account(string $nom, string $role, bool $actif = true): AdminUser
    {
        return AdminUser::query()->create([
            'nom' => $nom, 'prenom' => 'Test', 'email' => strtolower($nom).'@cama.bf', 'password' => 'motdepasse-solide',
            'role' => $role, 'actif' => $actif,
        ])->fresh();
    }

    private function role(string $slug, array $permissions, bool $actif = true): Role
    {
        return Role::query()->create(['slug' => $slug, 'nom' => ucfirst($slug), 'rang' => 10, 'systeme' => false, 'actif' => $actif, 'permissions' => $permissions]);
    }

    private function pool(): array
    {
        return app(DossierAffectationService::class)->assignablePool();
    }

    public function test_gestionnaires_keep_receiving_dossiers_and_supervisors_do_not_by_default(): void
    {
        $gestionnaire = $this->account('Alpha', 'gestionnaire');
        $this->account('Chef', 'superviseur');

        // Le superviseur peut « Traiter » les dossiers, mais ne reçoit rien tant qu'on ne le décide pas.
        $this->assertTrue($this->account('Chef2', 'superviseur')->hasPermission('dossiers.process'));
        $this->assertSame([$gestionnaire->display_name], $this->pool());
    }

    public function test_a_custom_role_is_included_when_it_has_both_permissions(): void
    {
        $this->role('charge-dossiers', ['dossiers.view', 'dossiers.process', 'dossiers.receive']);
        $this->role('lecteur', ['dossiers.view', 'dossiers.receive']);
        $this->role('traiteur', ['dossiers.view', 'dossiers.process']);

        $charge = $this->account('Charge', 'charge-dossiers');
        $this->account('Lecteur', 'lecteur');
        $this->account('Traiteur', 'traiteur');

        // « Recevoir » sans « Traiter » : il recevrait des dossiers qu'il ne peut pas traiter. « Traiter »
        // sans « Recevoir » : il traite ce qu'on lui affecte à la main, sans rien recevoir d'office.
        $this->assertSame([$charge->display_name], $this->pool());
    }

    public function test_a_role_must_also_be_able_to_open_the_dossiers(): void
    {
        // Recevoir et traiter, mais pas consulter : il recevrait des dossiers qu'il ne peut pas ouvrir.
        $this->role('sans-acces', ['dossiers.process', 'dossiers.receive']);
        $this->account('Sansacces', 'sans-acces');

        $this->assertSame([], $this->pool());
    }

    public function test_the_lists_show_each_account_with_its_role(): void
    {
        $chef = $this->account('Chef', 'superviseur');
        $this->account('Alpha', 'gestionnaire');
        $superviseur = Role::query()->where('slug', 'superviseur')->firstOrFail();
        $superviseur->update(['permissions' => [...$superviseur->permissions, 'dossiers.receive']]);

        $accounts = app(DossierAffectationService::class)->assignableAccounts();

        $this->assertSame($chef->fresh()->role_label, $accounts[$chef->fresh()->display_name]);
        $this->assertCount(2, array_unique($accounts));

        $gestionnaires = app(\App\Services\AdminDossierService::class)->gestionnaires();
        $row = collect($gestionnaires)->firstWhere('nom', $chef->fresh()->display_name);
        $this->assertSame($chef->fresh()->role_label, $row['role']);
        $this->assertTrue($row['actif']);
    }

    public function test_giving_the_permission_to_the_supervisor_role_adds_the_supervisors(): void
    {
        $chef = $this->account('Chef', 'superviseur');
        $this->assertNotContains($chef->display_name, $this->pool());

        $superviseur = Role::query()->where('slug', 'superviseur')->firstOrFail();
        $superviseur->update(['permissions' => [...$superviseur->permissions, 'dossiers.receive']]);

        $this->assertContains($chef->fresh()->display_name, $this->pool());
    }

    public function test_inactive_accounts_disabled_roles_and_administrators_never_receive(): void
    {
        $this->role('desactive', ['dossiers.view', 'dossiers.process', 'dossiers.receive'], actif: false);
        $this->account('Inactif', 'gestionnaire', actif: false);
        $this->account('RoleOff', 'desactive');
        $this->account('Admin', 'administrateur');

        $this->assertSame([], $this->pool());
    }

    public function test_automatic_assignment_follows_the_same_rule(): void
    {
        $this->role('charge-dossiers', ['dossiers.view', 'dossiers.process', 'dossiers.receive']);
        $charge = $this->account('Charge', 'charge-dossiers');
        $this->account('Chef', 'superviseur');
        $this->account('Admin', 'administrateur');
        PlatformSetting::query()->updateOrCreate(['key' => 'affectation_mode'], ['value' => 'round_robin']);

        foreach (range(1, 4) as $i) {
            $this->seq++;
            $assure = Assure::query()->create([
                'nom' => 'Assure', 'prenom' => "N{$this->seq}", 'matricule' => "MAT-{$this->seq}", 'numero_cim' => 'C', 'numero_cama' => "K{$this->seq}",
                'email' => "a{$this->seq}@example.bf", 'password' => 'password', 'statut' => AssureStatut::Actif, 'journal' => [],
            ]);
            $dossier = Dossier::query()->create([
                'assure_id' => $assure->id, 'ref' => "REF-{$this->seq}", 'nom' => 'M', 'prenom' => 'M', 'lien' => 'Enfant',
                'statut' => 'Soumis', 'journal' => [],
            ]);

            $this->assertSame($charge->display_name, app(DossierAffectationService::class)->autoAssign($dossier));
        }
    }

    public function test_the_permission_exists_in_the_catalog_and_the_gestionnaire_role(): void
    {
        $this->assertContains('dossiers.receive', \App\Support\AdminPermissions::allKeys());
        $this->assertContains('dossiers.receive', Role::query()->where('slug', 'gestionnaire')->firstOrFail()->permissions);
        $this->assertNotContains('dossiers.receive', Role::query()->where('slug', 'superviseur')->firstOrFail()->permissions);
    }
}
