<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jetons de réinitialisation des assurés, dans une table à part : la table partagée est
     * indexée par e-mail, donc un assuré et un compte interne de même adresse s'écraseraient
     * mutuellement leurs liens (invitation comprise).
     */
    public function up(): void
    {
        Schema::create('assure_password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assure_password_reset_tokens');
    }
};
