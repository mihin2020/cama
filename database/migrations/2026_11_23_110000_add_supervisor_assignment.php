<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Validation à deux niveaux : le dossier validé par un gestionnaire est confié à UN superviseur
 * (colonne « superviseur », nom affiché comme « gestionnaire »), choisi automatiquement (le moins chargé)
 * puis réaffectable à la main. Nouvelle permission « Recevoir des dossiers en supervision », distincte de
 * « Recevoir des dossiers à traiter » : sans cela, un superviseur qui reçoit des dossiers en supervision
 * en recevrait aussi en premier niveau.
 */
return new class extends Migration
{
    private const PERMISSION = 'dossiers.receive_supervision';

    public function up(): void
    {
        if (! Schema::hasColumn('dossiers', 'superviseur')) {
            Schema::table('dossiers', function (Blueprint $table) {
                $table->string('superviseur', 100)->nullable()->index();
            });
        }

        $role = DB::table('admin_roles')->where('slug', 'superviseur')->first();

        if ($role) {
            $permissions = json_decode((string) $role->permissions, true) ?: [];

            if (! in_array(self::PERMISSION, $permissions, true)) {
                $permissions[] = self::PERMISSION;
                DB::table('admin_roles')->where('id', $role->id)->update(['permissions' => json_encode(array_values($permissions))]);
            }
        }

        DB::table('admin_users')->where('role', 'superviseur')->whereNotNull('permissions')->orderBy('id')->each(function ($user) {
            $permissions = json_decode((string) $user->permissions, true);

            if (is_array($permissions) && ! in_array(self::PERMISSION, $permissions, true)) {
                $permissions[] = self::PERMISSION;
                DB::table('admin_users')->where('id', $user->id)->update(['permissions' => json_encode(array_values($permissions))]);
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('dossiers', 'superviseur')) {
            Schema::table('dossiers', function (Blueprint $table) {
                $table->dropIndex(['superviseur']);
                $table->dropColumn('superviseur');
            });
        }
    }
};
