<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dossiers', function (Blueprint $table) {
            $table->uuid('lot_id')->nullable()->after('wizard_meta')->index();
            $table->string('lot_type', 32)->default('initial')->after('lot_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('dossiers', function (Blueprint $table) {
            $table->dropColumn(['lot_id', 'lot_type']);
        });
    }
};
