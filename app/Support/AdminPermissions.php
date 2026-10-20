<?php

namespace App\Support;

use App\Enums\AdminRole;

class AdminPermissions
{
    public const GRADES = [
        'Soldat',
        'Caporal',
        'Caporal-chef',
        'Sergent',
        'Sergent-chef',
        'Adjudant',
        'Adjudant-chef',
        'Sous-lieutenant',
        'Lieutenant',
        'Capitaine',
        'Commandant',
        'Lieutenant-colonel',
        'Colonel',
        'Colonel-major',
        'Général',
        'Ingénieur',
        'Agent civil',
    ];

    /**
     * @return array<int, array{group: string, items: array<int, array{key: string, label: string}>}>
     */
    public static function catalog(): array
    {
        return [
            [
                'group' => 'Dossiers',
                'items' => [
                    ['key' => 'dossiers.view', 'label' => 'Consulter les dossiers qui lui sont affectés'],
                    ['key' => 'dossiers.view_all', 'label' => 'Consulter tous les dossiers (pas seulement les siens)'],
                    ['key' => 'dossiers.process', 'label' => 'Traiter / valider / refuser les dossiers'],
                    ['key' => 'dossiers.supervise', 'label' => 'Valider en second niveau (supervision)'],
                    ['key' => 'dossiers.assign', 'label' => 'Affecter les dossiers aux gestionnaires'],
                ],
            ],
            [
                'group' => 'Assurés',
                'items' => [
                    ['key' => 'assures.view', 'label' => 'Consulter les assurés'],
                    ['key' => 'assures.manage', 'label' => 'Activer / désactiver les comptes assurés'],
                ],
            ],
            [
                'group' => 'Inscriptions',
                'items' => [
                    ['key' => 'inscriptions.view', 'label' => 'Consulter les inscriptions'],
                    ['key' => 'inscriptions.validate', 'label' => 'Valider / refuser les inscriptions'],
                ],
            ],
            [
                'group' => 'Exports',
                'items' => [
                    ['key' => 'exports.export', 'label' => 'Exporter des données (Excel, CSV, SQL) et fiches PDF'],
                ],
            ],
            [
                'group' => 'Paramètres',
                'items' => [
                    ['key' => 'settings.manage', 'label' => 'Gérer les paramètres de la plateforme'],
                ],
            ],
            [
                'group' => 'Studio de contenu',
                'items' => [
                    ['key' => 'cms.manage', 'label' => 'Accès complet au studio de contenu'],
                    ['key' => 'cms.dashboard', 'label' => 'Tableau de bord CMS'],
                    ['key' => 'cms.pages', 'label' => 'Pages et page builder'],
                    ['key' => 'cms.publish', 'label' => 'Publier les pages et gérer leurs versions'],
                    ['key' => 'cms.html', 'label' => 'Insérer du code HTML libre dans les pages (profil technique)'],
                    ['key' => 'cms.media', 'label' => 'Médiathèque'],
                    ['key' => 'cms.articles', 'label' => 'Actualités'],
                    ['key' => 'cms.faq', 'label' => 'FAQ'],
                    ['key' => 'cms.banniere', 'label' => 'Bannière d\'accueil'],
                    ['key' => 'cms.chiffres', 'label' => 'Chiffres clés'],
                    ['key' => 'cms.ressources', 'label' => 'Ressources documentaires'],
                    ['key' => 'cms.partenaires', 'label' => 'Partenaires'],
                    ['key' => 'cms.menus', 'label' => 'Menus de navigation'],
                    ['key' => 'cms.footer', 'label' => 'Identité visuelle (logo, couleurs) et pied de page'],
                    ['key' => 'cms.legal', 'label' => 'Mentions légales et confidentialité'],
                    ['key' => 'cms.contacts', 'label' => 'Messages contact'],
                    ['key' => 'cms.newsletter', 'label' => 'Newsletter'],
                ],
            ],
            [
                'group' => 'Comptes internes',
                'items' => [
                    ['key' => 'users.manage', 'label' => 'Gérer les comptes internes et permissions'],
                ],
            ],
            [
                'group' => 'Notifications',
                'items' => [
                    ['key' => 'notifications.receive', 'label' => 'Recevoir les alertes système'],
                ],
            ],
        ];
    }

