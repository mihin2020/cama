<?php

use App\Support\AdminPermissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Un compte dont les permissions sont exactement celles de son rôle « suit » désormais ce rôle
 * (permissions = null) : modifier le rôle met alors le compte à jour. Les comptes administrateurs
 * suivent toujours leur rôle (toutes les permissions). Les comptes personnalisés ne changent pas.
 */
return new class extends Migration
{
    public function up(): void
    {
        $roles = DB::table('admin_roles')->get()->keyBy('slug');

        DB::table('admin_users')->whereNotNull('permissions')->orderBy('id')->each(function ($user) use ($roles) {
            $role = $roles[$user->role] ?? null;
            if (! $role) {
                return;
            }

            $stored = json_decode((string) $user->permissions, true);
            $rolePermissions = json_decode((string) $role->permissions, true);

            $follows = $role->slug === 'administrateur'
                || (is_array($stored) && is_array($rolePermissions) && AdminPermissions::sameSet($stored, $rolePermissions));

            if ($follows) {
                DB::table('admin_users')->where('id', $user->id)->update(['permissions' => null]);
            }
        });
    }

    public function down(): void
    {
        // Rien à défaire : un compte qui suit son rôle a exactement les mêmes droits.
    }
};
