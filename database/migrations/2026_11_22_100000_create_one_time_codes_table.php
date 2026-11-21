<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Codes à usage unique (2FA, vérification d'e-mail) : 6 caractères alphanumériques, jamais répétés.
 *  - one_time_codes garde l'empreinte de chaque code émis (index unique = jamais deux fois le même) ;
 *  - le code de vérification d'e-mail n'est plus stocké en clair (empreinte) et a un compteur d'essais ;
 *  - l'ancien historique par assuré (login_2fa_used_codes) est remplacé par cette table commune.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('one_time_codes', function (Blueprint $table) {
            $table->id();
            $table->string('fingerprint', 64)->unique();
            $table->string('context', 30);
            $table->timestamp('created_at')->nullable();
        });

        Schema::table('assures', function (Blueprint $table) {
            $table->string('email_verification_code', 64)->nullable()->change();
            $table->unsignedTinyInteger('email_verification_attempts')->default(0)->after('email_verification_expires_at');
        });

        Schema::table('assures', function (Blueprint $table) {
            $table->dropColumn('login_2fa_used_codes');
        });

        // Les codes en attente (anciens formats, dont l'e-mail en clair) sont annulés : il suffit
        // d'en redemander un.
        DB::table('assures')->update([
            'email_verification_code' => null,
            'email_verification_expires_at' => null,
            'login_2fa_code' => null,
            'login_2fa_expires_at' => null,
            'login_2fa_attempts' => 0,
        ]);
        DB::table('admin_users')->update([
            'login_2fa_code' => null,
            'login_2fa_expires_at' => null,
            'login_2fa_attempts' => 0,
        ]);
    }

    public function down(): void
    {
        Schema::table('assures', function (Blueprint $table) {
            $table->json('login_2fa_used_codes')->nullable();
            $table->dropColumn('email_verification_attempts');
        });

        Schema::dropIfExists('one_time_codes');
    }
};
