<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Enums\AssureStatut;
use App\Models\AdminUser;
use App\Models\Assure;
use App\Models\Dossier;
use App\Support\AdminPermissions;
use App\Support\CmsRichTextSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminAccessScopeTest extends TestCase
{
    use RefreshDatabase;

    private function agent(string $nom, AdminRole $role = AdminRole::Gestionnaire, ?array $permissions = null): AdminUser
    {
        return AdminUser::query()->create([
            'nom' => $nom, 'prenom' => 'Test', 'email' => strtolower($nom).'@cama.bf',
            'password' => 'motdepasse-solide', 'role' => $role, 'actif' => true, 'permissions' => $permissions,
        ]);
    }

    private function dossier(?string $gestionnaire, string $statut = 'En instruction', string $ref = 'REF-1'): Dossier
    {
        $assure = Assure::query()->firstOrCreate(['email' => 'assure@example.bf'], [
            'nom' => 'Assure', 'prenom' => 'Un', 'matricule' => 'MAT-1', 'numero_cim' => 'CIM-1', 'numero_cama' => 'CAMA-1',
            'password' => 'password', 'statut' => AssureStatut::Actif, 'journal' => [],
        ]);

        return Dossier::query()->create([
            'assure_id' => $assure->id, 'ref' => $ref, 'nom' => 'Membre', 'prenom' => 'M', 'lien' => 'Enfant',
            'statut' => $statut, 'gestionnaire' => $gestionnaire, 'date_soumission' => '2026-10-01', 'journal' => [],
            'pieces' => [['type' => 'CNIB', 'path' => 'dossiers/cnib.pdf', 'filename' => 'cnib.pdf']],
        ]);
    }

    public function test_every_permission_checked_route_is_mapped_and_unmapped_ones_are_refused(): void
    {
        foreach (app('router')->getRoutes() as $route) {
            if (in_array('admin.permission', $route->gatherMiddleware(), true)) {
                $this->assertTrue(AdminPermissions::isMappedRoute($route->getName()), "Route non déclarée : {$route->getName()}");
            }
        }

        $this->assertFalse(AdminPermissions::isMappedRoute('admin.route-inconnue'));
    }

    public function test_inscription_fields_require_settings_permission(): void
    {
        $this->actingAs($this->agent('Agent'), 'admin')
            ->post(route('admin.parametres.inscription-fields'), ['fields' => []])
            ->assertForbidden();
    }

    public function test_agent_cannot_act_on_or_read_a_dossier_assigned_to_someone_else(): void
    {
        $agent = $this->agent('Agent');
        $other = $this->dossier('Autre Gestionnaire');

        $this->actingAs($agent, 'admin')
            ->post(route('admin.dossiers.reject', $other), ['motif' => 'Non'])
            ->assertForbidden();
        $this->actingAs($agent, 'admin')
            ->post(route('admin.dossiers.message', $other), ['texte' => 'Bonjour'])
            ->assertForbidden();
        $this->actingAs($agent, 'admin')
            ->get(route('admin.dossiers.piece.download', ['dossier' => $other, 'type' => 'CNIB']))
            ->assertForbidden();

        $this->assertSame('En instruction', $other->fresh()->statut);
    }

    public function test_agent_can_still_act_on_own_dossier(): void
    {
        $agent = $this->agent('Agent');
        $own = $this->dossier($agent->display_name);

        $this->actingAs($agent, 'admin')
            ->post(route('admin.dossiers.reject', $own), ['motif' => 'Pièce illisible'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Refusé', $own->fresh()->statut);
    }

    public function test_export_and_pdf_only_cover_own_dossiers_without_view_all(): void
    {
        $agent = $this->agent('Agent');
        $this->dossier($agent->display_name, ref: 'REF-MIEN');
        $this->dossier('Autre Gestionnaire', ref: 'REF-AUTRE');

        $csv = $this->actingAs($agent, 'admin')->post(route('admin.exports.download'), [
            'format' => 'csv', 'datasets' => ['dossiers' => ['ref']],
        ])->streamedContent();

        $this->assertStringContainsString('REF-MIEN', $csv);
        $this->assertStringNotContainsString('REF-AUTRE', $csv);

        $catalog = $this->actingAs($agent, 'admin')->get(route('admin.exports'))->viewData('page')['props']['catalog'];
        $this->assertSame(1, collect($catalog)->firstWhere('key', 'dossiers')['count']);

        $this->actingAs($agent, 'admin')
            ->postJson(route('admin.exports.dossier-pdf'), ['ref' => 'REF-AUTRE'])
            ->assertNotFound();
    }

    public function test_agent_cannot_withdraw_a_dossier_awaiting_supervision(): void
    {
        $agent = $this->agent('Agent');
        $dossier = $this->dossier($agent->display_name, 'En attente supervision');

        $this->actingAs($agent, 'admin')
            ->post(route('admin.dossiers.retrait', $dossier), ['motif' => 'Devenu militaire'])
            ->assertSessionHasErrors('statut');

        $this->assertSame('En attente supervision', $dossier->fresh()->statut);
    }

    public function test_expiry_check_reload_does_not_count_as_activity(): void
    {
        $admin = $this->agent('Admin', AdminRole::Administrateur);
        $admin->forceFill(['deconnexion_auto_active' => true])->save();
        $last = time() - 10 * 60;

        $this->actingAs($admin, 'admin')
            ->withSession(['admin_session' => ['started_at' => $last, 'last_activity' => $last, 'remembered' => false]])
            ->withHeader('X-Admin-Idle-Check', '1')
            ->get(route('admin.profil'))
            ->assertOk();

        $this->assertSame($last, session('admin_session.last_activity'));
    }

    public function test_session_expires_exactly_at_the_timeout(): void
    {
        $admin = $this->agent('Admin', AdminRole::Administrateur);
        $admin->forceFill(['deconnexion_auto_active' => true])->save();
        $last = time() - 15 * 60;

        $this->actingAs($admin, 'admin')
            ->withSession(['admin_session' => ['started_at' => $last, 'last_activity' => $last, 'remembered' => false]])
            ->get(route('admin.profil'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_changing_ip_does_not_bypass_the_login_limit(): void
    {
        $this->agent('Cible', AdminRole::Administrateur);

        for ($i = 0; $i < 20; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.{$i}"])
                ->post(route('admin.login.store'), ['email' => 'cible@cama.bf', 'password' => 'mauvais']);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.1.1'])
            ->post(route('admin.login.store'), ['email' => 'cible@cama.bf', 'password' => 'motdepasse-solide'])
            ->assertSessionHasErrors(['email' => 'Trop de tentatives de connexion. Réessayez dans 5 minute(s).']);
        $this->assertGuest('admin');
    }

    public function test_protocol_relative_images_are_removed(): void
    {
        $html = CmsRichTextSanitizer::sanitize('<p><img src="//tracker.example/x.gif"><img src="/\\tracker.example/y.gif"><img src="/storage/ok.png"></p>', CmsRichTextSanitizer::ARTICLE_TAGS);

        $this->assertStringNotContainsString('tracker.example', $html);
        $this->assertStringContainsString('/storage/ok.png', $html);
    }

    public function test_migration_revokes_permissions_that_the_role_lock_used_to_block(): void
    {
        $direction = $this->agent('Direction', AdminRole::Direction, ['dossiers.view', 'settings.manage', 'inscriptions.validate']);
        $gestionnaire = $this->agent('Gestion', AdminRole::Gestionnaire, ['inscriptions.validate', 'settings.manage']);

        $migration = require database_path('migrations/2026_10_31_100000_revoke_permissions_previously_blocked_by_role.php');
        $migration->up();

        $permissions = fn (AdminUser $u) => json_decode(DB::table('admin_users')->where('id', $u->id)->value('permissions'), true);
        $this->assertSame(['dossiers.view'], $permissions($direction));
        $this->assertSame(['inscriptions.validate'], $permissions($gestionnaire));
    }
}