    /** Libellé lisible d'une permission (messages d'erreur). */
    public static function label(string $key): string
    {
        return collect(self::catalog())
            ->flatMap(fn (array $group) => $group['items'])
            ->firstWhere('key', $key)['label'] ?? $key;
    }

    /**
     * @return list<string>
     */
    public static function allKeys(): array
    {
        return collect(self::catalog())
            ->flatMap(fn (array $group) => collect($group['items'])->pluck('key'))
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public static function sanitize(?array $permissions): array
    {
        $allowed = self::allKeys();

        return array_values(array_intersect($permissions ?? [], $allowed));
    }

    /**
     * Permissions réservées à certains rôles : gérer les comptes internes n'a de sens que pour
     * un superviseur ou un administrateur (un gestionnaire n'a aucun compte « en dessous » de lui).
     *
     * @var array<string, list<AdminRole>>
     */
    public const ROLE_RESTRICTED = [
        'users.manage' => [AdminRole::Superviseur, AdminRole::Administrateur],
    ];

    /**
     * Permissions du studio NON couvertes par « Accès complet au studio » : du HTML libre peut
     * injecter du code dans le site public, il doit être accordé explicitement.
     */
    public const CMS_EXPLICIT_ONLY = ['cms.html'];

    /** « Accès complet au studio » couvre-t-il cette permission ? */
    public static function coveredByCmsManage(string $permission): bool
    {
        return str_starts_with($permission, 'cms.') && ! in_array($permission, self::CMS_EXPLICIT_ONLY, true);
    }

    /**
     * Permissions qu'un compte peut attribuer : un administrateur, toutes ; les autres,
     * uniquement celles qu'ils possèdent eux-mêmes (pas d'élévation de privilèges).
     *
     * @return list<string>
     */
    public static function grantableBy(AdminRole $actorRole, array $actorPermissions): array
    {
        if ($actorRole === AdminRole::Administrateur) {
            return self::allKeys();
        }

        // « Accès complet au studio » vaut toutes les permissions du studio.
        return array_values(array_filter(self::allKeys(), fn (string $key) => in_array($key, $actorPermissions, true)
            || (self::coveredByCmsManage($key) && in_array('cms.manage', $actorPermissions, true))));
    }

    /** La permission peut-elle être donnée à un compte de ce rôle ? */
    public static function allowedForRole(string $permission, AdminRole $role): bool
    {
        return ! isset(self::ROLE_RESTRICTED[$permission]) || in_array($role, self::ROLE_RESTRICTED[$permission], true);
    }

    /**
     * Permissions effectives : stockées si définies, sinon preset du rôle (comptes legacy).
     *
     * @return list<string>
     */
    public static function effective(?array $stored, AdminRole $role): array
    {
        $permissions = $stored !== null ? self::sanitize($stored) : self::presetForRole($role);

        return array_values(array_filter($permissions, fn (string $key) => self::allowedForRole($key, $role)));
    }

    /**
     * @return list<string>
     */
    public static function presetForRole(AdminRole $role): array
    {
        return match ($role) {
            AdminRole::Gestionnaire => [
                'dossiers.view',
                'dossiers.process',
                'assures.view',
                'inscriptions.view',
                'exports.export',
                'notifications.receive',
            ],
            AdminRole::Superviseur => [
                'dossiers.view',
                'dossiers.view_all',
                'dossiers.process',
                'dossiers.supervise',
                'dossiers.assign',
                'assures.view',
                'assures.manage',
                'inscriptions.view',
                'inscriptions.validate',
                'exports.export',
                'cms.manage',
                'users.manage',
                'notifications.receive',
            ],
            AdminRole::Administrateur => self::allKeys(),
            AdminRole::Direction => [
                'dossiers.view',
                'dossiers.view_all',
                'assures.view',
                'exports.export',
                'notifications.receive',
            ],
        };
    }

    /**
     * Permission requise pour une route admin (null = accessible à tout compte connecté).
     */
    public static function permissionForRoute(?string $routeName): ?string
    {
        if (! $routeName) {
            return null;
        }

        $map = [
            'admin.dashboard' => null,
            'admin.logout' => null,
            'admin.session.ping' => null,
            'admin.profil' => null,
            'admin.profil.password' => null,
            'admin.profil.2fa' => null,
            'admin.profil.idle' => null,
            'admin.profil.devices' => null,
            'admin.notifications' => null,
            'admin.notifications.read' => null,
            'admin.notifications.read-all' => null,

            'admin.dossiers' => 'dossiers.view',
            'admin.dossiers.assign' => 'dossiers.assign',
            'admin.dossiers.batch-assign' => 'dossiers.assign',
            'admin.dossiers.validate' => 'dossiers.process',
            'admin.dossiers.reject' => 'dossiers.process',
            'admin.dossiers.retrait' => 'dossiers.process',
            'admin.dossiers.complement' => 'dossiers.process',
            'admin.dossiers.en-attente' => 'dossiers.process',
            'admin.dossiers.message' => 'dossiers.process',
            'admin.dossiers.piece.download' => 'dossiers.view',

            'admin.assures' => 'assures.view',
            'admin.assures.statut' => 'assures.manage',

            'admin.inscriptions' => 'inscriptions.view',
            'admin.inscriptions.document' => 'inscriptions.view',
            'admin.inscriptions.identifiers' => 'inscriptions.validate',
            'admin.inscriptions.validate' => 'inscriptions.validate',
            'admin.inscriptions.reject' => 'inscriptions.validate',

            'admin.exports' => 'exports.export',
            'admin.exports.download' => 'exports.export',
            'admin.exports.dossier-pdf' => 'exports.export',

            'admin.parametres' => 'settings.manage',
            'admin.parametres.membres' => 'settings.manage',
            'admin.parametres.photos' => 'settings.manage',
            'admin.parametres.filiations' => 'settings.manage',
            'admin.parametres.fif' => 'settings.manage',
            'admin.parametres.retention' => 'settings.manage',
            'admin.parametres.affectation' => 'settings.manage',
            'admin.parametres.libelles' => 'settings.manage',
            'admin.parametres.structure' => 'settings.manage',
            'admin.parametres.structure.reset' => 'settings.manage',
            'admin.parametres.inscription-documents' => 'settings.manage',

            'admin.utilisateurs' => 'users.manage',
            'admin.utilisateurs.store' => 'users.manage',
            'admin.utilisateurs.update' => 'users.manage',
            'admin.utilisateurs.toggle' => 'users.manage',
            'admin.utilisateurs.resend-invitation' => 'users.manage',
            'admin.utilisateurs.destroy' => 'users.manage',

            'admin.cms.dashboard' => 'cms.dashboard',
            'admin.cms.pages' => 'cms.pages',
            'admin.cms.pages.store' => 'cms.pages',
            'admin.cms.pages.update' => 'cms.pages',
            'admin.cms.pages.destroy' => 'cms.pages',
            'admin.cms.pages.duplicate' => 'cms.pages',
            'admin.cms.pages.status' => 'cms.pages',
            'admin.cms.page_builder' => 'cms.pages',
            'admin.cms.page_builder.preview' => 'cms.pages',
            'admin.cms.page_builder.sections' => 'cms.pages',
            'admin.cms.page_builder.publish' => 'cms.pages',
            'admin.cms.page_builder.versions.restore' => 'cms.pages',
            'admin.cms.page_builder.versions.duplicate' => 'cms.pages',
            'admin.cms.media' => 'cms.media',
            'admin.cms.media.store' => 'cms.media',
            'admin.cms.media.update' => 'cms.media',
            'admin.cms.media.destroy' => 'cms.media',
            'admin.cms.actualites' => 'cms.articles',
            'admin.cms.actualites.store' => 'cms.articles',
            'admin.cms.actualites.update' => 'cms.articles',
            'admin.cms.actualites.destroy' => 'cms.articles',
            'admin.cms.actualites.categories.store' => 'cms.articles',
            'admin.cms.actualites.categories.update' => 'cms.articles',
            'admin.cms.actualites.categories.destroy' => 'cms.articles',
            'admin.cms.faq' => 'cms.faq',
            'admin.cms.faq.store' => 'cms.faq',
            'admin.cms.faq.update' => 'cms.faq',
            'admin.cms.faq.destroy' => 'cms.faq',
            'admin.cms.faq.categories.store' => 'cms.faq',
            'admin.cms.faq.categories.update' => 'cms.faq',
            'admin.cms.faq.categories.destroy' => 'cms.faq',
            'admin.cms.banniere' => 'cms.banniere',
            'admin.cms.banniere.bandeau' => 'cms.banniere',
            'admin.cms.banniere.slides.store' => 'cms.banniere',
            'admin.cms.banniere.slides.update' => 'cms.banniere',
            'admin.cms.banniere.slides.destroy' => 'cms.banniere',
            'admin.cms.chiffres_cles' => 'cms.chiffres',
            'admin.cms.chiffres_cles.store' => 'cms.chiffres',
            'admin.cms.chiffres_cles.update' => 'cms.chiffres',
            'admin.cms.chiffres_cles.destroy' => 'cms.chiffres',
            'admin.cms.ressources' => 'cms.ressources',
            'admin.cms.ressources.store' => 'cms.ressources',
            'admin.cms.ressources.update' => 'cms.ressources',
            'admin.cms.ressources.destroy' => 'cms.ressources',
            'admin.cms.ressources.categories.store' => 'cms.ressources',
            'admin.cms.ressources.categories.destroy' => 'cms.ressources',
            'admin.cms.partenaires' => 'cms.partenaires',
            'admin.cms.partenaires.store' => 'cms.partenaires',
            'admin.cms.partenaires.update' => 'cms.partenaires',
            'admin.cms.partenaires.destroy' => 'cms.partenaires',
            'admin.cms.footer' => 'cms.footer',
            'admin.cms.footer.update' => 'cms.footer',
            'admin.cms.legal' => 'cms.legal',
            'admin.cms.legal.update' => 'cms.legal',
            'admin.cms.legal.reset' => 'cms.legal',
            'admin.cms.identity' => 'cms.footer',
            'admin.cms.identity.logo' => 'cms.footer',
            'admin.cms.identity.logo.reset' => 'cms.footer',
            'admin.cms.identity.theme' => 'cms.footer',
            'admin.cms.identity.theme.reset' => 'cms.footer',
            'admin.cms.menus' => 'cms.menus',
            'admin.cms.menus.pages.store' => 'cms.menus',
            'admin.cms.menus.custom.store' => 'cms.menus',
            'admin.cms.menus.structure' => 'cms.menus',
            'admin.cms.menus.destroy' => 'cms.menus',
            'admin.cms.contacts' => 'cms.contacts',
            'admin.cms.contacts.update' => 'cms.contacts',
            'admin.cms.contacts.settings.recipients' => 'cms.contacts',
            'admin.cms.contacts.archive' => 'cms.contacts',
            'admin.cms.contacts.purge' => 'cms.contacts',
            'admin.cms.contacts.stats.unread' => 'cms.contacts',
            'admin.cms.newsletter' => 'cms.newsletter',
            'admin.cms.newsletter.campaigns.create' => 'cms.newsletter',
            'admin.cms.newsletter.campaigns.edit' => 'cms.newsletter',
            'admin.cms.newsletter.campaigns.store' => 'cms.newsletter',
            'admin.cms.newsletter.campaigns.update' => 'cms.newsletter',
            'admin.cms.newsletter.campaigns.send' => 'cms.newsletter',
            'admin.cms.newsletter.campaigns.destroy' => 'cms.newsletter',
            'admin.cms.newsletter.subscribers.update' => 'cms.newsletter',
            'admin.cms.newsletter.toggle' => 'cms.newsletter',
            'admin.cms.newsletter.clean' => 'cms.newsletter',
            'admin.cms.newsletter.export' => 'cms.newsletter',
            'admin.cms.newsletter.batches.store' => 'cms.newsletter',
            'admin.cms.newsletter.batches.destroy' => 'cms.newsletter',
        ];

        return $map[$routeName] ?? null;
    }

    /**
     * @deprecated Utiliser sanitize() ou effective().
     *
     * @return list<string>
     */
    public static function normalize(?array $permissions, AdminRole $role): array
    {
        if ($permissions === null) {
            return self::presetForRole($role);
        }

        return self::sanitize($permissions);
    }
}
