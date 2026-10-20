<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Enums\AssureStatut;
use App\Models\AdminUser;
use App\Models\Assure;
use App\Models\Dossier;
use App\Models\ExportLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;
use ZipArchive;

class AdminExportTest extends TestCase
{
    use RefreshDatabase;

    private function admin(AdminRole $role = AdminRole::Administrateur): AdminUser
    {
        return AdminUser::query()->create([
            'nom' => 'Export', 'prenom' => $role->value, 'email' => $role->value.'@cama.bf',
            'password' => 'motdepasse-solide', 'role' => $role, 'actif' => true,
        ]);
    }

    private function seedData(): Assure
    {
        $assure = Assure::query()->create([
            'nom' => 'TRAORÉ', 'prenom' => 'Issouf', 'matricule' => 'MAT-1', 'numero_cim' => 'CIM-1',
            'numero_cama' => 'CAMA-1', 'email' => 'issouf@example.bf', 'password' => 'secret-assure',
            'statut' => AssureStatut::Actif, 'journal' => [],
        ]);

        foreach (['2026-01-10' => 'REF-JANV', '2026-03-15' => 'REF-MARS'] as $date => $ref) {
            Dossier::query()->create([
                'assure_id' => $assure->id, 'ref' => $ref, 'nom' => 'TRAORÉ', 'prenom' => 'Aïcha',
                'lien' => 'Enfant', 'statut' => 'Soumis', 'date_soumission' => $date, 'journal' => [],
            ]);
        }

        return $assure;
    }

    private function catalogFor(AdminUser $admin): array
    {
        return $this->actingAs($admin, 'admin')->get(route('admin.exports'))
            ->assertOk()
            ->viewData('page')['props']['catalog'];
    }

    /** Contenu du fichier téléchargé, qu'il soit diffusé en flux ou envoyé depuis le disque. */
    private function body($response): string
    {
        $base = $response->baseResponse;

        return $base instanceof BinaryFileResponse
            ? file_get_contents($base->getFile()->getPathname())
            : $response->streamedContent();
    }

    public function test_excel_export_is_a_real_workbook_with_one_sheet_per_element(): void
    {
        $this->seedData();
        $admin = $this->admin();

        $response = $this->actingAs($admin, 'admin')->postJson(route('admin.exports.download'), [
            'format' => 'xlsx',
            'datasets' => ['assures' => ['nom', 'prenom', 'matricule'], 'dossiers' => ['ref', 'statut', 'assure_matricule']],
        ]);

        $response->assertOk();
        $this->assertStringContainsString('.xlsx', $response->headers->get('content-disposition'));

        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($path, $this->body($response));
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true, 'Le fichier n\'est pas un classeur Excel valide.');

        $workbook = $zip->getFromName('xl/workbook.xml');
        $this->assertStringContainsString('name="Assurés"', $workbook);
        $this->assertStringContainsString('name="Dossiers d&apos;enrôlement"', $workbook);

        $dossiers = $zip->getFromName('xl/worksheets/sheet2.xml');
        $this->assertStringContainsString('REF-JANV', $dossiers);
        $this->assertStringContainsString('MAT-1', $dossiers, 'La colonne jointe (matricule de l\'assuré) manque.');
        $zip->close();
        unlink($path);

        $this->assertSame(1, ExportLog::query()->where('admin_user_id', $admin->id)->where('format', 'Excel')->count());
    }

    public function test_csv_keeps_only_the_chosen_columns_and_applies_the_period(): void
    {
        $this->seedData();

        $response = $this->actingAs($this->admin(), 'admin')->postJson(route('admin.exports.download'), [
            'format' => 'csv',
            'datasets' => ['dossiers' => ['ref', 'statut']],
            'from' => '2026-03-01',
            'to' => '2026-03-31',
        ]);

        $response->assertOk();
        $lines = array_values(array_filter(explode("\n", str_replace("\xEF\xBB\xBF", '', $this->body($response)))));

        $this->assertSame('Référence;Statut', trim($lines[0]));
        $this->assertCount(2, $lines);
        $this->assertStringContainsString('REF-MARS', $lines[1]);
    }

    public function test_sql_export_is_reimportable_and_never_contains_secrets(): void
    {
        $this->seedData();
        $admin = $this->admin();

        $response = $this->actingAs($admin, 'admin')->postJson(route('admin.exports.download'), [
            'format' => 'sql',
            'datasets' => ['assures' => [], 'admin_users' => []],
            'structure' => true,
        ]);

        $response->assertOk();
        $sql = $this->body($response);

        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS', $sql);
        $this->assertStringContainsString('INSERT INTO "assures"', $sql);
        $this->assertStringContainsString('TRAORÉ', $sql);
        $this->assertStringNotContainsStringIgnoringCase('DROP TABLE', $sql);
        $this->assertStringNotContainsString($admin->getAuthPassword(), $sql);
        $this->assertStringNotContainsString('$2y$', $sql);
    }

    public function test_elements_outside_permissions_are_neither_listed_nor_exportable(): void
    {
        $gestionnaire = $this->admin(AdminRole::Gestionnaire);

        $keys = array_column($this->catalogFor($gestionnaire), 'key');
        $this->assertContains('dossiers', $keys);
        $this->assertNotContains('admin_users', $keys);

        $this->actingAs($gestionnaire, 'admin')->postJson(route('admin.exports.download'), [
            'format' => 'xlsx',
            'datasets' => ['dossiers' => ['ref'], 'admin_users' => ['email']],
        ])->assertStatus(422)->assertJsonValidationErrors('datasets');
    }

    public function test_secret_columns_are_never_offered(): void
    {
        $columns = collect($this->catalogFor($this->admin()))->flatMap(fn ($item) => collect($item['columns'])->pluck('key'));

        $this->assertNotEmpty($columns);
        $this->assertEmpty($columns->intersect(['password', 'remember_token', 'login_2fa_code', 'email_verification_code']));
    }
}
