<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La réception des dossiers de second niveau ne se règle plus par une permission de rôle mais dans
 * Paramètres > Règles d'affectation : on retire la permission « dossiers.receive_supervision » si elle a été ajoutée.
 */
return new class extends Migration
{
    private const PERMISSION = 'dossiers.receive_supervision';

    public function up(): void
    {
        foreach (['admin_roles', 'admin_users'] as $table) {
            DB::table($table)->whereNotNull('permissions')->orderBy('id')->each(function ($row) use ($table) {
                $permissions = json_decode((string) $row->permissions, true);

                if (is_array($permissions) && in_array(self::PERMISSION, $permissions, true)) {
                    DB::table($table)->where('id', $row->id)->update([
                        'permissions' => json_encode(array_values(array_diff($permissions, [self::PERMISSION]))),
                    ]);
                }
            });
        }
    }

    public function down(): void
    {
        // Rien à rétablir.
    }
};