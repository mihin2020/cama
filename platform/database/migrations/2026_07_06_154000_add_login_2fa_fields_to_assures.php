<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assures', function (Blueprint $table) {
            $table->string('login_2fa_code', 10)->nullable()->after('deux_fa_active');
            $table->timestamp('login_2fa_expires_at')->nullable()->after('login_2fa_code');
        });
    }

    public function down(): void
    {
        Schema::table('assures', function (Blueprint $table) {
            $table->dropColumn(['login_2fa_code', 'login_2fa_expires_at']);
        });
    }
};
