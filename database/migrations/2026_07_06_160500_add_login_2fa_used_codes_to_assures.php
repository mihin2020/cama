<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assures', function (Blueprint $table) {
            $table->json('login_2fa_used_codes')->nullable()->after('login_2fa_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('assures', function (Blueprint $table) {
            $table->dropColumn('login_2fa_used_codes');
        });
    }
};
