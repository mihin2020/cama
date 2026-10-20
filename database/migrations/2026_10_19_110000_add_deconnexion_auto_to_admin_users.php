<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_users', function (Blueprint $table) {
            // Déconnexion pour inactivité : facultative, désactivée par défaut.
            $table->boolean('deconnexion_auto_active')->default(false)->after('deux_fa_active');
        });
    }

    public function down(): void
    {
        Schema::table('admin_users', function (Blueprint $table) {
            $table->dropColumn('deconnexion_auto_active');
        });
    }
};
