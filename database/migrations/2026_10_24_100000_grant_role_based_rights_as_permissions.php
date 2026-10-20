<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ce qui dépendait jusqu'ici du RÔLE devient des permissions explicites (cochables par compte) :
 * supervision, vision de tous les dossiers, publication des pages, HTML libre.
 * Les comptes dont les permissions sont enregistrées les reçoivent selon leur rôle actuel,
 * pour que personne ne gagne ni ne perde de droits au passage.
 * (Les comptes sans liste enregistrée suivent le modèle du rôle, déjà à jour.)
 */
return new class extends Migration
{
    private const GRANTS = [
        'superviseur' => ['dossiers.view_all', 'dossiers.supervise', 'cms.publish'],
        'administrateur' => ['dossiers.view_all', 'dossiers.supervise', 'cms.publish', 'cms.html'],
        'direction' => ['dossiers.view_all'],
    ];

    public function up(): void
    {
        DB::table('admin_users')->whereNotNull('permissions')->orderBy('id')->each(function ($user) {
            $permissions = json_decode((string) $user->permissions, true);
            if (! is_array($permissions)) {
                return;
            }

            $added = self::GRANTS[$user->role] ?? [];
            // La publication n'avait de sens qu'avec l'accès aux pages.
            if (! in_array('cms.pages', $permissions, true) && ! in_array('cms.manage', $permissions, true)) {
                $added = array_diff($added, ['cms.publish', 'cms.html']);
            }

            $merged = array_values(array_unique([...$permissions, ...$added]));
            if ($merged !== $permissions) {
                DB::table('admin_users')->where('id', $user->id)->update(['permissions' => json_encode($merged)]);
            }
        });
    }

    public function down(): void
    {
        // Pas de retour : retirer ces permissions supprimerait des droits aux comptes concernés.
    }
};
