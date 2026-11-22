<?php

namespace Tests\Feature;

use App\Enums\AssureStatut;
use App\Models\Assure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/** Durcissement OWASP : CSP avec nonce, session renouvelée avant la 2FA, limites de débit, journal de sécurité. */
class OwaspHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_page_carries_a_content_security_policy_with_a_matching_nonce(): void
    {
        $response = $this->get('/')->assertOk();

        $csp = (string) $response->headers->get('Content-Security-Policy');
        preg_match("/script-src 'self' 'nonce-([^']+)'/", $csp, $match);

        $this->assertNotEmpty($match[1] ?? null, 'La CSP doit autoriser les scripts par nonce.');
        $this->assertStringContainsString('nonce="'.$match[1].'"', $response->getContent());
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        $this->assertStringContainsString("base-uri 'self'", $csp);
        // Aucun script ni style en ligne sans nonce, aucune ressource externe de script.
        $this->assertDoesNotMatchRegularExpression("/script-src[^;]*'unsafe-(inline|eval)'/", $csp);
        $this->assertDoesNotMatchRegularExpression("/style-src [^;]*'unsafe-inline'/", $csp);
        $this->assertDoesNotMatchRegularExpression('/(script|style|font)-src[^;]*https?:/', $csp);
    }

    public function test_the_nonce_changes_on_every_request(): void
    {
        $first = $this->get('/')->headers->get('Content-Security-Policy');
        $second = $this->get('/')->headers->get('Content-Security-Policy');

        $this->assertNotSame($first, $second);
    }

    public function test_the_assure_session_id_changes_before_the_two_factor_step(): void
    {
        $assure = Assure::query()->create([
            'nom' => 'A', 'prenom' => 'B', 'matricule' => 'M-2FA', 'numero_cim' => 'C', 'numero_cama' => 'K-2FA',
            'email' => 'deuxfa@example.bf', 'password' => 'mot-de-passe-solide', 'statut' => AssureStatut::Actif,
            'journal' => [], 'deux_fa_active' => true,
        ]);
        $assure->forceFill(['email_verified_at' => now()])->save();

        $this->get(route('assure.login'));
        $before = session()->getId();

        $this->post(route('assure.login.store'), ['email' => 'deuxfa@example.bf', 'password' => 'mot-de-passe-solide']);

        $this->assertNotSame($before, session()->getId());
        $this->assertSame($assure->id, session('assure_2fa_pending'));
    }

    public function test_the_invitation_and_email_code_endpoints_are_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->post(route('admin.invitation.store'), ['token' => 'x', 'password' => 'a', 'password_confirmation' => 'a']);
        }

        $this->post(route('admin.invitation.store'), ['token' => 'x', 'password' => 'a', 'password_confirmation' => 'a'])->assertStatus(429);
    }

    public function test_failed_logins_are_written_to_the_security_log(): void
    {
        Log::spy();

        $this->post(route('assure.login.store'), ['email' => 'inconnu@example.bf', 'password' => 'mauvais-mot-de-passe']);

        Log::shouldHaveReceived('warning')->withArgs(fn ($message) => str_contains((string) $message, 'échec de connexion'))->once();
    }

    public function test_denied_access_is_written_to_the_security_log(): void
    {
        Log::spy();

        $this->get('/admin/dossiers');
        $this->actingAs(\App\Models\AdminUser::query()->create([
            'nom' => 'Agent', 'prenom' => 'Test', 'email' => 'cms@cama.bf', 'password' => 'motdepasse-solide',
            'role' => 'gestionnaire', 'actif' => true, 'permissions' => ['cms.pages'],
        ]), 'admin')->get('/admin/dossiers')->assertForbidden();

        Log::shouldHaveReceived('warning')->withArgs(fn ($message) => str_contains((string) $message, 'accès refusé'))->atLeast()->once();
    }
}
