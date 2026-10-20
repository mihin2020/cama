<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Le matricule devient paramétrable à l'inscription (Paramètres › Champs d'inscription) :
     * il peut donc être absent. L'unicité reste garantie pour les matricules renseignés.
     */
    public function up(): void
    {
        Schema::table('assures', function (Blueprint $table) {
            $table->string('matricule')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('assures', function (Blueprint $table) {
            $table->string('matricule')->nullable(false)->change();
        });
    }
};
