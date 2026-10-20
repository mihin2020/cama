<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Requêtes de l'affectation automatique et des tableaux de suivi (charge par
     * gestionnaire, dossiers en cours, cohérence familiale) : sans index, chacune
     * parcourt toute la table, et elles s'exécutent sous verrou à chaque soumission.
     */
    public function up(): void
    {
        Schema::table('dossiers', function (Blueprint $table) {
            $table->index(['statut', 'gestionnaire'], 'dossiers_statut_gestionnaire_index');
            $table->index(['assure_id', 'gestionnaire'], 'dossiers_assure_gestionnaire_index');
        });
    }

    public function down(): void
    {
        Schema::table('dossiers', function (Blueprint $table) {
            $table->dropIndex('dossiers_statut_gestionnaire_index');
            $table->dropIndex('dossiers_assure_gestionnaire_index');
        });
    }
};
