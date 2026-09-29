<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assures', function (Blueprint $table) {
            $table->text('motif_refus')->nullable()->after('statut');
            $table->json('journal')->nullable()->after('motif_refus');
        });

        Schema::table('dossiers', function (Blueprint $table) {
            $table->date('date_naissance')->nullable()->after('sexe');
            $table->string('membre_numero_cama')->nullable()->after('date_naissance');
            $table->json('pieces')->nullable()->after('motif_refus');
            $table->json('wizard_meta')->nullable()->after('pieces');
        });
    }

    public function down(): void
    {
        Schema::table('dossiers', function (Blueprint $table) {
            $table->dropColumn(['date_naissance', 'membre_numero_cama', 'pieces', 'wizard_meta']);
        });

        Schema::table('assures', function (Blueprint $table) {
            $table->dropColumn(['motif_refus', 'journal']);
        });
    }
};
