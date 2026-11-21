<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Enums\AssureStatut;
use App\Models\AdminUser;
use App\Models\Assure;
use App\Models\Dossier;
use App\Services\AdminAssureService;
use App\Services\DossierService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/** Tout fichier envoyé par un assuré se visualise et se télécharge, pour qui en a le droit. */
class FileAccessTest extends TestCase
{
    use RefreshDatabase;

    private const PDF = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";

    private function assure(string $email = 'assure@example.bf', string $path = 'assures/26AT1R001/ASS_26AT1R001_INSCR-CNIB_20261121-143005_7F3A.pdf'): Assure
    {
        Storage::disk('local')->put($path, self::PDF);

        $assure = Assure::query()->create([
            'nom' => 'Assure', 'prenom' => 'Test', 'matricule' => 'MAT-'.uniqid(), 'numero_cim' => 'CIM-1', 'numero_cama' => 'CAMA-1',
            'email' => $email, 'password' => 'mot-de-passe-solide', 'statut' => AssureStatut::Actif, 'journal' => [],
            'documents_identite' => [['key' => 'doc3', 'label' => 'CNIB', 'path' => $path, 'filename' => basename($path), 'mime' => 'application/pdf', 'size' => 70]],
        ]);
        $assure->forceFill(['email_verified_at' => now()])->save();

        return $assure;
    }

    private function admin(array $permissions, string $email = 'agent@cama.bf'): AdminUser
    {
        return AdminUser::query()->create([
            'nom' => 'Agent', 'prenom' => 'Test', 'email' => $email, 'password' => 'motdepasse-solide',
            'role' => AdminRole::Gestionnaire, 'actif' => true, 'permissions' => $permissions,
        ]);
    }

    private function switchTo($user, string $guard)
    {
        $this->flushSession();
        $this->app['auth']->forgetGuards();

        return $this->actingAs($user, $guard);
    }

    // ---------------------------------------------------------------- administration

    public function test_admin_can_view_and_download_registration_documents(): void
    {
        Storage::fake('local');
        $assure = $this->assure();
        $admin = $this->admin(['inscriptions.view']);
        $url = route('admin.inscriptions.document', ['assure' => $assure, 'key' => 'doc3']);

        // « Visualiser » : le PDF s'ouvre dans le navigateur.
        $view = $this->actingAs($admin, 'admin')->get($url.'?inline=1')->assertOk();
        $this->assertStringStartsWith('inline;', (string) $view->headers->get('content-disposition'));
        $this->assertSame('application/pdf', $view->headers->get('content-type'));
        $this->assertSame('nosniff', $view->headers->get('x-content-type-options'));

        // « Télécharger » : enregistré sous son nom normalisé.
        $download = $this->actingAs($admin, 'admin')->get($url)->assertOk();
        $this->assertStringStartsWith('attachment;', (string) $download->headers->get('content-disposition'));
        $this->assertStringContainsString('ASS_26AT1R001_INSCR-CNIB_20261121-143005_7F3A.pdf', (string) $download->headers->get('content-disposition'));
    }

    public function test_registration_documents_need_the_inscriptions_permission(): void
    {
        Storage::fake('local');
        $assure = $this->assure();

        $this->actingAs($this->admin(['cms.pages']), 'admin')
            ->get(route('admin.inscriptions.document', ['assure' => $assure, 'key' => 'doc3']).'?inline=1')
            ->assertForbidden();
    }

    public function test_admin_can_view_and_download_member_pieces(): void
    {
        Storage::fake('local');
        $assure = $this->assure();
        Storage::disk('local')->put('assures/26AT1R001/ASS_26AT1R001-ENF01_ACTE-NAISSANCE_20261121-143200_B2D4.pdf', self::PDF);
        $dossier = Dossier::query()->create([
            'assure_id' => $assure->id, 'ref' => 'REF-F1', 'nom' => 'Enfant', 'prenom' => 'Un', 'lien' => 'Enfant biologique',
            'statut' => 'Soumis', 'journal' => [], 'gestionnaire' => 'Agent',
            'pieces' => [['type' => 'Acte de naissance', 'path' => 'assures/26AT1R001/ASS_26AT1R001-ENF01_ACTE-NAISSANCE_20261121-143200_B2D4.pdf',
                'filename' => 'ASS_26AT1R001-ENF01_ACTE-NAISSANCE_20261121-143200_B2D4.pdf']],
        ]);
        $admin = $this->admin(['dossiers.view', 'dossiers.view_all']);
        $url = route('admin.dossiers.piece.download', ['dossier' => $dossier, 'type' => 'Acte de naissance']);

        $view = $this->actingAs($admin, 'admin')->get($url.'&inline=1')->assertOk();
        $this->assertStringStartsWith('inline;', (string) $view->headers->get('content-disposition'));

        $download = $this->actingAs($admin, 'admin')->get($url)->assertOk();
        $this->assertStringStartsWith('attachment;', (string) $download->headers->get('content-disposition'));
    }

