<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assures', function (Blueprint $table) {
            $table->json('documents_identite')->nullable()->after('tel_personne_a_prevenir');
        });
    }

    public function down(): void
    {
        Schema::table('assures', function (Blueprint $table) {
            $table->dropColumn('documents_identite');
        });
    }
};
