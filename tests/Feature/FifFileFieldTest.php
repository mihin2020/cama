<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Enums\AssureStatut;
use App\Models\AdminUser;
use App\Models\Assure;
use App\Models\FifField;
use App\Models\FifSection;
use App\Services\FifSchemaService;
use App\Services\PlatformSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/** Champ « fichier à charger » du formulaire FIF configurable. */
class FifFileFieldTest extends TestCase
{
    use RefreshDatabase;

    private const PDF = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";

    /** @var list<string> */
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            @unlink($path);
        }

        parent::tearDown();
    }

    private function upload(string $name, string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'up');
        file_put_contents($path, $content);
        $this->temporaryFiles[] = $path;

        return new UploadedFile($path, $name, null, null, true);
    }

    private function pdf(string $name = 'carte.pdf'): UploadedFile
    {
        return $this->upload($name, self::PDF);
    }

    private function fileField(string $cible, string $label = 'Carte de groupe sanguin', bool $required = false): FifField
    {
        $section = app(FifSchemaService::class)->createSection($cible, ['titre' => 'Documents complémentaires']);

        return app(FifSchemaService::class)->createField($section, ['libelle' => $label, 'type' => 'fichier', 'obligatoire' => $required]);
    }

    private function registration(array $overrides = []): array
    {
        $org = app(PlatformSettingsService::class)->orgStructure();

        return array_merge([
            'nom' => 'Ouedraogo', 'prenom' => 'Awa', 'sexe' => 'Féminin', 'matricule' => 'MAT-001',
            'grade' => $org['grades'][0], 'categorie' => $org['categories'][0],
            'numero_informatique' => 'INF-1', 'numero_cim' => 'CIM-1', 'numero_cama' => 'CAMA-1',
            'armee' => $org['armees'][0], 'region' => 'Région inconnue',
            'telephone' => '+22670000000', 'email' => 'awa@example.bf',
            'password' => 'Motdepasse#2026', 'password_confirmation' => 'Motdepasse#2026', 'cgu' => true,
        ], $overrides);
    }

    private function activeAssure(): Assure
    {
        $assure = Assure::query()->create([
            'nom' => 'Assure', 'prenom' => 'Actif', 'matricule' => 'MAT-A1', 'numero_cim' => 'CIM-A1', 'numero_cama' => 'CAMA-A1',
            'email' => 'actif@example.bf', 'password' => 'mot-de-passe-solide', 'statut' => AssureStatut::Actif,
            'journal' => [], 'armee' => 'Armée de Terre', 'region' => '1re Région Militaire',
        ]);
        $assure->forceFill(['email_verified_at' => now()])->save();

        return $assure;
    }

    private function admin(array $permissions): AdminUser
    {
        return AdminUser::query()->create([
            'nom' => 'Agent', 'prenom' => 'Test', 'email' => 'agent@cama.bf', 'password' => 'motdepasse-solide',
            'role' => AdminRole::Gestionnaire, 'actif' => true, 'permissions' => $permissions,
        ]);
    }

    public function test_the_builder_offers_the_file_type(): void
    {
        $admin = AdminUser::query()->create([
            'nom' => 'Admin', 'prenom' => 'Fif', 'email' => 'fif@cama.bf', 'password' => 'motdepasse-solide',
            'role' => AdminRole::Administrateur, 'actif' => true,
        ]);

        $this->actingAs($admin, 'admin')->get(route('admin.fif'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('types', fn ($types) => collect($types)->contains(fn ($t) => $t['value'] === 'fichier' && $t['label'] === 'Fichier à charger (PDF)')));
    }

    public function test_registration_takes_a_pdf_for_the_file_field_and_names_it_after_the_fif_number(): void
    {
        Storage::fake('local');
        $field = $this->fileField('assure', 'Carte de groupe sanguin', true);

        // Obligatoire : refusé sans fichier, refusé si ce n'est pas un vrai PDF.
        $this->post(route('registration.store'), $this->registration())->assertSessionHasErrors("fif.{$field->code}");
        $this->post(route('registration.store'), $this->registration(['fif' => [$field->code => $this->upload('carte.pdf', 'pas un pdf')]]))
            ->assertSessionHasErrors("fif.{$field->code}");
        $this->assertSame(0, Assure::query()->count());

        $this->post(route('registration.store'), $this->registration(['fif' => [$field->code => $this->pdf()]]))->assertSessionHasNoErrors();

        $assure = Assure::query()->firstOrFail();
        $meta = $assure->fif_donnees[$field->code];

        $this->assertStringStartsWith("assures/{$assure->numero_fif}/", $meta['path']);
        $this->assertMatchesRegularExpression('/^ASS_'.$assure->numero_fif.'_CARTE-GROUPE-SANGUIN_\d{8}-\d{6}_[0-9A-F]{4}\.pdf$/', $meta['filename']);
        $this->assertSame('carte.pdf', $meta['original_name']);
        Storage::disk('local')->assertExists($meta['path']);
    }

    public function test_admin_views_and_downloads_the_file_with_the_right_permission(): void
    {
        Storage::fake('local');
        $field = $this->fileField('assure');
        $this->post(route('registration.store'), $this->registration(['fif' => [$field->code => $this->pdf()]]))->assertSessionHasNoErrors();
        $assure = Assure::query()->firstOrFail();
        auth('assure')->logout();
        $url = route('admin.fif.file', ['scope' => 'assure', 'id' => $assure->id, 'code' => $field->code]);

        $this->flushSession();
        $admin = $this->admin(['inscriptions.view']);
        $view = $this->actingAs($admin, 'admin')->get($url.'?inline=1')->assertOk();
        $this->assertStringStartsWith('inline;', (string) $view->headers->get('content-disposition'));
        $this->assertSame('application/pdf', $view->headers->get('content-type'));

        $download = $this->actingAs($admin, 'admin')->get($url)->assertOk();
        $this->assertStringStartsWith('attachment;', (string) $download->headers->get('content-disposition'));

        // Sans droit sur les assurés : refusé.
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->actingAs(AdminUser::query()->create([
            'nom' => 'Web', 'prenom' => 'Master', 'email' => 'web@cama.bf', 'password' => 'motdepasse-solide',
            'role' => AdminRole::Gestionnaire, 'actif' => true, 'permissions' => ['cms.pages'],
        ]), 'admin')->get($url)->assertForbidden();
    }

    public function test_admin_screens_show_that_a_file_exists_without_leaking_its_path(): void
    {
        Storage::fake('local');
        $field = $this->fileField('assure');
        $this->post(route('registration.store'), $this->registration(['fif' => [$field->code => $this->pdf()]]))->assertSessionHasNoErrors();
        auth('assure')->logout();

        $this->flushSession();
        $response = $this->actingAs($this->admin(['inscriptions.view']), 'admin')->get(route('admin.inscriptions'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('registrations.0.fifSections.0.rows.0.fichier', true)
                ->where('registrations.0.fifSections.0.rows.0.value', 'carte.pdf'));

        $this->assertStringNotContainsString('assures/', $response->getContent());
    }

    public function test_member_files_are_kept_replaced_and_required_at_submission(): void
    {
        Storage::fake('local');
        $field = $this->fileField('conjoint', 'Certificat de mariage', true);
        $assure = $this->activeAssure();
        $member = ['nom' => 'Sawadogo', 'prenom' => 'Awa', 'sexe' => 'Féminin', 'date_naissance' => '1990-01-02', 'lien' => 'Conjoint(e)'];

        // Brouillon avec le fichier : rangé sous le code du conjoint (CONJ01).
        $this->actingAs($assure, 'assure')->post(route('assure.dossiers.draft'), ['conjoints' => [
            [...$member, 'fif' => [$field->code => $this->pdf('mariage.pdf')]],
        ]])->assertSessionHasNoErrors();

        $dossier = $assure->dossiers()->firstOrFail();
        $first = $dossier->fif_donnees[$field->code];
        $this->assertMatchesRegularExpression('/-CONJ01_CERTIFICAT-MARIAGE_\d{8}-\d{6}_[0-9A-F]{4}\.pdf$/', $first['filename']);
        Storage::disk('local')->assertExists($first['path']);

        // Nouvel enregistrement sans fichier : l'ancien est conservé.
        $this->actingAs($assure, 'assure')->post(route('assure.dossiers.draft'), ['conjoints' => [
            [...$member, 'id' => $dossier->id, 'fif' => []],
        ]])->assertSessionHasNoErrors();
        $this->assertSame($first['path'], $dossier->fresh()->fif_donnees[$field->code]['path']);

        // Soumission : le fichier déjà enregistré suffit pour le champ obligatoire.
        $this->actingAs($assure, 'assure')->post(route('assure.dossiers.submit'), ['conjoints' => [
            [...$member, 'id' => $dossier->id, 'fif' => []],
        ]])->assertSessionDoesntHaveErrors('conjoints.0.fif');

        // Un nouveau fichier remplace l'ancien, qui est supprimé.
        $this->actingAs($assure, 'assure')->post(route('assure.dossiers.draft'), ['conjoints' => [
            [...$member, 'id' => $dossier->id, 'fif' => [$field->code => $this->pdf('nouveau.pdf')]],
        ]])->assertSessionHasNoErrors();
        $second = $dossier->fresh()->fif_donnees[$field->code];
        $this->assertNotSame($first['path'], $second['path']);
        Storage::disk('local')->assertMissing($first['path']);
        Storage::disk('local')->assertExists($second['path']);
    }

    public function test_submission_requires_the_mandatory_file_of_a_new_member(): void
    {
        Storage::fake('local');
        $this->fileField('enfant', 'Carnet de vaccination', true);
        $assure = $this->activeAssure();

        $this->actingAs($assure, 'assure')->post(route('assure.dossiers.submit'), ['enfants' => [[
            'nom' => 'Kaboré', 'prenom' => 'Issa', 'sexe' => 'Masculin', 'date_naissance' => '2018-03-04', 'lien' => 'Enfant biologique',
        ]]])->assertSessionHasErrors(['enfants.0.fif' => 'Champ(s) obligatoire(s) manquant(s) : Carnet de vaccination.']);
    }

    public function test_a_member_file_that_is_not_a_pdf_is_refused(): void
    {
        Storage::fake('local');
        $field = $this->fileField('enfant');
        $assure = $this->activeAssure();

        $this->actingAs($assure, 'assure')->post(route('assure.dossiers.draft'), ['enfants' => [[
            'nom' => 'Kaboré', 'prenom' => 'Issa', 'sexe' => 'Masculin', 'date_naissance' => '2018-03-04', 'lien' => 'Enfant biologique',
            'fif' => [$field->code => $this->upload('photo.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='))],
        ]]])->assertSessionHasErrors("enfants.0.fif.{$field->code}");

        $this->assertSame(0, $assure->dossiers()->count());
    }

    public function test_an_assure_only_reaches_his_own_fif_files(): void
    {
        Storage::fake('local');
        $field = $this->fileField('conjoint');
        $owner = $this->activeAssure();
        $this->actingAs($owner, 'assure')->post(route('assure.dossiers.draft'), ['conjoints' => [[
            'nom' => 'Sawadogo', 'prenom' => 'Awa', 'sexe' => 'Féminin', 'date_naissance' => '1990-01-02', 'lien' => 'Conjoint(e)',
            'fif' => [$field->code => $this->pdf()],
        ]]])->assertSessionHasNoErrors();
        $dossier = $owner->dossiers()->firstOrFail();
        $url = route('assure.fif.file', ['scope' => 'dossier', 'id' => $dossier->id, 'code' => $field->code]);

        $this->actingAs($owner, 'assure')->get($url.'?inline=1')->assertOk();

        $other = Assure::query()->create([
            'nom' => 'Autre', 'prenom' => 'Assure', 'matricule' => 'MAT-B2', 'numero_cim' => 'CIM-B2', 'numero_cama' => 'CAMA-B2',
            'email' => 'autre@example.bf', 'password' => 'mot-de-passe-solide', 'statut' => AssureStatut::Actif, 'journal' => [],
        ]);
        $other->forceFill(['email_verified_at' => now()])->save();
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->actingAs($other, 'assure')->get($url)->assertForbidden();
    }

    public function test_the_wizard_gets_the_file_name_but_never_its_internal_path(): void
    {
        Storage::fake('local');
        $field = $this->fileField('enfant');
        $assure = $this->activeAssure();
        $this->actingAs($assure, 'assure')->post(route('assure.dossiers.draft'), ['enfants' => [[
            'nom' => 'Kaboré', 'prenom' => 'Issa', 'sexe' => 'Masculin', 'date_naissance' => '2018-03-04', 'lien' => 'Enfant biologique',
            'fif' => [$field->code => $this->pdf('vaccins.pdf')],
        ]]])->assertSessionHasNoErrors();

        $response = $this->actingAs($assure, 'assure')->get(route('assure.ajouter-membre'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where("initialRows.enfants.0.fif.{$field->code}.filename", 'vaccins.pdf'));

        $this->assertStringNotContainsString('assures\/', $response->getContent());
        $this->assertSame(1, FifSection::query()->count());
    }
}
