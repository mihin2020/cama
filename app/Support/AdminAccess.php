<?php

namespace App\Support;

use App\Models\AdminUser;

/**
 * Contrôles d'accès communs à la connexion et à chaque requête du back-office.
 */
final class AdminAccess
{
    /**
     * Modules proposés comme page d'arrivée, dans l'ordre : permission requise => route.
     * Le tableau de bord présente l'activité des dossiers : il est réservé aux comptes qui les consultent.
     */
    private const LANDINGS = [
        'dossiers.view' => 'admin.dashboard',
        'inscriptions.view' => 'admin.inscriptions',
        'assures.view' => 'admin.assures',
        'exports.export' => 'admin.exports',
        'cms.dashboard' => 'admin.cms.dashboard',
        'cms.pages' => 'admin.cms.pages',
        'cms.articles' => 'admin.cms.actualites',
        'cms.media' => 'admin.cms.media',
        'cms.faq' => 'admin.cms.faq',
        'cms.banniere' => 'admin.cms.banniere',
        'cms.chiffres' => 'admin.cms.chiffres_cles',
        'cms.ressources' => 'admin.cms.ressources',
        'cms.partenaires' => 'admin.cms.partenaires',
        'cms.menus' => 'admin.cms.menus',
        'cms.footer' => 'admin.cms.footer',
        'cms.legal' => 'admin.cms.legal',
        'cms.contacts' => 'admin.cms.contacts',
        'cms.newsletter' => 'admin.cms.newsletter',
        'settings.manage' => 'admin.parametres',
        'fif.manage' => 'admin.fif',
        'users.manage' => 'admin.utilisateurs',
        'roles.manage' => 'admin.roles',
    ];

    /**
     * Raison de refuser l'accès au back-office, ou null si le compte peut l'utiliser.
     */
    public static function refusal(AdminUser $admin): ?string
    {
        if (! $admin->actif) {
            return 'Ce compte administrateur est désactivé.';
        }

        $role = $admin->roleRecord();

        if (! $role) {
            return "Le rôle de ce compte n'existe plus. Contactez un administrateur.";
        }

        if (! $role->actif) {
            return "Le rôle « {$role->nom} » est désactivé : ce compte ne peut pas se connecter. Contactez un administrateur.";
        }

        if ($admin->effective_permissions === []) {
            return "Aucune permission n'est attribuée à ce compte. Contactez un administrateur.";
        }

        return null;
    }

    /** Première page que le compte a le droit d'ouvrir. */
    public static function landingRoute(AdminUser $admin): string
    {
        foreach (self::LANDINGS as $permission => $route) {
            if ($admin->hasPermission($permission)) {
                return $route;
            }
        }

        return 'admin.profil';
    }

    /** Le compte peut-il ouvrir le tableau de bord ? */
    public static function seesDashboard(AdminUser $admin): bool
    {
        return $admin->hasPermission('dossiers.view');
    }
}
