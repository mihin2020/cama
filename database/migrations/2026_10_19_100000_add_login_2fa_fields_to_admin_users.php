<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_users', function (Blueprint $table) {
            // 2FA facultative : disponible pour tous les comptes, désactivée par défaut.
            $table->boolean('deux_fa_active')->default(false)->after('actif');
            // Empreinte HMAC du code (jamais le code en clair).
            $table->string('login_2fa_code', 64)->nullable()->after('deux_fa_active');
            $table->timestamp('login_2fa_expires_at')->nullable()->after('login_2fa_code');
            $table->unsignedTinyInteger('login_2fa_attempts')->default(0)->after('login_2fa_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('admin_users', function (Blueprint $table) {
            $table->dropColumn(['deux_fa_active', 'login_2fa_code', 'login_2fa_expires_at', 'login_2fa_attempts']);
        });
    }
};
