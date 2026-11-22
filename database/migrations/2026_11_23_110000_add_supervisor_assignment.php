<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Validation à deux niveaux : le dossier validé par un gestionnaire est confié à UN superviseur
 * (colonne « superviseur », nom affiché comme « gestionnaire »), choisi automatiquement (le moins chargé)
 * ou à la main selon la règle d'affectation des superviseurs (Paramètres).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('dossiers', 'superviseur')) {
            Schema::table('dossiers', function (Blueprint $table) {
                $table->string('superviseur', 100)->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('dossiers', 'superviseur')) {
            Schema::table('dossiers', function (Blueprint $table) {
                $table->dropIndex(['superviseur']);
                $table->dropColumn('superviseur');
            });
        }
    }
};