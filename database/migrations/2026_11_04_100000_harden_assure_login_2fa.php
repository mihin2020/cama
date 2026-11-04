<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 2FA assuré : compteur d'essais par code et code stocké sous forme d'empreinte HMAC
     * (comme pour le back-office), donc colonne élargie.
     */
    public function up(): void
    {
        // Les codes en clair encore en attente (15 min max) et l'historique en clair sont effacés :
        // ils ne seraient plus reconnus de toute façon.
        DB::table('assures')->update([
            'login_2fa_code' => null,
            'login_2fa_expires_at' => null,
            'login_2fa_used_codes' => null,
        ]);

        Schema::table('assures', function (Blueprint $table) {
            $table->string('login_2fa_code', 64)->nullable()->change();
            $table->unsignedTinyInteger('login_2fa_attempts')->default(0)->after('login_2fa_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('assures', function (Blueprint $table) {
            $table->dropColumn('login_2fa_attempts');
        });
    }
};
