<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Nouvelle permission « Recevoir des dossiers à traiter » (dossiers.receive) : elle décide qui figure
 * dans la liste d'affectation et reçoit des dossiers en affectation automatique. Elle est distincte de
 * « Traiter les dossiers », que les superviseurs et les administrateurs possèdent déjà : sans cela, ils
 * recevraient des dossiers du jour au lendemain.
 *
 * Le rôle Gestionnaire la reçoit, pour que rien ne change pour lui.
 */
return new class extends Migration
{
    public function up(): void
    {
        $role = DB::table('admin_roles')->where('slug', 'gestionnaire')->first();

        if ($role) {
            $permissions = json_decode((string) $role->permissions, true) ?: [];

            if (! in_array('dossiers.receive', $permissions, true)) {
                $permissions[] = 'dossiers.receive';
                DB::table('admin_roles')->where('id', $role->id)->update(['permissions' => json_encode(array_values($permissions))]);
            }
        }

        // Comptes gestionnaires aux permissions propres (anciennes données) : même ajout.
        DB::table('admin_users')->where('role', 'gestionnaire')->whereNotNull('permissions')->orderBy('id')->each(function ($user) {
            $permissions = json_decode((string) $user->permissions, true);

            if (is_array($permissions) && ! in_array('dossiers.receive', $permissions, true)) {
                $permissions[] = 'dossiers.receive';
                DB::table('admin_users')->where('id', $user->id)->update(['permissions' => json_encode(array_values($permissions))]);
            }
        });
    }

    public function down(): void
    {
        // Rien à défaire : retirer la permission couperait l'affectation des gestionnaires.
    }
};
