<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\AdminUser;
use App\Models\CmsMenuItem;
use App\Models\CmsPage;
use App\Services\CmsMenuService;
use App\Services\PlatformSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class CmsSiteStructureTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): AdminUser
    {
        return AdminUser::query()->create([
            'nom' => 'Test',
            'prenom' => 'Cms',
            'email' => 'cms-structure@cama.bf',
            'password' => 'motdepasse-solide',
            'role' => AdminRole::Administrateur,
            'actif' => true,
        ]);
    }

    public function test_accessibility_page_no_longer_exists(): void
    {
        $this->get('/accessibilite')->assertNotFound();
        $this->assertSame(0, CmsPage::query()->where('slug', 'accessibilite')->count());
    }

    public function test_legal_page_is_structured_and_uses_footer_contact_details(): void
    {
        $this->get('/mention_legales')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Public/Legal')
                ->where('pageSections.0.columns.0.widgets.0.type', 'legal_page')
                ->has('pageSections.0.columns.0.widgets.0.content.articles', 3)
                ->where('pageSections.0.columns.0.widgets.0.content.articles.2.anchor', 'cookies')
                ->has('publicSite.footer.address'));
    }

    public function test_a_page_already_in_the_menu_cannot_be_added_twice(): void
    {
        $page = CmsPage::query()->create(['title' => 'Partenaires', 'slug' => 'partenaires-test', 'status' => 'published']);
        $menus = app(CmsMenuService::class);

        $menus->addPages([$page->id]);
        $menus->addPages([$page->id]);

        $this->assertSame(1, CmsMenuItem::query()->where('page_id', $page->id)->count());

        $listed = collect($menus->availablePages())->firstWhere('id', $page->id);
        $this->assertTrue($listed['inMenu']);
    }

    public function test_a_custom_link_to_a_page_counts_as_in_menu(): void
    {
        $page = CmsPage::query()->create(['title' => 'Guide', 'slug' => 'guide-test', 'status' => 'published']);
        app(CmsMenuService::class)->addCustomLink('Guide', '/guide-test');

        $this->assertContains($page->id, app(CmsMenuService::class)->pageIdsInMenu());
    }

    public function test_logo_can_be_replaced_and_restored(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.cms.identity.logo'), ['logo' => UploadedFile::fake()->image('logo.png', 256, 256)])
            ->assertSessionHasNoErrors();

        $logo = app(PlatformSettingsService::class)->siteLogo();
        $this->assertStringStartsWith('/media/branding/logo-', $logo);
        $this->assertFileExists(public_path(ltrim($logo, '/')));

        // Le logo est partagé avec toutes les pages.
        $this->get('/mention_legales')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('branding.logoUrl', $logo));

        $this->actingAs($admin, 'admin')->delete(route('admin.cms.identity.logo.reset'))->assertSessionHasNoErrors();

        $this->assertSame(PlatformSettingsService::DEFAULT_LOGO, app(PlatformSettingsService::class)->siteLogo());
        $this->assertFileDoesNotExist(public_path(ltrim($logo, '/')));
    }

    public function test_svg_logo_is_rejected(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.cms.identity.logo'), [
                'logo' => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
            ])
            ->assertSessionHasErrors('logo');

        $this->assertSame(PlatformSettingsService::DEFAULT_LOGO, app(PlatformSettingsService::class)->siteLogo());
    }
}
