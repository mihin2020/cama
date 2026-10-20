<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Enums\AssureStatut;
use App\Models\AdminUser;
use App\Models\Assure;
use App\Models\Dossier;
use App\Services\AdminDossierService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AdminPermissionGovernanceTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    private function account(AdminRole $role, ?array $permissions = null): AdminUser
    {
        $n = ++$this->seq;

        return AdminUser::query()->create([
            'nom' => "Agent{$n}", 'prenom' => 'Test', 'email' => "agent{$n}@cama.bf", 'password' => 'motdepasse-solide',
            'role' => $role, 'actif' => true, 'permissions' => $permissions,
        ]);
    }

    private function payload(AdminUser $user, array $permissions, ?AdminRole $role = null): array
    {
        return [
            'nom' => $user->nom, 'prenom' => $user->prenom, 'email' => $user->email,
            'role' => ($role ?? $user->role)->value, 'permissions' => $permissions,
        ];
    }

    private function switchTo(AdminUser $user): self
    {
        // Nouvelle session à chaque changement de compte (la protection de session le demande).
        $this->flushSession();
        $this->app['auth']->forgetGuards();

        return $this->actingAs($user, 'admin');
    }

    public function test_checked_permission_opens_the_module_whatever_the_role(): void
    {
        $gestionnaire = $this->account(AdminRole::Gestionnaire, ['dossiers.view', 'settings.manage']);
        $this->switchTo($gestionnaire)->get(route('admin.parametres'))->assertOk();

        $sansDroit = $this->account(AdminRole::Superviseur, ['dossiers.view']);
        $this->switchTo($sansDroit)->get(route('admin.parametres'))->assertForbidden();
    }

    public function test_nobody_can_grant_a_permission_he_does_not_have(): void
    {
        $superviseur = $this->account(AdminRole::Superviseur, ['users.manage', 'dossiers.view']);
        $cible = $this->account(AdminRole::Gestionnaire, ['dossiers.view']);

        $this->switchTo($superviseur)
            ->put(route('admin.utilisateurs.update', $cible), $this->payload($cible, ['dossiers.view', 'settings.manage']))
            ->assertSessionHasErrors('permissions');

        $this->assertNotContains('settings.manage', $cible->fresh()->effective_permissions);
    }

    public function test_rights_given_by_an_administrator_are_kept_when_a_supervisor_edits_the_account(): void
    {
        $superviseur = $this->account(AdminRole::Superviseur, ['users.manage', 'dossiers.view']);
        $cible = $this->account(AdminRole::Gestionnaire, ['dossiers.view', 'settings.manage']);

        $this->switchTo($superviseur)
            ->put(route('admin.utilisateurs.update', $cible), [...$this->payload($cible, ['dossiers.view', 'settings.manage']), 'prenom' => 'Renommé'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Renommé', $cible->fresh()->prenom);
    }

    public function test_an_agent_cannot_change_his_own_rights(): void
    {
        $superviseur = $this->account(AdminRole::Superviseur, ['users.manage', 'dossiers.view', 'exports.export']);

        $this->switchTo($superviseur)
            ->put(route('admin.utilisateurs.update', $superviseur), $this->payload($superviseur, ['users.manage', 'dossiers.view']))
            ->assertSessionHasErrors('permissions');
    }

    public function test_account_management_is_reserved_to_supervisors_and_administrators(): void
    {
        $admin = $this->account(AdminRole::Administrateur);
        $gestionnaire = $this->account(AdminRole::Gestionnaire, ['dossiers.view']);

        $this->switchTo($admin)
            ->put(route('admin.utilisateurs.update', $gestionnaire), $this->payload($gestionnaire, ['dossiers.view', 'users.manage']))
            ->assertSessionHasErrors('permissions');

        // Même enregistrée à la main en base, la permission n'est pas effective pour ce rôle.
        $gestionnaire->update(['permissions' => ['dossiers.view', 'users.manage']]);
        $this->assertFalse($gestionnaire->fresh()->hasPermission('users.manage'));
    }

    public function test_full_studio_access_does_not_include_free_html(): void
    {
        $editeur = $this->account(AdminRole::Superviseur, ['cms.manage']);

        $this->assertTrue($editeur->hasPermission('cms.publish'));
        $this->assertFalse($editeur->hasPermission('cms.html'));
    }

    public function test_supervision_is_a_permission_not_a_role(): void
    {
        $assure = Assure::query()->create([
            'nom' => 'Assure', 'prenom' => 'Test', 'matricule' => 'MAT-1', 'numero_cim' => 'CIM-1', 'numero_cama' => 'CAMA-1',
            'email' => 'assure@example.bf', 'password' => 'password', 'statut' => AssureStatut::Actif, 'journal' => [],
        ]);
        $dossier = Dossier::query()->create([
            'assure_id' => $assure->id, 'ref' => 'REF-1', 'nom' => 'Membre', 'prenom' => 'Test', 'lien' => 'Enfant',
            'statut' => 'En attente supervision', 'journal' => [],
        ]);
        $service = app(AdminDossierService::class);

        $superviseurSansDroit = $this->account(AdminRole::Superviseur, ['dossiers.view', 'dossiers.process']);
        try {
            $service->refuse($dossier, 'Motif', $superviseurSansDroit);
            $this->fail('Refus accepté sans la permission de supervision.');
        } catch (ValidationException) {
        }

        $gestionnaireSuperviseur = $this->account(AdminRole::Gestionnaire, ['dossiers.view', 'dossiers.process', 'dossiers.supervise']);
        $service->refuse($dossier, 'Motif', $gestionnaireSuperviseur);
        $this->assertSame('Refusé', $dossier->fresh()->statut);
    }

    public function test_dossier_scope_follows_the_view_all_permission(): void
    {
        $service = app(AdminDossierService::class);

        $this->assertTrue($service->seesOnlyOwnDossiers($this->account(AdminRole::Superviseur, ['dossiers.view'])));
        $this->assertFalse($service->seesOnlyOwnDossiers($this->account(AdminRole::Gestionnaire, ['dossiers.view', 'dossiers.view_all'])));
    }
}
