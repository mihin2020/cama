<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * PostgreSQL : les recherches « contient » (ILIKE '%mot%') des listes d'administration ne peuvent pas utiliser un
 * index ordinaire et parcourent toute la table (mesuré : plus de 200 ms sur 50 000 assurés). Les index trigrammes
 * (extension pg_trgm) les rendent quasi instantanés. Sans effet sur MySQL et SQLite.
 *
 * L'extension demande un compte autorisé à la créer ; sinon la migration passe son chemin et l'application
 * fonctionne comme avant (recherches plus lentes sur de gros volumes).
 */
return new class extends Migration
{
    private const INDEXES = [
        'assures' => ['nom', 'prenom', 'matricule', 'numero_cama', 'email'],
        'admin_users' => ['nom', 'prenom', 'email'],
        'cms_media' => ['name', 'original_name', 'title'],
    ];

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        try {
            DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        } catch (\Throwable $e) {
            report($e);

            return;
        }

        foreach (self::INDEXES as $table => $columns) {
            foreach ($columns as $column) {
                DB::statement("CREATE INDEX IF NOT EXISTS {$table}_{$column}_trgm ON {$table} USING gin ({$column} gin_trgm_ops)");
            }
        }

        DB::statement('CREATE INDEX IF NOT EXISTS assures_statut_index ON assures (statut)');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach (self::INDEXES as $table => $columns) {
            foreach ($columns as $column) {
                DB::statement("DROP INDEX IF EXISTS {$table}_{$column}_trgm");
            }
        }

        DB::statement('DROP INDEX IF EXISTS assures_statut_index');
    }
};
