<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Le N° CIM n'est plus obligatoire : la colonne accepte l'absence de valeur, et les chaînes
 * vides enregistrées jusqu'ici (champ masqué ou non saisi) deviennent « non renseigné » (null).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assures', function (Blueprint $table) {
            $table->string('numero_cim')->nullable()->change();
        });

        DB::table('assures')->where('numero_cim', '')->update(['numero_cim' => null]);
    }

    public function down(): void
    {
        DB::table('assures')->whereNull('numero_cim')->update(['numero_cim' => '']);

        Schema::table('assures', function (Blueprint $table) {
            $table->string('numero_cim')->nullable(false)->change();
        });
    }
};
