<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\AdminUser;
use App\Models\CmsMedia;
use App\Models\CmsPage;
use App\Support\SiteUploads;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Les fichiers du site public ne sont pas rangés dans public/ (remplacé à chaque déploiement) mais
 * sur le stockage de Laravel, conservé entre deux déploiements.
 */
class SiteUploadsStorageTest extends TestCase
{
    use RefreshDatabase;

    private string $legacyRoot;

    protected function setUp(): void
    {
        parent::setUp();

        // Les anciens fichiers de ces tests vivent dans un dossier temporaire : jamais dans le vrai
        // public/ du projet (la commande de reprise sait supprimer les originaux).
        $this->legacyRoot = sys_get_temp_dir().DIRECTORY_SEPARATOR.'cama-legacy-'.uniqid();
        mkdir($this->legacyRoot, 0755, true);
        config(['filesystems.legacy_uploads_root' => $this->legacyRoot]);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->legacyRoot);

        parent::tearDown();
    }

    private function removeDirectory(string $directory): void
    {
        // Garde-fou : on ne supprime que le dossier temporaire créé par ce test.
        if (! str_starts_with($directory, sys_get_temp_dir().DIRECTORY_SEPARATOR.'cama-legacy-') || ! is_dir($directory)) {
            return;
        }

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($directory);
    }

    private function admin(): AdminUser
    {
        return AdminUser::query()->create([
            'nom' => 'Web', 'prenom' => 'Master', 'email' => 'web@cama.bf', 'password' => 'motdepasse-solide',
            'role' => AdminRole::Administrateur, 'actif' => true,
        ]);
    }

    /** Fichier déposé à l'ancien emplacement (public/…), comme avant le passage au stockage. */
    private function legacyFile(string $folder, string $name, string $content): string
    {
        $directory = $this->legacyRoot.DIRECTORY_SEPARATOR.$folder;
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($path = $directory.DIRECTORY_SEPARATOR.$name, $content);

        return $path;
    }

    public function test_new_uploads_are_stored_on_the_storage_disk_and_not_in_public(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.cms.media.store'), ['file' => UploadedFile::fake()->image('affiche.png')])
            ->assertSessionHasNoErrors();

        $media = CmsMedia::query()->firstOrFail();

        $this->assertMatchesRegularExpression('~^storage/site/media/cms/CMS_MEDIA_AFFICHE_\d{8}-\d{6}_[0-9A-F]{4}\.png$~', $media->disk_path);
        $this->assertSame('/'.$media->disk_path, $media->url);
        Storage::disk('public')->assertExists(substr($media->disk_path, strlen('storage/')));
        $this->assertFileDoesNotExist(public_path($media->disk_path));
        $this->assertFileDoesNotExist(public_path('media/cms/'.basename($media->disk_path)));

        // Suppression : le fichier disparaît du stockage.
        $this->actingAs($admin, 'admin')->delete(route('admin.cms.media.destroy', $media))->assertSessionHasNoErrors();
        Storage::disk('public')->assertMissing(substr($media->disk_path, strlen('storage/')));
    }

    public function test_stored_files_are_served_even_without_the_storage_symlink(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('site/images/cms/exemple.png', 'contenu-du-fichier');

        $response = $this->get('/storage/site/images/cms/exemple.png');

        $response->assertOk();
        $this->assertSame('contenu-du-fichier', $response->streamedContent());
    }

    public function test_site_uploads_helper_understands_new_and_legacy_references(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('site/images/cms/nouveau.png', 'x');
        $legacy = $this->legacyFile('images/cms', 'ancien-test-lot6.png', 'y');

        $this->assertTrue(SiteUploads::exists('storage/site/images/cms/nouveau.png'));
        $this->assertTrue(SiteUploads::exists('/storage/site/images/cms/nouveau.png'));
        $this->assertTrue(SiteUploads::exists('images/cms/ancien-test-lot6.png'));
        $this->assertFalse(SiteUploads::exists('images/cms/inconnu.png'));
        $this->assertSame('/storage/site/images/cms/nouveau.png', SiteUploads::url('storage/site/images/cms/nouveau.png'));
        $this->assertSame('https://cdn.example.test/a.png', SiteUploads::url('https://cdn.example.test/a.png'));

        // Un fichier d'origine du site (hors dossiers gérés) n'est jamais supprimé.
        $this->legacyFile('images', 'logo_origine.png', 'logo');
        SiteUploads::delete('images/logo_origine.png');
        $this->assertFileExists($this->legacyRoot.'/images/logo_origine.png');

        // Les deux sortes de références se suppriment.
        SiteUploads::delete('storage/site/images/cms/nouveau.png');
        SiteUploads::delete('images/cms/ancien-test-lot6.png');
        Storage::disk('public')->assertMissing('site/images/cms/nouveau.png');
        $this->assertFileDoesNotExist($legacy);

        // Pas de sortie du dossier géré.
        $this->assertNull(SiteUploads::legacyPath('images/cms/../../.env'));
        $this->assertNull(SiteUploads::diskPath('storage/../.env'));
    }

    public function test_command_moves_legacy_files_and_fixes_every_reference(): void
    {
        Storage::fake('public');
        $name = 'ancien-media-lot6.pdf';
        $this->legacyFile('media/cms', $name, '%PDF-1.4 ancien');

        $media = CmsMedia::query()->create([
            'disk_path' => "media/cms/{$name}", 'url' => "/media/cms/{$name}", 'name' => 'Ancien', 'original_name' => 'ancien.pdf',
            'title' => 'Ancien', 'folder' => 'general', 'mime_type' => 'application/pdf', 'extension' => 'pdf', 'size' => 12,
        ]);
        $page = CmsPage::query()->create([
            'title' => 'Page', 'slug' => 'page-lot6', 'status' => 'published',
            // JSON enregistré par Laravel : les « / » sont échappés.
            'sections_json' => [['type' => 'texte', 'content' => ['fichier' => "/media/cms/{$name}"]]],
        ]);
        $this->assertStringContainsString('media\\/cms\\/'.$name, (string) DB::table('cms_pages')->where('id', $page->id)->value('sections_json'));

        $this->artisan('uploads:move-to-storage')->assertSuccessful();

        $new = "storage/site/media/cms/{$name}";
        Storage::disk('public')->assertExists("site/media/cms/{$name}");
        $this->assertSame('%PDF-1.4 ancien', Storage::disk('public')->get("site/media/cms/{$name}"));
        $this->assertSame($new, $media->fresh()->disk_path);
        $this->assertSame("/{$new}", $media->fresh()->url);
        $this->assertSame("/{$new}", $page->fresh()->sections_json[0]['content']['fichier']);
        // Sans l'option, les originaux sont conservés.
        $this->assertFileExists($this->legacyRoot."/media/cms/{$name}");

        // Relance : rien n'est modifié une seconde fois.
        $this->artisan('uploads:move-to-storage')->assertSuccessful();
        $this->assertSame($new, $media->fresh()->disk_path);

        // Suppression des originaux sur demande.
        $this->artisan('uploads:move-to-storage', ['--delete-originals' => true])->assertSuccessful();
        $this->assertFileDoesNotExist($this->legacyRoot."/media/cms/{$name}");
        Storage::disk('public')->assertExists("site/media/cms/{$name}");
    }
}
