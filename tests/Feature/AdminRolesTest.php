<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\AdminUser;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AdminRolesTest extends TestCase
{
    use RefreshDatabase;

    private const WEBMASTER_PERMISSIONS = ['cms.dashboard', 'cms.pages', 'cms.articles', 'cms.media'];

    private function account(AdminRole|string $role, ?array $permissions = null, string $email = 'compte@cama.bf'): AdminUser
    {
        return AdminUser::query()->create([
            'nom' => 'Test', 'prenom' => 'Compte', 'email' => $email,
            'password' => 'motdepasse-solide', 'role' => $role, 'actif' => true, 'permissions' => $permissions,
        ])->fresh();
    }

    private function administrateur(): AdminUser
    {
        return $this->account(AdminRole::Administrateur, null, 'admin@cama.bf');
    }

    private function createWebmasterRole(AdminUser $admin): Role
    {
        $this->actingAs($admin, 'admin')
            ->post(route('admin.roles.store'), [
                'nom' => 'Webmaster',
                'description' => 'Gère le contenu du site public',
                'rang' => 20,
                'permissions' => self::WEBMASTER_PERMISSIONS,
            ])
            ->assertSessionHasNoErrors();

        return Role::query()->where('slug', 'webmaster')->firstOrFail();
    }

    public function test_the_four_original_roles_exist_as_system_roles(): void
    {
        $this->assertEqualsCanonicalizing(
            ['gestionnaire', 'superviseur', 'direction', 'administrateur'],
            Role::query()->where('systeme', true)->pluck('slug')->all(),
        );
    }

    public function test_administrator_creates_a_webmaster_role_and_a_webmaster_account(): void
    {
        Notification::fake();
        $admin = $this->administrateur();
        $role = $this->createWebmasterRole($admin);

        $this->assertSame(20, $role->rang);
        $this->assertFalse($role->systeme);
        $this->assertEqualsCanonicalizing(self::WEBMASTER_PERMISSIONS, $role->permissionKeys());

        // La fiche « Nouveau compte » propose le rôle et ses permissions.
        $this->actingAs($admin, 'admin')
            ->get(route('admin.utilisateurs'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('assignableRoles', fn ($roles) => collect($roles)->contains('value', 'webmaster'))
                ->where('rolePresets.webmaster', fn ($permissions) => collect($permissions)->sort()->values()->all()
                    === collect(self::WEBMASTER_PERMISSIONS)->sort()->values()->all()));

        $this->actingAs($admin, 'admin')
            ->post(route('admin.utilisateurs.store'), [
                'prenom' => 'Awa', 'nom' => 'Ouedraogo', 'email' => 'webmaster@cama.bf',
                'role' => 'webmaster', 'permissions' => self::WEBMASTER_PERMISSIONS,
            ])
            ->assertSessionHasNoErrors();

        $webmaster = AdminUser::query()->where('email', 'webmaster@cama.bf')->firstOrFail();
        $this->assertSame('webmaster', $webmaster->role);
        $this->assertSame('Webmaster', $webmaster->role_label);
        $this->assertTrue($webmaster->hasPermission('cms.pages'));
        $this->assertFalse($webmaster->hasPermission('dossiers.view'));
    }

    public function test_webmaster_logs_in_lands_on_the_cms_and_gets_403_elsewhere(): void
    {
        $role = $this->createWebmasterRole($this->administrateur());
        $this->account($role->slug, null, 'webmaster@cama.bf');
        auth('admin')->logout();

        $this->post(route('admin.login.store'), ['email' => 'webmaster@cama.bf', 'password' => 'motdepasse-solide'])
            ->assertRedirect(route('admin.dashboard'));

        // Pas de tableau de bord des dossiers pour lui : il arrive sur le studio de contenu.
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.cms.dashboard'));
        $this->get(route('admin.cms.pages'))->assertOk();

        $this->get(route('admin.dossiers'))
            ->assertForbidden()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Errors/Show')->where('status', 403));
        $this->get(route('admin.utilisateurs'))->assertForbidden();
        $this->get(route('admin.roles'))->assertForbidden();
    }

    public function test_account_of_a_disabled_role_cannot_log_in_and_is_logged_out(): void
    {
        $admin = $this->administrateur();
        $role = $this->createWebmasterRole($admin);
        $webmaster = $this->account($role->slug, null, 'webmaster@cama.bf');

        $this->actingAs($admin, 'admin')->post(route('admin.roles.toggle', $role))->assertSessionHasNoErrors();
        $this->assertFalse($role->fresh()->actif);

        // Session déjà ouverte : coupée à la requête suivante.
        $this->actingAs($webmaster, 'admin')
            ->get(route('admin.cms.pages'))
            ->assertRedirect(route('admin.login'));
        $this->assertGuest('admin');

        $this->post(route('admin.login.store'), ['email' => 'webmaster@cama.bf', 'password' => 'motdepasse-solide'])
            ->assertSessionHasErrors(['email' => 'Le rôle « Webmaster » est désactivé : ce compte ne peut pas se connecter. Contactez un administrateur.']);
    }

    public function test_account_without_any_permission_cannot_log_in(): void
    {
        $this->account(AdminRole::Gestionnaire, []);

        $this->post(route('admin.login.store'), ['email' => 'compte@cama.bf', 'password' => 'motdepasse-solide'])
            ->assertSessionHasErrors(['email' => "Aucune permission n'est attribuée à ce compte. Contactez un administrateur."]);
        $this->assertGuest('admin');
    }

    public function test_only_the_administrator_can_manage_roles(): void
    {
        $superviseur = $this->account(AdminRole::Superviseur, ['users.manage', 'dossiers.view', 'roles.manage']);

        // « roles.manage » demande le rang 100 : il n'a aucun effet sur un superviseur.
        $this->assertFalse($superviseur->hasPermission('roles.manage'));
        $this->actingAs($superviseur, 'admin')->get(route('admin.roles'))->assertForbidden();
        $this->actingAs($superviseur, 'admin')
            ->post(route('admin.roles.store'), ['nom' => 'Pirate', 'rang' => 99, 'permissions' => ['settings.manage']])
            ->assertForbidden();
        $this->assertDatabaseMissing('admin_roles', ['nom' => 'Pirate']);
    }

    public function test_supervisor_cannot_assign_a_role_above_his_rank(): void
    {
        Notification::fake();
        $superviseur = $this->account(AdminRole::Superviseur, ['users.manage', 'dossiers.view']);

        foreach (['direction', 'administrateur'] as $slug) {
            $this->actingAs($superviseur, 'admin')
                ->post(route('admin.utilisateurs.store'), [
                    'prenom' => 'X', 'nom' => 'Y', 'email' => "$slug-new@cama.bf", 'role' => $slug, 'permissions' => ['dossiers.view'],
                ])
                ->assertSessionHasErrors('role');
        }

        $this->actingAs($superviseur, 'admin')
            ->post(route('admin.utilisateurs.store'), [
                'prenom' => 'X', 'nom' => 'Y', 'email' => 'gest-new@cama.bf', 'role' => 'gestionnaire', 'permissions' => ['dossiers.view'],
            ])
            ->assertSessionHasNoErrors();
        $this->assertSame('gestionnaire', AdminUser::query()->where('email', 'gest-new@cama.bf')->value('role'));
    }

    public function test_role_permissions_respect_the_rank(): void
    {
        $admin = $this->administrateur();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.roles.store'), ['nom' => 'Assistant', 'rang' => 20, 'permissions' => ['users.manage']])
            ->assertSessionHasErrors('permissions');

        $this->actingAs($admin, 'admin')
            ->post(route('admin.roles.store'), ['nom' => 'Faux admin', 'rang' => 100, 'permissions' => []])
            ->assertSessionHasErrors('rang');
    }

    public function test_protected_roles_cannot_be_deleted_or_disabled(): void
    {
        $admin = $this->administrateur();
        $gestionnaire = Role::query()->where('slug', 'gestionnaire')->firstOrFail();
        $adminRole = Role::query()->where('slug', 'administrateur')->firstOrFail();
        $webmaster = $this->createWebmasterRole($admin);
        $this->account($webmaster->slug, null, 'webmaster@cama.bf');

        $this->actingAs($admin, 'admin')->delete(route('admin.roles.destroy', $gestionnaire))->assertSessionHasErrors('role');
        $this->actingAs($admin, 'admin')->delete(route('admin.roles.destroy', $webmaster))->assertSessionHasErrors('role');
        $this->actingAs($admin, 'admin')->post(route('admin.roles.toggle', $adminRole))->assertSessionHasErrors('role');

        $this->assertDatabaseHas('admin_roles', ['slug' => 'gestionnaire']);
        $this->assertDatabaseHas('admin_roles', ['slug' => 'webmaster']);
        $this->assertTrue($adminRole->fresh()->actif);
    }

    public function test_unused_custom_role_can_be_deleted(): void
    {
        $admin = $this->administrateur();
        $role = $this->createWebmasterRole($admin);

        $this->actingAs($admin, 'admin')->delete(route('admin.roles.destroy', $role))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('admin_roles', ['slug' => 'webmaster']);
    }

    public function test_role_changes_can_be_applied_to_existing_accounts(): void
    {
        $admin = $this->administrateur();
        $role = $this->createWebmasterRole($admin);
        $webmaster = $this->account($role->slug, ['cms.pages'], 'webmaster@cama.bf');

        $this->actingAs($admin, 'admin')
            ->put(route('admin.roles.update', $role), [
                'nom' => 'Webmaster', 'rang' => 20, 'permissions' => ['cms.pages', 'cms.faq'], 'apply_to_users' => true,
            ])
            ->assertSessionHasNoErrors();

        $this->assertEqualsCanonicalizing(['cms.pages', 'cms.faq'], $webmaster->fresh()->effective_permissions);
    }

    public function test_a_disabled_or_unknown_role_cannot_be_given_to_an_account(): void
    {
        $admin = $this->administrateur();
        $role = $this->createWebmasterRole($admin);
        $role->update(['actif' => false]);

        foreach (['webmaster', 'inexistant'] as $slug) {
            $this->actingAs($admin, 'admin')
                ->post(route('admin.utilisateurs.store'), [
                    'prenom' => 'X', 'nom' => 'Y', 'email' => "$slug@cama.bf", 'role' => $slug, 'permissions' => [],
                ])
                ->assertSessionHasErrors('role');
        }
    }
}
