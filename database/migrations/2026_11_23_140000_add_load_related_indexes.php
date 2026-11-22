<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index des requêtes les plus fréquentes (voir docs/dimensionnement-bd.md) : badges de notifications non lues
 * à chaque page, journal d'activité trié par date, dossiers d'un assuré par statut.
 */
return new class extends Migration
{
    private const INDEXES = [
        'assure_notifications' => [['assure_id', 'lu'], 'assure_notifications_assure_lu_index'],
        'admin_notifications' => [['admin_user_id', 'lu'], 'admin_notifications_admin_lu_index'],
        'cms_activity_logs' => [['created_at'], 'cms_activity_logs_created_at_index'],
        'dossiers' => [['assure_id', 'statut'], 'dossiers_assure_statut_index'],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => [$columns, $name]) {
            if (! Schema::hasIndex($table, $name)) {
                Schema::table($table, fn (Blueprint $t) => $t->index($columns, $name));
            }
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $table => [, $name]) {
            if (Schema::hasIndex($table, $name)) {
                Schema::table($table, fn (Blueprint $t) => $t->dropIndex($name));
            }
        }
    }
};
