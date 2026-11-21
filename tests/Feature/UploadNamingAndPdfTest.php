<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Enums\AssureStatut;
use App\Models\AdminUser;
use App\Models\Assure;
use App\Rules\IdentityPhoto;
use App\Rules\MemberPieceFile;
use App\Rules\PdfDocument;
use App\Services\DossierPieceStorageService;
use App\Services\PlatformSettingsService;
use App\Support\UploadFilename;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class UploadNamingAndPdfTest extends TestCase
{
    use RefreshDatabase;

    /** PNG valide de 1 pixel. */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    /** @var list<string> */
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            @unlink($path);
        }

        parent::tearDown();
    }

    /** Vrai fichier temporaire : le type est déduit du contenu, comme pour un envoi réel. */
    private function file(string $name, string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'up');
        file_put_contents($path, $content);
        $this->temporaryFiles[] = $path;

        return new UploadedFile($path, $name, null, null, true);
    }

    private function pdf(string $name = 'cnib.pdf', string $extra = ''): UploadedFile
    {
        return $this->file($name, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n{$extra}trailer\n<< /Root 1 0 R >>\n%%EOF\n");
    }

    private function png(string $name = 'photo.png'): UploadedFile
    {
        return $this->file($name, base64_decode(self::PNG));
    }

    private function passes(object $rule, UploadedFile $file, string $attribute = 'fichier'): bool
    {
        // Données imbriquées, comme dans un vrai formulaire (« conjoints.0.piece_files.photo_membre »).
        $data = [];
        data_set($data, $attribute, $file);

        return Validator::make($data, [$attribute => [$rule]])->passes();
    }

    private function assure(array $attributes = []): Assure
    {
        return Assure::query()->create(array_merge([
            'nom' => 'Ouedraogo', 'prenom' => 'Awa', 'matricule' => 'MAT-'.uniqid(), 'numero_cim' => 'CIM-1',
            'numero_cama' => 'CAMA-1', 'email' => 'awa@example.bf', 'password' => 'mot-de-passe-solide',
            'armee' => 'Armée de Terre', 'region' => '1re Région Militaire',
            'statut' => AssureStatut::Actif, 'journal' => [],
        ], $attributes))->fresh();
    }

    // ---------------------------------------------------------------- nomenclature

    public function test_file_names_follow_the_domain_owner_type_date_random_format(): void
    {
        $name = UploadFilename::make('ASS', '26AT1R001-'.UploadFilename::memberCode(false, 1), 'Acte de naissance', 'PDF');

        $this->assertMatchesRegularExpression('/^ASS_26AT1R001-ENF01_ACTE-NAISSANCE_\d{8}-\d{6}_[0-9A-F]{4}\.pdf$/', $name);
        $this->assertSame('CONJ02', UploadFilename::memberCode(true, 2));

        // Pas d'accent, d'espace ni de symbole ; libellés longs coupés entre deux mots.
        $this->assertSame('RENTREE-SCOLAIRE-ENFANTS-OFFICIERS', UploadFilename::segment("Rentrée scolaire des enfants d'officiers", 40));
        $this->assertSame('RENTREE-SCOLAIRE', UploadFilename::label('Rentrée scolaire des enfants d\'officiers', 20));
        $this->assertSame('X', UploadFilename::segment('???'));
    }

    public function test_two_files_never_share_a_name(): void
    {
        $names = array_map(fn () => UploadFilename::make('ASS', '26AT1R001', 'FIF', 'pdf'), range(1, 300));

        $this->assertGreaterThan(290, count(array_unique($names)));

        $taken = [];
        for ($i = 0; $i < 50; $i++) {
            $taken[] = UploadFilename::makeUnique(fn (string $n) => in_array($n, $taken, true), 'ASS', '26AT1R001', 'FIF', 'pdf');
        }
        $this->assertCount(50, array_unique($taken));
    }

    // ---------------------------------------------------------------- règle PDF

    public function test_a_real_pdf_is_accepted(): void
    {
        $this->assertTrue($this->passes(new PdfDocument, $this->pdf()));
    }

    public function test_anything_that_is_not_a_real_pdf_is_refused(): void
    {
        $rule = new PdfDocument;

        // Image déguisée en PDF.
        $this->assertFalse($this->passes($rule, $this->file('cnib.pdf', base64_decode(self::PNG))));
        // Texte (ou page web) nommé .pdf : pas de signature « %PDF- ».
        $this->assertFalse($this->passes($rule, $this->file('cnib.pdf', '<html><script>alert(1)</script></html>')));
        // Vrai PDF mais mauvaise extension.
        $this->assertFalse($this->passes($rule, $this->pdf('cnib.png')));
        $this->assertFalse($this->passes($rule, $this->pdf('cnib.php')));
        // Image envoyée telle quelle.
        $this->assertFalse($this->passes($rule, $this->png('cnib.png')));
    }

    public function test_pdf_with_active_content_is_refused(): void
    {
        $rule = new PdfDocument;

        foreach (['/JavaScript (app.alert(1))', '/JS (app.alert(1))', '/Launch << /F (cmd.exe) >>', '/EmbeddedFile'] as $active) {
            $this->assertFalse($this->passes($rule, $this->pdf('piece.pdf', "<< /S {$active} >>\n")), "{$active} doit être refusé.");
        }

        // Un nom qui ressemble sans être une action (« /JSON ») n'est pas bloqué.
        $this->assertTrue($this->passes($rule, $this->pdf('piece.pdf', "<< /Title /JSONData >>\n")));
    }

    public function test_pdf_over_the_size_limit_is_refused(): void
    {
        $big = $this->file('gros.pdf', "%PDF-1.4\n".str_repeat('0', 6 * 1024 * 1024));

        $this->assertFalse($this->passes(new PdfDocument, $big));
        $this->assertTrue($this->passes(new PdfDocument(8192), $big));
    }

    public function test_member_photo_is_the_only_piece_that_stays_an_image(): void
    {
        $rule = new MemberPieceFile;

        $this->assertTrue($this->passes($rule, $this->png(), 'conjoints.0.piece_files.photo_membre'));
        $this->assertFalse($this->passes($rule, $this->pdf(), 'conjoints.0.piece_files.photo_membre'));
        // Autres pièces : PDF seulement.
        $this->assertTrue($this->passes($rule, $this->pdf(), 'enfants.1.piece_files.acte_naissance'));
        $this->assertFalse($this->passes($rule, $this->png(), 'enfants.1.piece_files.acte_naissance'));
    }

    public function test_photo_rule_checks_the_real_image_not_the_file_name(): void
    {
        $rule = new IdentityPhoto;

        $this->assertTrue($this->passes($rule, $this->png('photo.png')));
        $this->assertFalse($this->passes($rule, $this->file('photo.png', '%PDF-1.4 pas une image')));
        $this->assertFalse($this->passes($rule, $this->file('photo.jpg', '<?php echo 1;')));
    }

    // ---------------------------------------------------------------- stockage

    public function test_member_pieces_are_stored_privately_under_the_fif_number_with_a_normalized_name(): void
    {
        Storage::fake('local');
        $assure = $this->assure();
        $storage = app(DossierPieceStorageService::class);

        $piece = $storage->storeMemberPiece($assure, $this->pdf('Scan 001 (1).pdf'), 'acte_naissance', 'Acte de naissance', 'ENF01');

        $this->assertStringStartsWith("assures/{$assure->numero_fif}/", $piece['path']);
        $this->assertMatchesRegularExpression('/^ASS_'.$assure->numero_fif.'-ENF01_ACTE-NAISSANCE_\d{8}-\d{6}_[0-9A-F]{4}\.pdf$/', $piece['filename']);
        $this->assertSame($piece['filename'], basename($piece['path']));
        $this->assertSame('Scan 001 (1).pdf', $piece['original_name']);
        $this->assertSame('application/pdf', $piece['mime']);
        Storage::disk('local')->assertExists($piece['path']);

        $photo = $storage->storeMemberPiece($assure, $this->png('IMG_2031.PNG'), 'photo_membre', 'Photo du membre', 'CONJ01');
        $this->assertMatchesRegularExpression('/^ASS_'.$assure->numero_fif.'-CONJ01_PHOTO-MEMBRE_\d{8}-\d{6}_[0-9A-F]{4}\.png$/', $photo['filename']);
        $this->assertSame('image/png', $photo['mime']);
    }

    public function test_a_piece_that_is_not_a_pdf_is_never_stored(): void
    {
        Storage::fake('local');
        $storage = app(DossierPieceStorageService::class);
        $assure = $this->assure();

        foreach ([$this->png('cnib.png'), $this->file('cnib.pdf', 'pas un pdf'), $this->pdf('cnib.pdf', '/JavaScript (x)')] as $file) {
            try {
                $storage->storeMemberPiece($assure, $file, 'cnib_parent', 'CNIB du parent', 'ENF01');
                $this->fail('Une pièce invalide ne doit pas être enregistrée.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('piece.cnib_parent', $e->errors());
            }
        }

        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_signed_fif_must_be_a_pdf(): void
    {
        Storage::fake('local');
        $storage = app(DossierPieceStorageService::class);
        $assure = $this->assure();

        $piece = $storage->storeLotPiece($assure, $this->pdf('fif-signee.pdf'), 'FIF signée');
        $this->assertMatchesRegularExpression('/^ASS_'.$assure->numero_fif.'_FIF-SIGNEE_\d{8}-\d{6}_[0-9A-F]{4}\.pdf$/', $piece['filename']);
        $this->assertTrue($piece['lot']);

        $this->expectException(ValidationException::class);
        $storage->storeLotPiece($assure, $this->png('fif.png'), 'FIF signée');
    }

    // ---------------------------------------------------------------- inscription et téléchargement

    private function registrationPayload(array $documents): array
    {
        $org = app(PlatformSettingsService::class)->orgStructure();

        return [
            'nom' => 'Ouedraogo', 'prenom' => 'Awa', 'sexe' => 'Féminin', 'matricule' => 'MAT-001',
            'grade' => $org['grades'][0], 'categorie' => $org['categories'][0],
            'numero_informatique' => 'INF-1', 'numero_cim' => 'CIM-1', 'numero_cama' => 'CAMA-1',
            'armee' => $org['armees'][0], 'region' => 'Région inconnue',
            'telephone' => '+22670000000', 'email' => 'awa@example.bf',
            'password' => 'Motdepasse#2026', 'password_confirmation' => 'Motdepasse#2026', 'cgu' => true,
            'documents' => $documents,
        ];
    }

    public function test_registration_accepts_only_pdf_documents_and_names_them_after_the_fif_number(): void
    {
        Storage::fake('local');
        app(PlatformSettingsService::class)->updateInscriptionDocuments([
            ['key' => 'doc1', 'actif' => true, 'titre' => 'Carte militaire'],
        ]);

        // Image : refusée, rien n'est créé ni stocké.
        $this->post(route('registration.store'), $this->registrationPayload(['doc1' => $this->png('carte.png')]))
            ->assertSessionHasErrors('documents.doc1');
        $this->assertSame(0, Assure::query()->count());
        $this->assertSame([], Storage::disk('local')->allFiles());

        // PDF : accepté.
        $this->post(route('registration.store'), $this->registrationPayload(['doc1' => $this->pdf('Carte militaire.pdf')]))
            ->assertSessionHasNoErrors();

        $assure = Assure::query()->firstOrFail();
        $document = $assure->documents_identite[0];

        $this->assertNotEmpty($assure->numero_fif);
        $this->assertStringStartsWith("assures/{$assure->numero_fif}/", $document['path']);
        $this->assertMatchesRegularExpression('/^ASS_'.$assure->numero_fif.'_INSCR-CARTE-MILITAIRE_\d{8}-\d{6}_[0-9A-F]{4}\.pdf$/', $document['filename']);
        Storage::disk('local')->assertExists($document['path']);

        // Téléchargement par l'administration : sous le nom normalisé, jamais affiché dans le navigateur.
        $admin = AdminUser::query()->create([
            'nom' => 'Agent', 'prenom' => 'Test', 'email' => 'agent@cama.bf', 'password' => 'motdepasse-solide',
            'role' => AdminRole::Gestionnaire, 'actif' => true, 'permissions' => ['inscriptions.view'],
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.inscriptions.document', ['assure' => $assure, 'key' => 'doc1']))
            ->assertOk();

        $this->assertStringContainsString($document['filename'], (string) $response->headers->get('content-disposition'));
        $this->assertStringStartsWith('attachment;', (string) $response->headers->get('content-disposition'));
        $this->assertSame('nosniff', $response->headers->get('x-content-type-options'));
    }

    public function test_a_document_path_outside_the_private_pieces_space_is_never_served(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('autre/secret.txt', 'secret');
        $assure = $this->assure(['documents_identite' => [['key' => 'cnib', 'label' => 'CNIB', 'path' => 'autre/secret.txt', 'filename' => 'x.pdf']]]);
        $admin = AdminUser::query()->create([
            'nom' => 'Agent', 'prenom' => 'Test', 'email' => 'agent2@cama.bf', 'password' => 'motdepasse-solide',
            'role' => AdminRole::Gestionnaire, 'actif' => true, 'permissions' => ['inscriptions.view'],
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.inscriptions.document', ['assure' => $assure, 'key' => 'cnib']))
            ->assertNotFound();
    }

    // ---------------------------------------------------------------- site public

    public function test_site_uploads_use_the_same_naming(): void
    {
        $admin = AdminUser::query()->create([
            'nom' => 'Web', 'prenom' => 'Master', 'email' => 'web@cama.bf', 'password' => 'motdepasse-solide',
            'role' => AdminRole::Administrateur, 'actif' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.cms.media.store'), ['file' => $this->png('Rapport annuel 2026.png')])
            ->assertSessionHasNoErrors();

        $media = \App\Models\CmsMedia::query()->firstOrFail();
        $this->assertMatchesRegularExpression('/^CMS_MEDIA_RAPPORT-ANNUEL-2026_\d{8}-\d{6}_[0-9A-F]{4}\.png$/', basename($media->disk_path));
        $this->assertSame('Rapport annuel 2026.png', $media->original_name);
        @unlink(public_path($media->disk_path));
    }
}
