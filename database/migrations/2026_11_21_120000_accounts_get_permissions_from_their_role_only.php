<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Les permissions se règlent uniquement sur les rôles : chaque compte reçoit celles de son rôle.
 * Les listes propres à un compte (anciennes cases cochées compte par compte) sont abandonnées.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('admin_users')->whereNotNull('permissions')->update(['permissions' => null]);
    }

    public function down(): void
    {
        // Rien à restaurer : les anciennes listes propres aux comptes ne sont plus utilisées.
    }
};
