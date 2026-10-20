<?php

namespace App\Support;

/**
 * Données exportables depuis le centre d'export, regroupées par domaine.
 *
 * Chaque élément correspond à une table : assurés, dossiers et comptes internes. Les colonnes secrètes
 * (SECRET_COLUMNS) ne sortent jamais, quel que soit le format.
 */
class ExportCatalog
{
    /** Mots de passe, jetons et codes à usage unique : jamais exportés. */
    public const SECRET_COLUMNS = [
        'password',
        'remember_token',
        'login_2fa_code',
        'login_2fa_used_codes',
        'email_verification_code',
    ];

    /**
     * @return array<string, array{
     *     group: string, label: string, description: string, table: string,
     *     permission: string, date?: string, labels?: array<string, string>,
     *     extras?: array<string, array{table: string, foreign: string, column: string, label: string}>
     * }>
     */
    public static function datasets(): array
    {
        $assureExtras = [
            'assure_matricule' => ['table' => 'assures', 'foreign' => 'assure_id', 'column' => 'matricule', 'label' => 'Matricule de l\'assuré'],
            'assure_nom' => ['table' => 'assures', 'foreign' => 'assure_id', 'column' => 'nom', 'label' => 'Nom de l\'assuré'],
            'assure_prenom' => ['table' => 'assures', 'foreign' => 'assure_id', 'column' => 'prenom', 'label' => 'Prénom de l\'assuré'],
        ];
        $adminExtras = [
            'admin_nom' => ['table' => 'admin_users', 'foreign' => 'admin_user_id', 'column' => 'nom', 'label' => 'Nom du compte interne'],
            'admin_prenom' => ['table' => 'admin_users', 'foreign' => 'admin_user_id', 'column' => 'prenom', 'label' => 'Prénom du compte interne'],
        ];

        $assures = 'Assurés et dossiers';
        $comptes = 'Comptes internes';

        return [
            'assures' => ['group' => $assures, 'label' => 'Assurés', 'description' => 'Comptes des membres principaux : identité, unité, contacts, statut.', 'table' => 'assures', 'permission' => 'assures.view', 'date' => 'created_at'],
            'dossiers' => ['group' => $assures, 'label' => 'Dossiers d\'enrôlement', 'description' => 'Membres déclarés, statut, gestionnaire, décisions (période : date de soumission).', 'table' => 'dossiers', 'permission' => 'dossiers.view', 'date' => 'date_soumission', 'extras' => $assureExtras, 'labels' => ['lien' => 'Lien de parenté', 'nom' => 'Nom du membre', 'prenom' => 'Prénom du membre']],
            'assure_notifications' => ['group' => $assures, 'label' => 'Notifications des assurés', 'description' => 'Messages envoyés dans l\'espace assuré.', 'table' => 'assure_notifications', 'permission' => 'assures.view', 'date' => 'created_at', 'extras' => $assureExtras],

            'admin_users' => ['group' => $comptes, 'label' => 'Comptes internes', 'description' => 'Agents CAMA : rôle, permissions, état du compte, dernière connexion.', 'table' => 'admin_users', 'permission' => 'users.manage', 'date' => 'created_at'],
            'admin_notifications' => ['group' => $comptes, 'label' => 'Alertes des comptes internes', 'description' => 'Alertes système reçues par les agents.', 'table' => 'admin_notifications', 'permission' => 'users.manage', 'date' => 'created_at', 'extras' => $adminExtras],
        ];
    }

    /** Libellés lisibles des colonnes courantes (en-têtes Excel et CSV ; le SQL garde les noms techniques). */
    public static function columnLabel(string $column): string
    {
        $labels = [
            'id' => 'ID', 'nom' => 'Nom', 'prenom' => 'Prénom', 'sexe' => 'Sexe', 'email' => 'E-mail',
            'telephone' => 'Téléphone', 'grade' => 'Grade', 'matricule' => 'Matricule',
            'numero_cim' => 'N° CIM', 'numero_cama' => 'N° CAMA', 'numero_iup' => 'N° IUP', 'numero_informatique' => 'N° informatique',
            'membre_numero_cama' => 'N° CAMA du membre', 'statut' => 'Statut', 'ref' => 'Référence',
            'lien' => 'Lien', 'date_naissance' => 'Date de naissance', 'gestionnaire' => 'Gestionnaire',
            'date_soumission' => 'Date de soumission', 'date_decision' => 'Date de décision', 'motif_refus' => 'Motif de refus',
            'assure_id' => 'ID assuré', 'admin_user_id' => 'ID compte interne', 'role' => 'Rôle', 'actif' => 'Actif',
            'titre' => 'Titre', 'contenu' => 'Contenu', 'lu' => 'Lu', 'type' => 'Type', 'last_login_at' => 'Dernière connexion',
            'invited_at' => 'Invité le', 'email_verified_at' => 'E-mail vérifié le', 'deux_fa_active' => '2FA activée',
            'created_at' => 'Créé le', 'updated_at' => 'Modifié le',
        ];

        return $labels[$column] ?? ucfirst(str_replace('_', ' ', $column));
    }
}
