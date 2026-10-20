<?php

namespace App\Services;

use App\Models\AdminUser;
use App\Models\Dossier;
use App\Models\ExportLog;
use App\Support\ExportCatalog;
use App\Support\Exports\XlsxWriter;
use Generator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class ExportService
{
    /** Lignes lues par requête : assez pour aller vite, assez peu pour ne pas saturer la mémoire. */
    private const CHUNK = 2000;

    /** Lignes par instruction INSERT dans l'export SQL. */
    private const SQL_BATCH = 200;

    public const FORMATS = ['xlsx' => 'Excel', 'csv' => 'CSV', 'sql' => 'SQL'];

    /** @var array<string, array<string, array>> */
    private array $columnCache = [];

    private ?int $schemaVersion = null;

    public function __construct(
        private DossierService $dossiers,
        private AdminPermissionService $permissions,
        private PlatformSettingsService $settings,
    ) {}

    /** Trace d'audit (table export_logs) : qui a exporté quoi, et quand. */
    public function log(AdminUser $admin, string $nom, string $format): ExportLog
    {
        return ExportLog::query()->create([
            'admin_user_id' => $admin->id,
            'nom' => Str::limit($nom, 250),
            'format' => $format,
        ]);
    }

    /**
     * Éléments que ce compte peut exporter, avec leurs colonnes et leur nombre de lignes.
     */
    public function catalog(AdminUser $admin): array
    {
        $items = [];
        foreach ($this->allowedDatasets($admin) as $key => $dataset) {
            $items[] = [
                'key' => $key,
                'group' => $dataset['group'],
                'label' => $dataset['label'],
                'description' => $dataset['description'],
                'count' => DB::table($dataset['table'])->count(),
                'hasDate' => isset($dataset['date']),
                'columns' => collect($this->selectableColumns($dataset))
                    ->map(fn (string $column) => ['key' => $column, 'label' => $this->label($dataset, $column)])
                    ->values()
                    ->all(),
            ];
        }

        return $items;
    }

    /**
     * Génère l'export demandé.
     *
     * @param  array<string, list<string>|null>  $selection  clé de l'élément => colonnes choisies
     */
    public function export(AdminUser $admin, array $selection, string $format, ?string $from = null, ?string $to = null, bool $structure = true): StreamedResponse|BinaryFileResponse
    {
        $resolved = $this->resolveSelection($admin, $selection, $format);

        @set_time_limit(0);

        $labels = collect($resolved)->map(fn ($item) => $item['dataset']['label'])->implode(', ');
        $period = $from || $to ? ' — période '.($from ?? '…').' au '.($to ?? '…') : '';
        $this->log($admin, $labels.$period, self::FORMATS[$format]);

        $stamp = now()->format('Y-m-d_His');
        $base = count($resolved) === 1 ? 'cama-'.Str::slug(array_key_first($resolved)) : 'cama-export';

        return match ($format) {
            'sql' => response()->streamDownload(function () use ($resolved, $from, $to, $structure, $admin) {
                $out = fopen('php://output', 'w');
                $this->writeSql($out, $resolved, $from, $to, $structure, $admin);
                fclose($out);
            }, "{$base}-{$stamp}.sql", ['Content-Type' => 'application/sql; charset=UTF-8']),

            'csv' => count($resolved) === 1
                ? response()->streamDownload(function () use ($resolved, $from, $to) {
                    $out = fopen('php://output', 'w');
                    $this->writeCsv($out, reset($resolved), $from, $to);
                    fclose($out);
                }, "{$base}-{$stamp}.csv", ['Content-Type' => 'text/csv; charset=UTF-8'])
                : $this->download($this->buildCsvZip($resolved, $from, $to), "{$base}-{$stamp}.zip", 'application/zip'),

            default => $this->download(
                $this->buildXlsx($resolved, $from, $to),
                "{$base}-{$stamp}.xlsx",
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ),
        };
    }

    public function dossierForPdf(string $ref): ?array
    {
        $dossier = Dossier::query()->with('assure')->where('ref', $ref)->first();
        if (! $dossier) {
            return null;
        }

        $membre = $this->dossiers->formatMembre($dossier);
        $assure = $dossier->assure;

        return [
            'membre' => $membre,
            'assure' => [
                'nom' => $assure->nom,
                'prenom' => $assure->prenom,
                'fullName' => $assure->full_name,
                'matricule' => $assure->matricule,
                'numeroCama' => $assure->numero_cama,
                'grade' => $assure->grade,
                'armee' => $assure->armee,
            ],
        ];
    }

    /* ---------------------------------------------------------------- Sélection */

    /**
     * @return array<string, array>
     */
    private function allowedDatasets(AdminUser $admin): array
    {
        return collect(ExportCatalog::datasets())
            ->filter(fn (array $dataset) => $this->permissions->allows($admin, $dataset['permission'])
                && Schema::hasTable($dataset['table']))
            ->all();
    }

    /**
     * @return array<string, array{dataset: array, columns: list<string>}>
     */
    private function resolveSelection(AdminUser $admin, array $selection, string $format): array
    {
        if (! isset(self::FORMATS[$format])) {
            throw ValidationException::withMessages(['format' => 'Format inconnu.']);
        }

        $allowed = $this->allowedDatasets($admin);
        $resolved = [];

        // L'ordre du catalogue est conservé : le fichier se lit toujours dans le même ordre.
        foreach ($allowed as $key => $dataset) {
            if (! array_key_exists($key, $selection)) {
                continue;
            }

            if ($format === 'sql') {
                // Le SQL reprend toutes les colonnes de la table pour rester réimportable.
                $columns = $this->sqlColumns($dataset['table']);
            } else {
                $wanted = $selection[$key] ?? [];
                $columns = array_values(array_intersect($this->selectableColumns($dataset), $wanted));
                if ($columns === []) {
                    throw ValidationException::withMessages([
                        'datasets' => "Choisissez au moins une colonne pour « {$dataset['label']} ».",
                    ]);
                }
            }

            $resolved[$key] = ['dataset' => $dataset, 'columns' => $columns];
        }

        $refused = array_diff(array_keys($selection), array_keys($allowed));
        if ($refused !== []) {
            throw ValidationException::withMessages([
                'datasets' => 'Vous n\'avez pas accès à : '.implode(', ', $refused).'.',
            ]);
        }

        if ($resolved === []) {
            throw ValidationException::withMessages(['datasets' => 'Cochez au moins un élément à exporter.']);
        }

        return $resolved;
    }

    /**
     * Colonnes proposées à l'export Excel / CSV : celles de la table hors secrets, plus
     * les colonnes de confort (nom de l'assuré, du compte interne…).
     *
     * @return list<string>
     */
    private function selectableColumns(array $dataset): array
    {
        $columns = array_keys($this->tableColumns($dataset['table']));
        $columns = array_values(array_diff($columns, ExportCatalog::SECRET_COLUMNS));

        return [...$columns, ...array_keys($dataset['extras'] ?? [])];
    }

    /**
     * Colonnes de l'export SQL : toutes, sauf les secrets — remplacés par une valeur neutre
     * lorsque la colonne est obligatoire (sinon la ligne ne pourrait pas être réimportée).
     *
     * @return list<string>
     */
    private function sqlColumns(string $table): array
    {
        return collect($this->tableColumns($table))
            ->filter(fn (array $column, string $name) => ! in_array($name, ExportCatalog::SECRET_COLUMNS, true)
                || $this->needsPlaceholder($column))
            ->keys()
            ->all();
    }

    private function needsPlaceholder(array $column): bool
    {
        return ! $column['nullable'] && $column['default'] === null;
    }

    /**
     * @return array<string, array{type: string, type_name: string, nullable: bool, default: mixed}>
     */
    private function tableColumns(string $table): array
    {
        // La structure ne change qu'avec une migration : mise en cache, renouvelée à chaque nouvelle migration.
        return $this->columnCache[$table] ??= Cache::rememberForever(
            "export.columns.{$table}.".$this->schemaVersion(),
            fn () => collect(Schema::getColumns($table))->keyBy('name')->all(),
        );
    }

    private function schemaVersion(): int
    {
        return $this->schemaVersion ??= (int) DB::table('migrations')->max('id');
    }

    private function label(array $dataset, string $column): string
    {
        return $dataset['extras'][$column]['label']
            ?? $dataset['labels'][$column]
            ?? ExportCatalog::columnLabel($column);
    }

    /* ---------------------------------------------------------------- Lecture */

    /**
     * Lit la table par lots (pagination par identifiant : rapide même sur de gros volumes).
     *
     * @return Generator<int, array<string, mixed>>
     */
    private function rows(array $dataset, array $columns, ?string $from, ?string $to): Generator
    {
        $table = $dataset['table'];
        $tableColumns = $this->tableColumns($table);
        $query = DB::table($table);
        $select = [];
        $joined = [];

        foreach ($columns as $column) {
            if (isset($tableColumns[$column])) {
                $select[] = "{$table}.{$column}";

                continue;
            }

            $extra = $dataset['extras'][$column];
            $alias = 'x_'.$extra['foreign'];
            if (! isset($joined[$alias])) {
                $query->leftJoin("{$extra['table']} as {$alias}", "{$alias}.id", '=', "{$table}.{$extra['foreign']}");
                $joined[$alias] = true;
            }
            $select[] = "{$alias}.{$extra['column']} as {$column}";
        }

        if ($date = $dataset['date'] ?? null) {
            $query->when($from, fn ($q) => $q->whereDate("{$table}.{$date}", '>=', $from))
                ->when($to, fn ($q) => $q->whereDate("{$table}.{$date}", '<=', $to));
        }

        if (isset($tableColumns['id'])) {
            $select[] = "{$table}.id as __pk";
            $iterator = $query->select($select)->lazyById(self::CHUNK, "{$table}.id", '__pk');
        } else {
            $order = collect(Schema::getIndexes($table))->firstWhere('primary', true)['columns'] ?? [array_key_first($tableColumns)];
            foreach ($order as $column) {
                $query->orderBy("{$table}.{$column}");
            }
            $iterator = $query->select($select)->lazy(self::CHUNK);
        }

        foreach ($iterator as $row) {
            $row = (array) $row;
            unset($row['__pk']);

            yield $row;
        }
    }

    /**
     * Valeur lisible pour Excel / CSV (booléens en Oui / Non).
     */
    private function displayValue(array $dataset, string $column, mixed $value): mixed
    {
        $meta = $this->tableColumns($dataset['table'])[$column] ?? null;
        if ($meta && $value !== null && $this->isBoolean($meta)) {
            return (int) $value === 1 ? 'Oui' : 'Non';
        }

        return $value;
    }

    private function isBoolean(array $meta): bool
    {
        return in_array(strtolower($meta['type']), ['tinyint(1)', 'boolean', 'bool'], true);
    }

    private function isNumeric(array $dataset, string $column): bool
    {
        $meta = $this->tableColumns($dataset['table'])[$column] ?? null;
        if (! $meta || $this->isBoolean($meta)) {
            return false;
        }

        return in_array(strtolower($meta['type_name']), ['int', 'integer', 'bigint', 'smallint', 'mediumint', 'tinyint', 'decimal', 'numeric', 'float', 'double', 'real'], true);
    }

    /* ---------------------------------------------------------------- Excel */

    private function buildXlsx(array $resolved, ?string $from, ?string $to): string
    {
        $dir = $this->workDir();
        $writer = new XlsxWriter($dir, $this->settings->themeHex('primary'));

        foreach ($resolved as $item) {
            ['dataset' => $dataset, 'columns' => $columns] = $item;
            $writer->startSheet(
                $dataset['label'],
                array_map(fn ($c) => $this->label($dataset, $c), $columns),
                array_map(fn ($c) => $this->isNumeric($dataset, $c), $columns),
            );

            foreach ($this->rows($dataset, $columns, $from, $to) as $row) {
                $writer->addRow(array_map(fn ($c) => $this->displayValue($dataset, $c, $row[$c] ?? null), $columns));
            }
        }

        return $writer->save($dir.'.xlsx');
    }

    /* ---------------------------------------------------------------- CSV */

    /**
     * @param  resource  $out
     */
    private function writeCsv($out, array $item, ?string $from, ?string $to): void
    {
        ['dataset' => $dataset, 'columns' => $columns] = $item;

        // BOM + point-virgule : ouverture directe et accents corrects dans Excel (réglages français).
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, array_map(fn ($c) => $this->label($dataset, $c), $columns), ';');

        foreach ($this->rows($dataset, $columns, $from, $to) as $row) {
            fputcsv($out, array_map(
                fn ($c) => $this->csvSafe($this->displayValue($dataset, $c, $row[$c] ?? null)),
                $columns,
            ), ';');
        }
    }

    /** Neutralise les valeurs qu'Excel interpréterait comme une formule (=, +, -, @). */
    private function csvSafe(mixed $value): mixed
    {
        if (is_string($value) && $value !== '' && ! is_numeric($value) && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }

    private function buildCsvZip(array $resolved, ?string $from, ?string $to): string
    {
        $dir = $this->workDir();
        $zip = new ZipArchive;
        $zip->open($dir.'.zip', ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($resolved as $key => $item) {
            $path = $dir.DIRECTORY_SEPARATOR.$key.'.csv';
            $out = fopen($path, 'w');
            $this->writeCsv($out, $item, $from, $to);
            fclose($out);
            $zip->addFile($path, Str::slug($item['dataset']['label']).'.csv');
        }

        $zip->close();
        File::deleteDirectory($dir);

        return $dir.'.zip';
    }

    /* ---------------------------------------------------------------- SQL */

    /**
     * Script SQL réimportable : CREATE TABLE IF NOT EXISTS (jamais de DROP : importer ce
     * fichier ne peut pas effacer de données) puis INSERT groupés.
     *
     * @param  resource  $out
     */
    private function writeSql($out, array $resolved, ?string $from, ?string $to, bool $structure, AdminUser $admin): void
    {
        $connection = DB::connection();
        $pdo = $connection->getPdo();
        $grammar = $connection->getQueryGrammar();
        $mysql = in_array($connection->getDriverName(), ['mysql', 'mariadb'], true);

        fwrite($out, "-- Export CAMA — {$connection->getDatabaseName()}\n");
        fwrite($out, '-- Généré le '.now()->format('d/m/Y à H:i')." par {$admin->display_name}\n");
        if ($from || $to) {
            fwrite($out, '-- Période : '.($from ?? '…').' au '.($to ?? '…')."\n");
        }
        fwrite($out, "-- Les mots de passe, jetons et codes de sécurité ne sont pas exportés :\n");
        fwrite($out, "-- les comptes réimportés devront réinitialiser leur mot de passe.\n\n");
        if ($mysql) {
            fwrite($out, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n");
        }

        foreach ($resolved as $item) {
            ['dataset' => $dataset, 'columns' => $columns] = $item;
            $table = $dataset['table'];
            $meta = $this->tableColumns($table);

            fwrite($out, "-- ------------------------------------------------------------\n");
            fwrite($out, "-- {$dataset['label']} ({$table})\n");
            fwrite($out, "-- ------------------------------------------------------------\n\n");

            if ($structure && ($create = $this->createStatement($table)) !== null) {
                fwrite($out, $create.";\n\n");
            }

            $head = 'INSERT INTO '.$grammar->wrapTable($table).' ('.implode(', ', array_map(fn ($c) => $grammar->wrap($c), $columns)).") VALUES\n";
            $batch = [];
            // Les secrets ne sont même pas lus en base.
            $readable = array_values(array_diff($columns, ExportCatalog::SECRET_COLUMNS));

            foreach ($this->rows($dataset, $readable, $from, $to) as $row) {
                $values = [];
                foreach ($columns as $column) {
                    // Secret obligatoire : valeur vide (un mot de passe vide ne permet aucune connexion).
                    $value = in_array($column, ExportCatalog::SECRET_COLUMNS, true) ? '' : ($row[$column] ?? null);
                    $values[] = $this->sqlValue($pdo, $value, $meta[$column] ?? null);
                }
                $batch[] = '('.implode(', ', $values).')';

                if (count($batch) >= self::SQL_BATCH) {
                    fwrite($out, $head.implode(",\n", $batch).";\n");
                    $batch = [];
                }
            }

            if ($batch !== []) {
                fwrite($out, $head.implode(",\n", $batch).";\n");
            }
            fwrite($out, "\n");
        }

        if ($mysql) {
            fwrite($out, "SET FOREIGN_KEY_CHECKS = 1;\n");
        }
    }

    private function createStatement(string $table): ?string
    {
        $driver = DB::connection()->getDriverName();

        $sql = match ($driver) {
            'mysql', 'mariadb' => (array) (DB::selectOne('SHOW CREATE TABLE '.DB::connection()->getQueryGrammar()->wrapTable($table)) ?? []),
            'sqlite' => ['sql' => DB::table('sqlite_master')->where('type', 'table')->where('name', $table)->value('sql')],
            default => [],
        };
        $statement = $sql['Create Table'] ?? $sql['sql'] ?? null;

        return $statement ? preg_replace('/^CREATE TABLE\s+/i', 'CREATE TABLE IF NOT EXISTS ', $statement) : null;
    }

    private function sqlValue(\PDO $pdo, mixed $value, ?array $meta): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if ($meta && is_numeric($value) && in_array(strtolower($meta['type_name']), ['int', 'integer', 'bigint', 'smallint', 'mediumint', 'tinyint', 'decimal', 'numeric', 'float', 'double', 'real'], true)) {
            return (string) $value;
        }

        return $pdo->quote((string) $value);
    }

    /* ---------------------------------------------------------------- Fichiers */

    /** Dossier de travail temporaire ; les fichiers de plus d'une heure sont nettoyés au passage. */
    private function workDir(): string
    {
        $root = storage_path('app/private/exports');
        File::ensureDirectoryExists($root);

        foreach (File::glob($root.DIRECTORY_SEPARATOR.'*') as $old) {
            if (@filemtime($old) < time() - 3600) {
                is_dir($old) ? File::deleteDirectory($old) : @unlink($old);
            }
        }

        $dir = $root.DIRECTORY_SEPARATOR.Str::uuid();
        File::ensureDirectoryExists($dir);

        return $dir;
    }

    private function download(string $path, string $name, string $mime): BinaryFileResponse
    {
        $dir = substr($path, 0, strrpos($path, '.'));
        if (is_dir($dir)) {
            File::deleteDirectory($dir);
        }

        return response()->download($path, $name, ['Content-Type' => $mime])->deleteFileAfterSend();
    }
}
