<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\AdminUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;
use RuntimeException;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(AdminRole $role): AdminUser
    {
        return AdminUser::query()->create([
            'nom' => 'Test',
            'prenom' => 'Admin',
            'email' => 'erreurs@cama.bf',
            'password' => 'motdepasse-solide',
            'role' => $role,
            'actif' => true,
        ])->fresh();
    }

    public function test_unknown_address_shows_the_custom_404_page(): void
    {
        $this->get('/cette/adresse/nexiste/pas')
            ->assertNotFound()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Errors/Show')
                ->where('status', 404)
                ->where('espace', 'site')
                ->has('branding.logoUrl'));
    }

    public function test_unknown_cms_page_shows_the_custom_404_page(): void
    {
        $this->get('/page-cms-inexistante')
            ->assertNotFound()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Errors/Show')->where('status', 404));
    }

    public function test_admin_without_permission_gets_the_custom_403_page(): void
    {
        $this->actingAs($this->makeAdmin(AdminRole::Gestionnaire), 'admin')
            ->get(route('admin.utilisateurs'))
            ->assertForbidden()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Errors/Show')
                ->where('status', 403)
                ->where('espace', 'admin')
                ->where('actions.0.href', route('admin.dashboard')));
    }

    public function test_inertia_navigation_also_receives_the_error_page(): void
    {
        $version = $this->get('/')->viewData('page')['version'];

        $this->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => $version, 'X-Requested-With' => 'XMLHttpRequest'])
            ->get('/introuvable/vraiment')
            ->assertNotFound()
            ->assertJsonPath('component', 'Errors/Show')
            ->assertJsonPath('props.status', 404);
    }

    public function test_server_error_shows_the_custom_500_page_when_debug_is_off(): void
    {
        config(['app.debug' => false]);
        Route::middleware('web')->get('/_test/panne', fn () => throw new RuntimeException('detail-technique-confidentiel'));

        $this->get('/_test/panne')
            ->assertStatus(500)
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Errors/Show')->where('status', 500))
            ->assertDontSee('detail-technique-confidentiel', false);
    }

    public function test_too_many_requests_keeps_the_retry_after_header(): void
    {
        Route::middleware(['web', 'throttle:1,1'])->get('/_test/limite', fn () => 'ok');

        $this->get('/_test/limite')->assertOk();
        $this->get('/_test/limite')
            ->assertStatus(429)
            ->assertHeader('Retry-After')
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Errors/Show')->where('status', 429));
    }

    public function test_expired_session_redirects_back_with_a_message(): void
    {
        Route::middleware('web')->post('/_test/expire', fn () => abort(419));

        $this->from('/contact')
            ->post('/_test/expire')
            ->assertRedirect('/contact')
            ->assertSessionHas('error', 'Votre session a expiré. Veuillez réessayer.');
    }

    public function test_api_requests_keep_a_json_error(): void
    {
        // Seules les adresses api/* répondent en JSON (bootstrap/app.php, shouldRenderJsonWhen).
        $this->getJson('/api/introuvable')
            ->assertNotFound()
            ->assertJsonStructure(['message']);
    }

    public function test_blade_fallback_pages_exist_and_load_nothing_from_outside(): void
    {
        foreach ([403, 404, 419, 429, 500, 503] as $status) {
            $html = view("errors.$status")->render();

            $this->assertStringContainsString((string) $status, $html);
            $this->assertStringNotContainsString('<script', $html);
            // Aucune adresse absolue : le logo et les liens doivent marcher quelle que soit APP_URL ou le schéma forcé.
            $this->assertDoesNotMatchRegularExpression('~(src|href)="https?://~', $html);
            $this->assertStringContainsString('src="/images/logo_cama.png"', $html);
        }
    }
}
