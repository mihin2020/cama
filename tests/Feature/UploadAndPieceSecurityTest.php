<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Enums\AssureStatut;
use App\Models\AdminUser;
use App\Models\Assure;
use App\Models\CmsMedia;
use App\Models\Dossier;
use App\Support\SafeUpload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class UploadAndPieceSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function admin(array $permissions, string $email = 'agent@cama.bf'): AdminUser
    {
        return AdminUser::query()->create([
            'nom' => 'Agent', 'prenom' => 'Test', 'email' => $email, 'password' => 'motdepasse-solide',
            'role' => AdminRole::Gestionnaire, 'actif' => true, 'permissions' => $permissions,
        ]);
    }

    private function dossierWithPiece(): Dossier
    {
        Storage::fake('local');
        Storage::disk('local')->put('dossiers/cnib.pdf', "%PDF-1.4\n%test\n");

        $assure = Assure::query()->create([
            'nom' => 'Assure', 'prenom' => 'Test', 'matricule' => 'MAT-1', 'numero_cim' => 'CIM-1', 'numero_cama' => 'CAMA-1',
            'email' => 'assure@example.bf', 'password' => 'password', 'statut' => AssureStatut::Actif, 'journal' => [],
        ]);

        return Dossier::query()->create([
            'assure_id' => $assure->id, 'ref' => 'REF-1', 'nom' => 'Membre', 'prenom' => 'Test', 'lien' => 'Enfant',
            'statut' => 'Soumis', 'journal' => [],
            'pieces' => [['type' => 'CNIB', 'path' => 'dossiers/cnib.pdf', 'filename' => 'cnib "test".pdf']],
        ]);
    }

    public function test_extension_comes_from_the_real_content_not_the_file_name(): void
    {
        // Vrais fichiers temporaires : les faux fichiers de test devinent leur type d'après le nom.
        $png = tempnam(sys_get_temp_dir(), 'img');
        imagepng(imagecreatetruecolor(4, 4), $png);
        $this->assertSame('png', SafeUpload::extension(new UploadedFile($png, 'photo.gif', null, null, true), SafeUpload::IMAGES));

        $page = tempnam(sys_get_temp_dir(), 'page');
        file_put_contents($page, '<!doctype html><html><body><h1>pas une image</h1></body></html>');

        try {
            $this->expectException(ValidationException::class);
            SafeUpload::extension(new UploadedFile($page, 'photo.png', 'image/png', null, true), SafeUpload::IMAGES);
        } finally {
            @unlink($png);
            @unlink($page);
        }
    }

    public function test_media_library_refuses_web_pages_and_keeps_only_safe_extensions(): void
    {
        $admin = $this->admin(['cms.media']);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.cms.media.store'), ['file' => UploadedFile::fake()->createWithContent('page.html', '<h1>test</h1>')])
            ->assertSessionHasErrors('file');
        $this->assertSame(0, CmsMedia::query()->count());

        $this->actingAs($admin, 'admin')
            ->post(route('admin.cms.media.store'), ['file' => UploadedFile::fake()->image('photo.png')])
            ->assertSessionHasNoErrors();

        $media = CmsMedia::query()->firstOrFail();
        $this->assertSame('png', $media->extension);
        @unlink(public_path($media->disk_path));
    }

    public function test_dossier_pieces_require_the_dossier_permission(): void
    {
        $dossier = $this->dossierWithPiece();
        $url = route('admin.dossiers.piece.download', ['dossier' => $dossier, 'type' => 'CNIB']);

        // Compte réservé au site : pas d'accès aux pièces d'identité.
        $this->actingAs($this->admin(['cms.pages']), 'admin')->get($url)->assertForbidden();

        // Changement de compte : nouvelle session (sinon la protection de session déconnecte le second compte).
        $this->flushSession();
        $this->app['auth']->forgetGuards();

        $response = $this->actingAs($this->admin(['dossiers.view'], 'gestion@cama.bf'), 'admin')->get($url)->assertOk();
        $this->assertStringStartsWith('attachment;', $response->headers->get('content-disposition'));
        $this->assertSame('nosniff', $response->headers->get('x-content-type-options'));
    }

    public function test_assure_only_reaches_pieces_of_his_own_dossiers(): void
    {
        $dossier = $this->dossierWithPiece();
        $other = Assure::query()->create([
            'nom' => 'Autre', 'prenom' => 'Assure', 'matricule' => 'MAT-2', 'numero_cim' => 'CIM-2', 'numero_cama' => 'CAMA-2',
            'email' => 'autre@example.bf', 'password' => 'password', 'statut' => AssureStatut::Actif, 'journal' => [],
        ]);
        $other->forceFill(['email_verified_at' => now()])->save();

        $this->actingAs($other, 'assure')
            ->get(route('assure.dossiers.piece.download', ['dossier' => $dossier, 'type' => 'CNIB']))
            ->assertForbidden();
    }
}
