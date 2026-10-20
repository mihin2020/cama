<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\AdminUser;
use App\Services\AdminLogin2faService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AdminAuthSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(array $attributes = []): AdminUser
    {
        return AdminUser::query()->create(array_merge([
            'nom' => 'Test',
            'prenom' => 'Admin',
            'email' => 'admin-test@cama.bf',
            'password' => 'motdepasse-solide',
            'role' => AdminRole::Administrateur,
            'actif' => true,
        ], $attributes))->fresh();
    }

    private function login(array $overrides = [])
    {
        return $this->post(route('admin.login.store'), array_merge([
            'email' => 'admin-test@cama.bf',
            'password' => 'motdepasse-solide',
        ], $overrides));
    }

    public function test_two_factor_is_disabled_by_default(): void
    {
        $this->assertFalse($this->makeAdmin()->deux_fa_active);
    }

    public function test_login_without_2fa_opens_session_and_records_last_login(): void
    {
        $admin = $this->makeAdmin();

        $this->login()->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin, 'admin');
        $this->assertNotNull($admin->fresh()->last_login_at);
    }

    public function test_remember_cookie_is_limited_to_configured_duration(): void
    {
        $this->makeAdmin();

        $response = $this->login(['remember' => true]);

        $cookie = $response->getCookie(Auth::guard('admin')->getRecallerName(), false);
        $this->assertNotNull($cookie);

        $days = ($cookie->getExpiresTime() - time()) / 86400;
        $this->assertEqualsWithDelta(3, $days, 0.1);
    }

    private function idleState(int $secondsAgo, bool $remembered = false): array
    {
        return ['admin_session' => [
            'started_at' => time() - $secondsAgo,
            'last_activity' => time() - $secondsAgo,
            'remembered' => $remembered,
        ]];
    }

    public function test_session_expires_after_15_minutes_of_inactivity(): void
    {
        $admin = $this->makeAdmin(['deconnexion_auto_active' => true]);

        $this->actingAs($admin, 'admin')
            ->withSession($this->idleState(16 * 60))
            ->get(route('admin.profil'))
            ->assertRedirect(route('admin.login'));

        $this->assertGuest('admin');
    }

    public function test_activity_ping_extends_the_session(): void
    {
        $admin = $this->makeAdmin(['deconnexion_auto_active' => true]);

        $this->actingAs($admin, 'admin')
            ->withSession($this->idleState(14 * 60))
            ->postJson(route('admin.session.ping'))
            ->assertOk()
            ->assertJson(['enabled' => true, 'idleTimeout' => 900, 'remembered' => false]);

        $this->assertAuthenticatedAs($admin, 'admin');
        $this->assertEqualsWithDelta(time(), session('admin_session.last_activity'), 2);
    }

    public function test_idle_ping_returns_401_once_expired(): void
    {
        $admin = $this->makeAdmin(['deconnexion_auto_active' => true]);

        $this->actingAs($admin, 'admin')
            ->withSession($this->idleState(16 * 60))
            ->postJson(route('admin.session.ping'))
            ->assertStatus(401)
            ->assertJson(['expired' => true]);
    }

    public function test_remembered_device_is_not_logged_out_for_inactivity(): void
    {
        $admin = $this->makeAdmin(['deconnexion_auto_active' => true]);

        $this->actingAs($admin, 'admin')
            ->withSession($this->idleState(60 * 60, remembered: true))
            ->postJson(route('admin.session.ping'))
            ->assertOk()
            ->assertJson(['remembered' => true, 'rememberDays' => 3]);
    }

    public function test_idle_logout_is_disabled_by_default(): void
    {
        $admin = $this->makeAdmin();
        $this->assertFalse($admin->deconnexion_auto_active);

        $this->actingAs($admin, 'admin')
            ->withSession($this->idleState(60 * 60))
            ->postJson(route('admin.session.ping'))
            ->assertOk()
            ->assertJson(['enabled' => false]);

        $this->assertAuthenticatedAs($admin, 'admin');
    }

    public function test_enabling_idle_logout_needs_no_password_but_disabling_does(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin, 'admin')
            ->patch(route('admin.profil.idle'), ['active' => true])
            ->assertSessionHasNoErrors();
        $this->assertTrue($admin->fresh()->deconnexion_auto_active);

        $this->actingAs($admin, 'admin')
            ->patch(route('admin.profil.idle'), ['active' => false])
            ->assertSessionHasErrors('current_password');
        $this->assertTrue($admin->fresh()->deconnexion_auto_active);

        $this->actingAs($admin, 'admin')
            ->patch(route('admin.profil.idle'), ['active' => false, 'current_password' => 'motdepasse-solide'])
            ->assertSessionHasNoErrors();
        $this->assertFalse($admin->fresh()->deconnexion_auto_active);
    }

    public function test_login_starts_idle_tracking(): void
    {
        $this->makeAdmin();

        $this->login(['remember' => true]);

        $this->assertTrue(session('admin_session.remembered'));
        $this->assertEqualsWithDelta(time(), session('admin_session.last_activity'), 2);
    }

    public function test_login_is_locked_after_too_many_failures(): void
    {
        $this->makeAdmin();

        for ($i = 0; $i < 5; $i++) {
            $this->login(['password' => 'mauvais'])->assertSessionHasErrors('email');
        }

        $this->login()->assertSessionHasErrors(['email' => 'Trop de tentatives de connexion. Réessayez dans 5 minute(s).']);
        $this->assertGuest('admin');
    }

    public function test_login_with_2fa_requires_code_before_session(): void
    {
        config(['mail.default' => 'log']);
        $admin = $this->makeAdmin(['deux_fa_active' => true]);

        $this->login(['remember' => true])->assertRedirect(route('admin.login.2fa'));
        $this->assertGuest('admin');

        $code = session('dev_2fa_code');
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $this->assertNotSame($code, $admin->fresh()->login_2fa_code, 'Le code ne doit pas être stocké en clair.');

        $this->post(route('admin.login.2fa.store'), ['code' => $code])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin, 'admin');
        $this->assertNull($admin->fresh()->login_2fa_code);
    }

    public function test_2fa_code_is_cancelled_after_max_attempts(): void
    {
        config(['mail.default' => 'log']);
        $admin = $this->makeAdmin();
        $service = app(AdminLogin2faService::class);
        $code = $service->sendCode($admin);
        $wrong = $code === '000000' ? '111111' : '000000';

        for ($i = 0; $i < AdminLogin2faService::MAX_ATTEMPTS; $i++) {
            try {
                $service->verify($admin->fresh(), $wrong);
                $this->fail('Un code incorrect a été accepté.');
            } catch (ValidationException) {
            }
        }

        // Même le bon code est refusé : il a été annulé.
        $this->expectException(ValidationException::class);
        $service->verify($admin->fresh(), $code);
    }

    public function test_deactivated_admin_is_logged_out_on_next_request(): void
    {
        $admin = $this->makeAdmin();
        $this->actingAs($admin, 'admin');

        $admin->forceFill(['actif' => false])->save();

        $this->get(route('admin.profil'))->assertRedirect(route('admin.login'));
        $this->assertGuest('admin');
    }

    public function test_password_change_revokes_remember_tokens(): void
    {
        $admin = $this->makeAdmin();
        $admin->forceFill(['remember_token' => 'ancien-jeton'])->save();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.profil.password'), [
                'current_password' => 'motdepasse-solide',
                'password' => 'nouveau-motdepasse-solide',
                'password_confirmation' => 'nouveau-motdepasse-solide',
            ])
            ->assertSessionHasNoErrors();

        $this->assertNotSame('ancien-jeton', $admin->fresh()->remember_token);
    }

    public function test_disabling_2fa_requires_current_password(): void
    {
        $admin = $this->makeAdmin(['deux_fa_active' => true]);

        $this->actingAs($admin, 'admin')
            ->patch(route('admin.profil.2fa'), ['active' => false, 'current_password' => 'mauvais'])
            ->assertSessionHasErrors('current_password');
        $this->assertTrue($admin->fresh()->deux_fa_active);

        $this->actingAs($admin, 'admin')
            ->patch(route('admin.profil.2fa'), ['active' => false, 'current_password' => 'motdepasse-solide'])
            ->assertSessionHasNoErrors();
        $this->assertFalse($admin->fresh()->deux_fa_active);
    }

    public function test_2fa_code_is_never_shown_in_production_even_with_log_mailer(): void
    {
        $admin = $this->makeAdmin(['deux_fa_active' => true]);
        config(['mail.default' => 'log']);
        $service = app(AdminLogin2faService::class);

        $this->assertNotNull($service->sendCode($admin), 'En test, le code reste visible pour le développement.');

        $this->app['env'] = 'production';
        $this->assertNull($service->sendCode($admin->fresh()), 'En production, le code ne doit jamais être renvoyé à l\'écran.');
    }
}
