<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Formulaire FIF configurable : l'administration ajoute, modifie, masque, réordonne et supprime des
 * sections et des champs pour l'assuré, le conjoint et l'enfant.
 *
 *  - fif_sections / fif_fields : la définition du formulaire (suppression « douce » : les données déjà
 *    saisies ne sont jamais perdues, le champ n'est simplement plus proposé ni imprimé) ;
 *  - assures.fif_donnees / dossiers.fif_donnees : valeurs saisies, au format {code_du_champ: valeur}.
 *    Le code d'un champ ne change jamais, même si on renomme son libellé.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fif_sections', function (Blueprint $table) {
            $table->id();
            $table->string('cible', 20);
            $table->string('code', 80);
            $table->string('titre', 150);
            $table->string('description', 500)->nullable();
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->boolean('visible')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['cible', 'code']);
            $table->index(['cible', 'ordre']);
        });

        Schema::create('fif_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained('fif_sections')->cascadeOnDelete();
            $table->string('code', 80);
            $table->string('libelle', 150);
            $table->string('type', 20)->default('text');
            $table->json('options')->nullable();
            $table->string('aide', 300)->nullable();
            $table->boolean('obligatoire')->default(false);
            $table->boolean('visible')->default(true);
            $table->boolean('imprimer_pdf')->default(true);
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['section_id', 'code']);
            $table->index(['section_id', 'ordre']);
        });

        Schema::table('assures', function (Blueprint $table) {
            $table->json('fif_donnees')->nullable();
        });

        Schema::table('dossiers', function (Blueprint $table) {
            $table->json('fif_donnees')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('dossiers', function (Blueprint $table) {
            $table->dropColumn('fif_donnees');
        });
        Schema::table('assures', function (Blueprint $table) {
            $table->dropColumn('fif_donnees');
        });
        Schema::dropIfExists('fif_fields');
        Schema::dropIfExists('fif_sections');
    }
};
