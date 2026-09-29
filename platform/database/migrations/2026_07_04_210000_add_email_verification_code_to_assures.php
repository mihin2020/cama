<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assures', function (Blueprint $table) {
            $table->string('email_verification_code', 10)->nullable()->after('email_verified_at');
            $table->timestamp('email_verification_expires_at')->nullable()->after('email_verification_code');
        });

        // Les comptes existants (créés avant la vérification e-mail) sont considérés vérifiés.
        DB::table('assures')->whereNull('email_verified_at')->update(['email_verified_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('assures', function (Blueprint $table) {
            $table->dropColumn(['email_verification_code', 'email_verification_expires_at']);
        });
    }
};
