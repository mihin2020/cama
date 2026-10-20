<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\AdminUser;
use App\Models\CmsPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class LegalNoticeModuleTest extends TestCase
{
    use RefreshDatabase;

    private function admin(array $permissions = ['cms.legal']): AdminUser
    {
        return AdminUser::query()->firstOrCreate(['email' => 'legal@cama.bf'], [
            'nom' => 'Agent', 'prenom' => 'Juridique', 'password' => 'motdepasse-solide',
            'role' => AdminRole::Gestionnaire, 'actif' => true, 'permissions' => $permissions,
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Confidentialité CAMA',
            'intro' => 'Notre engagement.',
            'articles' => [
                ['title' => 'Protection des données', 'icon' => 'security', 'anchor' => 'rgpd', 'body' => "Texte.\n\n- point un\n- point deux"],
                ['title' => 'Cookies', 'icon' => 'cookie', 'anchor' => 'cookies', 'body' => 'Uniquement techniques.'],
                ['title' => 'Vos droits', 'icon' => '', 'anchor' => 'cookies', 'body' => 'Accès et rectification.'],
            ],
        ], $overrides);
    }

    public function test_module_is_reserved_to_the_legal_permission(): void
    {
        $this->actingAs($this->admin(['cms.pages']), 'admin')->get(route('admin.cms.legal'))->assertForbidden();

        $this->flushSession();
        $this->app['auth']->forgetGuards();
        AdminUser::query()->delete();

        $this->actingAs($this->admin(), 'admin')->get(route('admin.cms.legal'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Cms/Legal/Index')
                ->where('notice.custom', false)
                ->has('notice.articles', 3)
                ->has('coordinates'));
    }

    public function test_publishing_updates_the_public_page_immediately(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->put(route('admin.cms.legal.update'), $this->payload())
            ->assertSessionHasNoErrors();

        $this->get('/mention_legales')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('pageSections.0.columns.0.widgets.0.content.title', 'Confidentialité CAMA')
                // Plus de DPO sur la page (retiré à la demande de la CAMA).
                ->missing('pageSections.0.columns.0.widgets.0.content.dpoEmail')
                ->has('pageSections.0.columns.0.widgets.0.content.articles', 3)
                ->where('pageSections.0.columns.0.widgets.0.content.articles.0.anchor', 'rgpd')
                // Ancre en double : rendue unique ; icône vide : icône par défaut.
                ->where('pageSections.0.columns.0.widgets.0.content.articles.2.anchor', 'cookies-2')
                ->where('pageSections.0.columns.0.widgets.0.content.articles.2.icon', 'article'));
    }

    public function test_every_section_needs_a_title_and_a_text(): void
    {
        $payload = $this->payload();
        $payload['articles'][1]['body'] = '';

        $this->actingAs($this->admin(), 'admin')
            ->put(route('admin.cms.legal.update'), $payload)
            ->assertSessionHasErrors('articles.1.body');
    }

    public function test_original_text_can_be_restored(): void
    {
        $this->actingAs($this->admin(), 'admin')->put(route('admin.cms.legal.update'), $this->payload());
        $this->actingAs($this->admin(), 'admin')->delete(route('admin.cms.legal.reset'))->assertSessionHasNoErrors();

        $this->get('/mention_legales')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('pageSections.0.columns.0.widgets.0.content.title', 'Informations légales & confidentialité'));
    }

    public function test_legal_notice_is_no_longer_a_page_builder_page(): void
    {
        $this->assertSame(0, CmsPage::query()->where('slug', 'mention_legales')->count());
    }
}