    public function test_the_assures_screen_lists_every_file_without_internal_paths(): void
    {
        Storage::fake('local');
        $assure = $this->assure();
        Dossier::query()->create([
            'assure_id' => $assure->id, 'ref' => 'REF-F2', 'nom' => 'Enfant', 'prenom' => 'Deux', 'lien' => 'Enfant biologique',
            'statut' => 'Soumis', 'journal' => [],
            'pieces' => [['type' => 'Acte de naissance', 'path' => 'assures/26AT1R001/piece.pdf', 'filename' => 'piece.pdf']],
        ]);
        $admin = $this->admin(['assures.view', 'inscriptions.view', 'dossiers.view']);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.assures'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('assures.0.documents.0.key', 'doc3')
                ->where('assures.0.documents.0.label', 'CNIB')
                ->where('assures.0.membresExport.0.pieces.0.hasFile', true));

        // Le chemin interne des fichiers n'est jamais transmis au navigateur.
        $this->assertStringNotContainsString('assures\/26AT1R001', $response->getContent());
        $this->assertStringNotContainsString('assures/26AT1R001', $response->getContent());
    }

    public function test_no_internal_path_in_member_or_profile_payloads(): void
    {
        Storage::fake('local');
        $assure = $this->assure();
        $dossier = Dossier::query()->create([
            'assure_id' => $assure->id, 'ref' => 'REF-F3', 'nom' => 'Enfant', 'prenom' => 'Trois', 'lien' => 'Enfant biologique',
            'statut' => 'Soumis', 'journal' => [], 'pieces' => [['type' => 'CNIB', 'path' => 'assures/x/secret.pdf', 'filename' => 'secret.pdf']],
        ]);

        $membre = app(DossierService::class)->formatMembre($dossier);
        $profil = app(AdminAssureService::class)->profileForAdmin($assure);

        $this->assertArrayNotHasKey('path', $membre['pieces'][0]);
        $this->assertTrue($membre['pieces'][0]['hasFile']);
        $this->assertArrayNotHasKey('path', $profil['documentsIdentite'][0]);
        $this->assertStringNotContainsString('assures/', json_encode([$membre, $profil]));
    }

    // ---------------------------------------------------------------- assuré

    public function test_assure_can_view_and_download_his_own_registration_documents(): void
    {
        Storage::fake('local');
        $assure = $this->assure();
        $url = route('assure.profil.document', 'doc3');

        $view = $this->actingAs($assure, 'assure')->get($url.'?inline=1')->assertOk();
        $this->assertStringStartsWith('inline;', (string) $view->headers->get('content-disposition'));

        $download = $this->actingAs($assure, 'assure')->get($url)->assertOk();
        $this->assertStringStartsWith('attachment;', (string) $download->headers->get('content-disposition'));

        $this->actingAs($assure, 'assure')->get(route('assure.profil'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('profil.documents.0.label', 'CNIB')
                ->missing('profil.documents.0.path'));
    }

    public function test_an_assure_never_reaches_the_documents_of_another(): void
    {
        Storage::fake('local');
        $owner = $this->assure('proprietaire@example.bf', 'assures/26AT1R001/proprietaire.pdf');
        $other = $this->assure('autre@example.bf', 'assures/26AT1R002/autre.pdf');
        $other->update(['documents_identite' => []]);

        // « doc3 » existe chez le propriétaire, pas chez l'autre assuré : il n'obtient rien.
        $this->switchTo($other->fresh(), 'assure')->get(route('assure.profil.document', 'doc3').'?inline=1')->assertNotFound();
        $this->assertNotNull($owner->fresh());

        // Sans connexion : renvoyé vers la connexion.
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->get(route('assure.profil.document', 'doc3'))->assertRedirect();
    }

    public function test_non_viewable_files_are_always_downloaded_not_displayed(): void
    {
        Storage::fake('local');
        $assure = $this->assure('texte@example.bf', 'assures/26AT1R003/note.txt');
        Storage::disk('local')->put('assures/26AT1R003/note.txt', '<script>alert(1)</script>');

        $response = $this->actingAs($assure, 'assure')->get(route('assure.profil.document', 'doc3').'?inline=1')->assertOk();

        $this->assertStringStartsWith('attachment;', (string) $response->headers->get('content-disposition'));
        $this->assertSame('application/octet-stream', $response->headers->get('content-type'));
    }
}
