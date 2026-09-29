<?php

namespace Database\Seeders;

use App\Enums\AdminRole;
use App\Enums\AssureStatut;
use App\Models\AdminUser;
use App\Models\Assure;
use App\Models\AssureNotification;
use App\Models\Dossier;
use App\Services\AdminNotificationService;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        AdminUser::query()->updateOrCreate(
            ['email' => 'admin@cama.bf'],
            [
                'nom' => 'OUÉDRAOGO',
                'prenom' => 'Awa',
                'grade' => 'Ingénieur',
                'password' => 'Demo2026!',
                'role' => AdminRole::Administrateur,
                'permissions' => \App\Support\AdminPermissions::presetForRole(AdminRole::Administrateur),
                'matricule_interne' => 'INT-0050',
                'actif' => true,
            ]
        );

        AdminUser::query()->updateOrCreate(
            ['email' => 'gestionnaire@cama.bf'],
            [
                'nom' => 'KABORÉ',
                'prenom' => 'Aminata',
                'grade' => 'Lieutenant',
                'password' => 'Demo2026!',
                'role' => AdminRole::Gestionnaire,
                'permissions' => \App\Support\AdminPermissions::presetForRole(AdminRole::Gestionnaire),
                'matricule_interne' => 'INT-0231',
                'actif' => true,
            ]
        );

        AdminUser::query()->updateOrCreate(
            ['email' => 'superviseur@cama.bf'],
            [
                'nom' => 'SAWADOGO',
                'prenom' => 'Paul',
                'grade' => 'Commandant',
                'password' => 'Demo2026!',
                'role' => AdminRole::Superviseur,
                'permissions' => \App\Support\AdminPermissions::presetForRole(AdminRole::Superviseur),
                'matricule_interne' => 'INT-0142',
                'actif' => true,
            ]
        );

        $issouf = Assure::query()->updateOrCreate(
            ['email' => 'issouf.traore@armee.bf'],
            [
                'nom' => 'TRAORÉ',
                'prenom' => 'Issouf',
                'sexe' => 'Masculin',
                'matricule' => '4521-B',
                'numero_informatique' => 'INF-4521',
                'grade' => 'Capitaine',
                'categorie' => 'Officier',
                'numero_cim' => 'CIM-104521',
                'numero_cama' => 'CAMA-104521',
                'numero_iup' => 'IUP-104521',
                'armee' => 'Armée de Terre',
                'region' => '1re Région Militaire (Ouagadougou)',
                'corps' => '11e Régiment d\'Infanterie Commando',
                'service' => 'Service Administratif',
                'section' => 'Section Personnel',
                'sous_section' => 'Bureau Solde',
                'telephone' => '+226 70 12 34 56',
                'personne_a_prevenir' => 'Aïcha TRAORÉ',
                'tel_personne_a_prevenir' => '+226 76 00 11 22',
                'password' => 'Demo2026!',
                'statut' => AssureStatut::Actif,
                'deux_fa_active' => true,
                'journal' => [
                    ['date' => '12/01/2025 08:00', 'libelle' => 'Compte créé'],
                    ['date' => '12/01/2025 09:30', 'libelle' => 'Compte validé par le gestionnaire'],
                ],
            ]
        );

        Assure::query()->updateOrCreate(
            ['email' => 'adama.sanou@armee.bf'],
            [
                'nom' => 'SANOU',
                'prenom' => 'Adama',
                'sexe' => 'Masculin',
                'matricule' => '3310-A',
                'numero_cim' => 'CIM-103310',
                'numero_cama' => 'CAMA-103310',
                'password' => 'Demo2026!',
                'statut' => AssureStatut::Actif,
            ]
        );

        Assure::query()->updateOrCreate(
            ['email' => 'david.kone@armee.bf'],
            [
                'nom' => 'KONÉ',
                'prenom' => 'David',
                'sexe' => 'Masculin',
                'matricule' => '5502-C',
                'numero_cim' => 'CIM-105502',
                'numero_cama' => 'CAMA-105502',
                'password' => 'Demo2026!',
                'statut' => AssureStatut::EnAttenteValidation,
            ]
        );

        Assure::query()->updateOrCreate(
            ['email' => 'salif.nikiema@armee.bf'],
            [
                'nom' => 'NIKIÉMA',
                'prenom' => 'Salif',
                'sexe' => 'Masculin',
                'matricule' => '6118-E',
                'numero_cim' => 'CIM-206118',
                'numero_cama' => 'CAMA-206118',
                'grade' => 'Sergent',
                'categorie' => 'Sous-officier',
                'armee' => 'Armée de Terre',
                'region' => '1re Région Militaire (Ouagadougou)',
                'telephone' => '+226 70 11 22 33',
                'password' => 'Demo2026!',
                'statut' => AssureStatut::EnAttenteValidation,
                'journal' => [['date' => '26/06/2026 09:12', 'libelle' => 'Demande d\'inscription soumise par l\'assuré']],
            ]
        );

        Assure::query()->updateOrCreate(
            ['email' => 'edwige.compaore@armee.bf'],
            [
                'nom' => 'COMPAORÉ',
                'prenom' => 'Edwige',
                'sexe' => 'Féminin',
                'matricule' => '7322-F',
                'numero_cim' => 'CIM-207322',
                'numero_cama' => 'CAMA-207322',
                'grade' => 'Adjudant',
                'categorie' => 'Sous-officier',
                'armee' => 'Armée de l\'Air',
                'region' => '2e Région Militaire (Bobo-Dioulasso)',
                'telephone' => '+226 76 44 55 66',
                'password' => 'Demo2026!',
                'statut' => AssureStatut::EnAttenteValidation,
                'journal' => [['date' => '28/06/2026 16:40', 'libelle' => 'Demande d\'inscription soumise par l\'assuré']],
            ]
        );

        // Compte de démonstration à connexion directe : actif, e-mail déjà
        // vérifié et SANS 2FA -> aucune saisie de code requise (utile quand
        // l'envoi d'e-mails n'est pas disponible, ex. plan gratuit Railway).
        $demo = Assure::query()->updateOrCreate(
            ['email' => 'demo@cama.bf'],
            [
                'nom' => 'SANKARA',
                'prenom' => 'Boureima',
                'sexe' => 'Masculin',
                'matricule' => '0001-D',
                'numero_informatique' => 'INF-0001',
                'grade' => 'Capitaine',
                'categorie' => 'Officier',
                'numero_cim' => 'CIM-100001',
                'numero_cama' => 'CAMA-100001',
                'numero_iup' => 'IUP-100001',
                'armee' => 'Armée de Terre',
                'region' => '1re Région Militaire (Ouagadougou)',
                'telephone' => '+226 70 00 00 00',
                'password' => 'Demo2026!',
                'statut' => AssureStatut::Actif,
                'deux_fa_active' => false,
            ]
        );
        // email_verified_at n'est pas mass-assignable : on le force ici.
        $demo->forceFill(['email_verified_at' => now()])->save();

        $this->seedDossiers($issouf);
        $this->seedNotifications($issouf);

        $notifService = app(AdminNotificationService::class);
        AdminUser::query()->each(fn (AdminUser $admin) => $notifService->seedForUser($admin));

        $this->call(CmsSeeder::class);
    }

    private function seedDossiers(Assure $issouf): void
    {
        $dossiers = [
            [
                'ref' => 'CAMA-2025-88213',
                'nom' => 'TRAORÉ', 'prenom' => 'Aïcha', 'lien' => 'Conjoint(e)', 'sexe' => 'Féminin',
                'statut' => 'Validé', 'gestionnaire' => 'Lt. Aminata KABORÉ',
                'date_naissance' => '1990-04-03',
                'membre_numero_cama' => 'CAMA-104522',
                'date_soumission' => '2025-02-15', 'date_decision' => '2025-03-02',
                'pieces' => [
                    ['type' => 'Acte de mariage', 'statut' => 'Validée'],
                    ['type' => 'Copie CNIB du conjoint', 'statut' => 'Validée'],
                ],
                'wizard_meta' => [
                    'lieu_naissance' => 'Ouagadougou',
                    'groupe_sanguin' => 'O+',
                    'ref_identite' => 'CNIB B0912345',
                    'ref_acte_mariage' => 'AM-2015-0456 (Mairie de Ouaga)',
                    'profession' => 'Enseignante',
                    'lieu_residence' => 'Ouagadougou, secteur 15',
                    'telephone' => '+226 70 55 66 77',
                ],
                'journal' => [
                    ['date' => '15/02/2025 10:02', 'libelle' => 'Dossier soumis par l\'assuré'],
                    ['date' => '20/02/2025 14:30', 'libelle' => 'Affecté à Lt. Aminata KABORÉ'],
                    ['date' => '02/03/2025 09:15', 'libelle' => 'Dossier validé'],
                ],
            ],
            [
                'ref' => 'CAMA-2025-91007',
                'nom' => 'TRAORÉ', 'prenom' => 'Boubacar', 'lien' => 'Enfant biologique', 'sexe' => 'Masculin',
                'statut' => 'Pièce manquante demandée', 'gestionnaire' => 'Lt. Aminata KABORÉ',
                'date_naissance' => '2014-09-22',
                'date_soumission' => '2026-05-01',
                'pieces' => [
                    ['type' => 'Acte de naissance', 'statut' => 'Validée'],
                    ['type' => 'Copie CNIB du parent', 'statut' => 'Validée'],
                    ['type' => 'Certificat médical de scolarité', 'statut' => 'Manquante'],
                ],
                'wizard_meta' => [
                    'lieu_naissance' => 'Ouagadougou',
                    'groupe_sanguin' => 'O+',
                    'ref_identite' => 'Acte naissance N° 2014-3321',
                    'ref_acte_scolarite' => 'Certificat scolarité 2025-2026',
                    'nom_prenoms_parent' => 'Aïcha TRAORÉ',
                    'telephone' => '+226 70 55 66 77',
                ],
                'journal' => [
                    ['date' => '01/05/2026 08:40', 'libelle' => 'Dossier soumis par l\'assuré'],
                    ['date' => '06/05/2026 11:00', 'libelle' => 'Pièce complémentaire demandée : certificat de scolarité'],
                ],
            ],
            [
                'ref' => 'CAMA-2026-12044',
                'nom' => 'OUÉDRAOGO', 'prenom' => 'Marie', 'lien' => 'Parent', 'sexe' => 'Féminin',
                'statut' => 'En instruction', 'gestionnaire' => 'Sgt. Daniel ZONGO',
                'date_naissance' => '1958-11-11',
                'date_soumission' => '2026-05-28',
                'pieces' => [
                    ['type' => 'Acte de naissance de l\'assuré', 'statut' => 'Validée'],
                    ['type' => 'Copie CNIB du parent', 'statut' => 'Validée'],
                ],
                'journal' => [
                    ['date' => '28/05/2026 16:21', 'libelle' => 'Dossier soumis par l\'assuré'],
                    ['date' => '02/06/2026 09:00', 'libelle' => 'Passage en instruction'],
                ],
            ],
            [
                'ref' => 'CAMA-2026-15302',
                'nom' => 'TRAORÉ', 'prenom' => 'Fatoumata', 'lien' => 'Enfant du conjoint', 'sexe' => 'Féminin',
                'statut' => 'Soumis', 'gestionnaire' => 'Non affecté',
                'date_naissance' => '2016-03-14',
                'date_soumission' => '2026-06-12',
                'pieces' => [
                    ['type' => 'Acte de naissance', 'statut' => 'Soumise'],
                    ['type' => 'Acte de mariage avec le parent', 'statut' => 'Soumise'],
                ],
                'journal' => [
                    ['date' => '12/06/2026 13:10', 'libelle' => 'Dossier soumis par l\'assuré'],
                ],
            ],
            [
                'ref' => 'CAMA-2026-20011',
                'nom' => 'TRAORÉ', 'prenom' => 'Salimata', 'lien' => 'Enfant biologique', 'sexe' => 'Féminin',
                'statut' => 'Brouillon', 'gestionnaire' => 'Non affecté',
                'date_naissance' => '2020-01-07',
                'date_soumission' => '2026-06-10',
                'pieces' => [],
                'journal' => [
                    ['date' => '10/06/2026 12:00', 'libelle' => 'Brouillon créé par l\'assuré'],
                ],
            ],
        ];

        foreach ($dossiers as $data) {
            Dossier::query()->updateOrCreate(
                ['ref' => $data['ref']],
                array_merge($data, ['assure_id' => $issouf->id])
            );
        }
    }

    private function seedNotifications(Assure $issouf): void
    {
        $notifs = [
            ['type' => 'creation_compte', 'titre' => 'Création de compte', 'contenu' => 'Votre compte assuré a été créé avec succès.', 'lu' => true, 'lien' => null],
            ['type' => 'validation_compte', 'titre' => 'Validation administrative', 'contenu' => 'Votre compte assuré a été validé. Vous pouvez enrôler vos membres de famille.', 'lu' => true, 'lien' => null],
            ['type' => 'soumission_dossier', 'titre' => 'Dossier soumis', 'contenu' => 'Le dossier de Fatoumata TRAORÉ a été soumis (réf. CAMA-2026-15302).', 'lu' => false, 'lien' => '/espace-assure/ma-famille'],
            ['type' => 'piece_complementaire', 'titre' => 'Pièce complémentaire demandée', 'contenu' => 'Certificat médical de scolarité demandé pour Boubacar TRAORÉ.', 'lu' => false, 'lien' => '/espace-assure/ma-famille'],
            ['type' => 'validation_refus', 'titre' => 'Dossier validé', 'contenu' => 'Le dossier d\'Aïcha TRAORÉ a été validé.', 'lu' => true, 'lien' => '/espace-assure/ma-famille'],
        ];

        foreach ($notifs as $notif) {
            AssureNotification::query()->firstOrCreate(
                ['assure_id' => $issouf->id, 'titre' => $notif['titre']],
                $notif
            );
        }
    }
}
