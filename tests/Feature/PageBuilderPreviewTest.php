<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\AdminUser;
use App\Models\CmsPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class PageBuilderPreviewTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): AdminUser
    {
        return AdminUser::query()->create([
            'nom' => 'Test',
            'prenom' => 'Cms',
            'email' => 'cms-test@cama.bf',
            'password' => 'motdepasse-solide',
            'role' => AdminRole::Administrateur,
            'actif' => true,
        ]);
    }

    public function test_draft_user_page_is_rendered_with_the_public_component(): void
    {
        $page = CmsPage::query()->create([
            'title' => 'Ma page brouillon',
            'slug' => 'ma-page-brouillon',
            'status' => 'draft',
            'sections_json' => [],
        ]);

        // Le site public refuse les brouillons…
        $this->get('/ma-page-brouillon')->assertNotFound();

        // … l'aperçu de l'éditeur les affiche avec le même composant que le site.
        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.cms.page_builder.preview', ['page' => $page, 'mode' => 'edit']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $inertia) => $inertia
                ->component('Public/CmsPage')
                ->where('cmsPage.title', 'Ma page brouillon')
                ->where('builderPreview.mode', 'edit')
                ->where('builderPreview.pageId', $page->id));
    }

    public function test_system_page_preview_uses_its_public_page_and_data(): void
    {
        $page = CmsPage::query()->updateOrCreate(['slug' => 'services'], [
            'title' => 'Services',
            'status' => 'published',
        ]);

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.cms.page_builder.preview', ['page' => $page]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $inertia) => $inertia
                ->component('Public/Services')
                ->has('faqs')
                ->has('partners')
                ->where('builderPreview.mode', 'preview'));
    }

    public function test_public_pages_never_receive_the_builder_flag(): void
    {
        $this->get('/services')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $inertia) => $inertia
                ->component('Public/Services')
                ->missing('builderPreview'));
    }

    public function test_preview_requires_an_admin(): void
    {
        $page = CmsPage::query()->create(['title' => 'X', 'slug' => 'x-page', 'status' => 'draft']);

        $this->get(route('admin.cms.page_builder.preview', ['page' => $page]))
            ->assertRedirect(route('admin.login'));
    }
}
