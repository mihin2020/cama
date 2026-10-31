<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Les routes des inscriptions et des paramètres étaient verrouillées par rôle : une permission
 * cochée sur un compte d'un autre rôle restait sans effet. Ce verrou a disparu au profit des
 * seules permissions ; on retire donc ces permissions « dormantes » pour que personne ne gagne
 * de droits au passage. Un administrateur peut les réattribuer explicitement ensuite.
 */
return new class extends Migration
{
    /** Permission => rôles qui y avaient réellement accès avant la suppression du verrou. */
    private const PREVIOUSLY_ALLOWED = [
        'inscriptions.view' => ['gestionnaire', 'superviseur', 'administrateur'],
        'inscriptions.validate' => ['gestionnaire', 'superviseur', 'administrateur'],
        'settings.manage' => ['administrateur'],
    ];

    public function up(): void
    {
        DB::table('admin_users')->whereNotNull('permissions')->orderBy('id')->each(function ($user) {
            $permissions = json_decode((string) $user->permissions, true);
            if (! is_array($permissions)) {
                return;
            }

            $kept = array_values(array_filter($permissions, fn ($key) => ! isset(self::PREVIOUSLY_ALLOWED[$key])
                || in_array($user->role, self::PREVIOUSLY_ALLOWED[$key], true)));

            if ($kept !== $permissions) {
                DB::table('admin_users')->where('id', $user->id)->update(['permissions' => json_encode($kept)]);
            }
        });
    }

    public function down(): void
    {
        // Pas de retour : rendre ces permissions rouvrirait des accès qui n'avaient jamais été effectifs.
    }
};
