/**
 * Données assuré ↔ adminlocalStorage partagé (prototype CAMA).
 */
(function (global) {
    const DOSSIERS_KEY = 'cama_dossiers';
    const ASSURE_NOTIFS_KEY = 'cama_assure_notifs';
    const ASSURE_SESSION_KEY = 'cama_assure_session';
    const ADMIN_SESSION_KEY = 'cama_admin_session';
    const ASSURES_COMPTES_KEY = 'cama_assures_comptes';
    const REGISTRATIONS_KEY = 'cama_assure_registrations';
    const ADMIN_NOTIFS_KEY = 'cama_admin_notifs';
    const SETTINGS_KEY = 'cama_settings';

    const ORG_STRUCTURE_KEY = 'cama_org_structure';

    const AGE_MAX_ENFANT_PLAFOND = 26;
    const GESTIONNAIRES_CAMA = [
        'Lt. Aminata KABORÉ',
        'Sgt. Daniel ZONGO',
        'Adj. Rasmané BANCÉ'
    ];
    const ADMIN_ACCOUNTS = {
        'gestionnaire@cama.bf': { role: 'gestionnaire', nom: 'Lt. Aminata KABORÉ', roleLabel: 'Gestionnaire CAMA', id: 'INT-0231', password: 'Demo2026!' },
        'gestionnaire2@cama.bf': { role: 'gestionnaire', nom: 'Sgt. Daniel ZONGO', roleLabel: 'Gestionnaire CAMA', id: 'INT-0288', password: 'Demo2026!' },
        'gestionnaire3@cama.bf': { role: 'gestionnaire', nom: 'Adj. Rasmané BANCÉ', roleLabel: 'Gestionnaire CAMA', id: 'INT-0312', password: 'Demo2026!' },
        'superviseur@cama.bf': { role: 'superviseur', nom: 'Cdt. Paul SAWADOGO', roleLabel: 'Superviseur / Responsable', id: 'INT-0102', password: 'Demo2026!' },
        'admin@cama.bf': { role: 'administrateur', nom: 'Ing. Awa OUÉDRAOGO', roleLabel: 'Administrateur technique', id: 'INT-0050', password: 'Demo2026!' },
        'direction@cama.bf': { role: 'direction', nom: 'Col-Maj. Issa COMPAORÉ', roleLabel: 'Direction Générale', id: 'INT-0001', password: 'Demo2026!' }
    };
    const DOSSIER_OPEN_STATUTS = ['Soumis', 'En instruction', 'Pièce manquante demandée', 'En attente supervision'];
    const DEFAULT_SETTINGS = {
        ageMaxEnfant: 21 // plafond paramétrable, borné à AGE_MAX_ENFANT_PLAFOND
    };

    // Structure militaire de rattachement, configurable côté back-office.
    // Hiérarchie : Région > Corps > Service > Section > Sous-section.
    const DEFAULT_ORG_STRUCTURE = {
        armees: ['Armée de Terre', 'Armée de l\'Air', 'Gendarmerie Nationale', 'Sapeurs-Pompiers Militaires'],
        categories: ['Officier', 'Sous-officier', 'Militaire du rang', 'Personnel civil'],
        groupesSanguins: ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'],
        regions: [
            {
                id: 'reg-1', libelle: '1re Région Militaire (Ouagadougou)',
                corps: [
                    {
                        id: 'corps-11', libelle: '11e Régiment d\'Infanterie Commando',
                        services: [
                            {
                                id: 'srv-adm', libelle: 'Service Administratif',
                                sections: [
                                    { id: 'sec-pers', libelle: 'Section Personnel', sousSections: [{ id: 'ss-solde', libelle: 'Bureau Solde' }, { id: 'ss-effectif', libelle: 'Bureau Effectifs' }] },
                                    { id: 'sec-log', libelle: 'Section Logistique', sousSections: [{ id: 'ss-appro', libelle: 'Bureau Approvisionnement' }] }
                                ]
                            },
                            {
                                id: 'srv-ops', libelle: 'Service Opérations',
                                sections: [{ id: 'sec-instr', libelle: 'Section Instruction', sousSections: [] }]
                            }
                        ]
                    },
                    {
                        id: 'corps-gsp', libelle: 'Groupement de Sécurité et de Protection',
                        services: [{ id: 'srv-secu', libelle: 'Service Sécurité', sections: [] }]
                    }
                ]
            },
            {
                id: 'reg-2', libelle: '2e Région Militaire (Bobo-Dioulasso)',
                corps: [
                    {
                        id: 'corps-21', libelle: '21e Régiment d\'Infanterie Commando',
                        services: [{ id: 'srv-adm2', libelle: 'Service Administratif', sections: [{ id: 'sec-pers2', libelle: 'Section Personnel', sousSections: [] }] }]
                    }
                ]
            }
        ]
    };

    const ASSURE_PROFILE = {
        nom: 'TRAORÉ',
        prenom: 'Issouf',
        prenoms: 'Issouf',
        fullName: 'Issouf TRAORÉ',
        sexe: 'Masculin',
        matricule: '4521-B',
        numeroInformatique: 'INF-4521',
        grade: 'Capitaine',
        categorie: 'Officier',
        numeroCim: 'CIM-104521',
        numeroCama: 'CAMA-104521',
        numeroIup: 'IUP-104521',
        armee: 'Armée de Terre',
        region: '1re Région Militaire (Ouagadougou)',
        corps: '11e Régiment d\'Infanterie Commando',
        service: 'Service Administratif',
        section: 'Section Personnel',
        sousSection: 'Bureau Solde',
        telephones: '+226 70 12 34 56',
        telephone: '+226 70 12 34 56',
        email: 'issouf.traore@armee.bf',
        personneAPrevenir: 'Aïcha TRAORÉ',
        telPersonneAPrevenir: '+226 76 00 11 22',
        statut: 'Actif',
        deuxFA: true,
        dateCreation: '12/01/2025',
        derniereConnexion: '18/06/2026 09:14'
    };

    const ACCOUNT_EVENTS = [
        { date: '12/01/2025 08:00', libelle: 'Compte créé', cat: 'Compte' },
        { date: '12/01/2025 09:30', libelle: 'Compte validé par le service gestionnaire', cat: 'Compte' },
        { date: '18/06/2026 09:14', libelle: 'Connexion réussie', cat: 'Compte' }
    ];

    const SECURITY_LOG = [
        { date: '18/06/2026 09:14', lieu: 'Ouagadougou, BF', appareil: 'Chrome / Windows', suspect: true },
        { date: '10/06/2026 07:50', lieu: 'Ouagadougou, BF', appareil: 'Safari / iPhone', suspect: false },
        { date: '02/06/2026 18:22', lieu: 'Bobo-Dioulasso, BF', appareil: 'Chrome / Windows', suspect: false }
    ];

    const PIECE_LABELS = {
        acte_mariage: 'Acte de mariage',
        cnib_conjoint: 'Copie CNIB du conjoint',
        acte_divorce: 'Acte de divorce du précédent conjoint',
        acte_naissance: 'Acte de naissance',
        cnib_parent: 'Copie CNIB du parent',
        certificat_scolarite: 'Certificat médical de scolarité',
        acte_naissance_enfant: 'Acte de naissance',
        acte_mariage_parent: 'Acte de mariage avec le parent',
        piece_garde: 'Pièce justifiant la garde',
        acte_naissance_assure: 'Acte de naissance de l\'assuré',
        cnib_parent_p: 'Copie CNIB du parent',
        piece_autre: 'Pièces justificatives'
    };

    const DEFAULT_DOSSIERS = [
        { id: 1, ref: 'CAMA-2025-88213', beneficiaire: 'Aïcha TRAORÉ', assureNom: 'Issouf TRAORÉ', lien: 'Conjoint(e)', dateSoumission: '2025-02-15', gestionnaire: 'Lt. Aminata KABORÉ', statut: 'Validé', prenom: 'Aïcha', nom: 'TRAORÉ', sexe: 'Féminin', dateNaissance: '03/04/1990', numeroCama: 'CAMA-104522',
            lieuNaissance: 'Ouagadougou', groupeSanguin: 'O+', refIdentite: 'CNIB B0912345', refActeMariage: 'AM-2015-0456 (Mairie de Ouaga)', profession: 'Enseignante', lieuResidence: 'Ouagadougou, secteur 15', telephone: '+226 70 55 66 77',
            pieces: [{ type: 'Acte de mariage', statut: 'Validée' }, { type: 'Copie CNIB du conjoint', statut: 'Validée' }],
            journal: [{ date: '15/02/2025 10:02', libelle: 'Dossier soumis par l\'assuré' }, { date: '20/02/2025 14:30', libelle: 'Affecté à Lt. Aminata KABORÉ' }, { date: '02/03/2025 09:15', libelle: 'Dossier validé' }],
            messages: [{ auteur: 'assure', texte: 'Bonjour, mon dossier est-il complet ?', date: '16/02/2025 08:00' }, { auteur: 'gestionnaire', texte: 'Bonjour, oui tout est en ordre, en cours d\'instruction.', date: '16/02/2025 09:12' }] },
        { id: 2, ref: 'CAMA-2025-91007', beneficiaire: 'Boubacar TRAORÉ', assureNom: 'Issouf TRAORÉ', lien: 'Enfant biologique', dateSoumission: '2026-05-01', gestionnaire: 'Lt. Aminata KABORÉ', statut: 'Pièce manquante demandée', prenom: 'Boubacar', nom: 'TRAORÉ', sexe: 'Masculin', dateNaissance: '22/09/2014',
            lieuNaissance: 'Ouagadougou', groupeSanguin: 'O+', refIdentite: 'Acte naissance N° 2014-3321', refActeScolariteEtatCivil: 'Certificat scolarité 2025-2026', nomPrenomsParent: 'Aïcha TRAORÉ', telephone: '+226 70 55 66 77',
            pieces: [{ type: 'Acte de naissance', statut: 'Validée' }, { type: 'Copie CNIB du parent', statut: 'Validée' }, { type: 'Certificat médical de scolarité', statut: 'Manquante' }],
            journal: [{ date: '01/05/2026 08:40', libelle: 'Dossier soumis par l\'assuré' }, { date: '06/05/2026 11:00', libelle: 'Pièce complémentaire demandée : certificat de scolarité' }], messages: [] },
        { id: 3, ref: 'CAMA-2026-12044', beneficiaire: 'Marie OUÉDRAOGO', assureNom: 'Issouf TRAORÉ', lien: 'Parent', dateSoumission: '2026-05-28', gestionnaire: 'Sgt. Daniel ZONGO', statut: 'En instruction', prenom: 'Marie', nom: 'OUÉDRAOGO', sexe: 'Féminin', dateNaissance: '11/11/1958',
            pieces: [{ type: 'Acte de naissance de l\'assuré', statut: 'Validée' }, { type: 'Copie CNIB du parent', statut: 'Validée' }],
            journal: [{ date: '28/05/2026 16:21', libelle: 'Dossier soumis par l\'assuré' }, { date: '02/06/2026 09:00', libelle: 'Passage en instruction' }], messages: [] },
        { id: 4, ref: 'CAMA-2026-15302', beneficiaire: 'Fatoumata TRAORÉ', assureNom: 'Issouf TRAORÉ', lien: 'Enfant du conjoint', dateSoumission: '2026-06-12', gestionnaire: 'Non affecté', statut: 'Soumis', prenom: 'Fatoumata', nom: 'TRAORÉ', sexe: 'Féminin', dateNaissance: '14/03/2016',
            pieces: [{ type: 'Acte de naissance', statut: 'Soumise' }, { type: 'Acte de mariage avec le parent', statut: 'Soumise' }],
            journal: [{ date: '12/06/2026 13:10', libelle: 'Dossier soumis par l\'assuré' }], messages: [] },
        { id: 5, ref: 'CAMA-2025-77310', beneficiaire: 'Karim SANOU', assureNom: 'Adama SANOU', lien: 'Autre', dateSoumission: '2025-01-10', gestionnaire: 'Adj. Rasmané BANCÉ', statut: 'Refusé', prenom: 'Karim', nom: 'SANOU', sexe: 'Masculin', dateNaissance: '30/06/2012',
            pieces: [{ type: 'Acte de naissance', statut: 'Refusée' }],
            journal: [{ date: '10/01/2025 09:00', libelle: 'Dossier soumis par l\'assuré' }, { date: '25/01/2025 15:40', libelle: 'Dossier refusé : pièce justifiant la garde non conforme' }],
            messages: [], motifRefus: 'Pièce justifiant la garde non conforme.' },
        { id: 6, ref: 'CAMA-2026-20011', beneficiaire: 'Salimata TRAORÉ', assureNom: 'Issouf TRAORÉ', lien: 'Enfant biologique', dateSoumission: '2026-06-10', gestionnaire: 'Non affecté', statut: 'Brouillon', prenom: 'Salimata', nom: 'TRAORÉ', sexe: 'Féminin', dateNaissance: '07/01/2020',
            pieces: [], journal: [{ date: '10/06/2026 12:00', libelle: 'Brouillon créé par l\'assuré' }], messages: [] },
        { id: 7, ref: 'CAMA-2026-20098', beneficiaire: 'Ousmane KONÉ', assureNom: 'David KONÉ', lien: 'Conjoint(e)', dateSoumission: '2026-06-14', gestionnaire: 'Sgt. Daniel ZONGO', statut: 'En instruction', prenom: 'Ousmane', nom: 'KONÉ', sexe: 'Masculin', dateNaissance: '01/05/1988',
            pieces: [{ type: 'Acte de mariage', statut: 'Validée' }, { type: 'Copie CNIB du conjoint', statut: 'Validée' }],
            journal: [{ date: '14/06/2026 10:00', libelle: 'Dossier soumis' }, { date: '15/06/2026 09:00', libelle: 'Passage en instruction' }], messages: [] },
        { id: 8, ref: 'CAMA-2026-20102', beneficiaire: 'Issa OUATTARA', assureNom: 'Brahima OUATTARA', lien: 'Parent', dateSoumission: '2026-06-15', gestionnaire: 'Adj. Rasmané BANCÉ', statut: 'Soumis', prenom: 'Issa', nom: 'OUATTARA', sexe: 'Masculin', dateNaissance: '15/08/1960',
            pieces: [{ type: 'Acte de naissance de l\'assuré', statut: 'Soumise' }], journal: [{ date: '15/06/2026 14:00', libelle: 'Dossier soumis' }], messages: [] },
        { id: 9, ref: 'CAMA-2026-20155', beneficiaire: 'Aminata SANFO', assureNom: 'Moussa SANFO', lien: 'Enfant biologique', dateSoumission: '2026-06-17', gestionnaire: 'Lt. Aminata KABORÉ', statut: 'En attente supervision', prenom: 'Aminata', nom: 'SANFO', sexe: 'Féminin', dateNaissance: '12/12/2015',
            pieces: [{ type: 'Acte de naissance', statut: 'Validée' }, { type: 'Copie CNIB du parent', statut: 'Validée' }],
            journal: [{ date: '17/06/2026 10:00', libelle: 'Dossier soumis' }, { date: '18/06/2026 11:20', libelle: 'Pré-validation gestionnaireen attente supervision' }], messages: [] }
    ];

    const DEFAULT_ASSURE_NOTIFS = [
        { id: 1, type: 'creation_compte', date: '12/01/2025 08:00', lu: true, titre: 'Création de compte', contenu: 'Votre compte assuré a été créé avec succès.', lien: null },
        { id: 2, type: 'validation_compte', date: '12/01/2025 09:30', lu: true, titre: 'Validation administrative', contenu: 'Votre compte assuré a été validé. Vous pouvez enrôler vos membres de famille.', lien: null },
        { id: 3, type: 'soumission_dossier', date: '12/06/2026 13:10', lu: false, titre: 'Dossier soumis', contenu: 'Le dossier de Fatoumata TRAORÉ a été soumis (réf. CAMA-2026-15302).', lien: 'mes-membres.html#membre-4', dossierRef: 'CAMA-2026-15302' },
        { id: 4, type: 'piece_complementaire', date: '06/05/2026 11:00', lu: false, titre: 'Pièce complémentaire demandée', contenu: 'Certificat médical de scolarité demandé pour Boubacar TRAORÉ.', lien: 'mes-membres.html#membre-2', dossierRef: 'CAMA-2025-91007' },
        { id: 5, type: 'validation_refus', date: '02/03/2025 09:15', lu: true, titre: 'Dossier validé', contenu: 'Le dossier d\'Aïcha TRAORÉ a été validé.', lien: 'mes-membres.html#membre-1', dossierRef: 'CAMA-2025-88213' }
    ];

    const DEFAULT_ASSURE_ACCOUNTS = [
        { assureNom: 'Issouf TRAORÉ', matricule: '4521-B', numeroCama: 'CAMA-104521', statut: 'Actif', dateCreation: '12/01/2025',
            journal: [{ date: '12/01/2025 08:00', libelle: 'Compte créé' }, { date: '12/01/2025 09:30', libelle: 'Compte validé par le gestionnaire' }] },
        { assureNom: 'Adama SANOU', matricule: '3310-A', numeroCama: 'CAMA-103310', statut: 'Actif', dateCreation: '05/11/2024',
            journal: [{ date: '05/11/2024 10:00', libelle: 'Compte créé' }, { date: '06/11/2024 14:00', libelle: 'Compte validé' }] },
        { assureNom: 'David KONÉ', matricule: '5502-C', numeroCama: 'CAMA-105502', statut: 'En attente de validation', dateCreation: '17/06/2026',
            journal: [{ date: '17/06/2026 09:00', libelle: 'Compte créé, en attente de validation manuelle' }] },
        { assureNom: 'Brahima OUATTARA', matricule: '2207-D', numeroCama: 'CAMA-102207', statut: 'Désactivé', dateCreation: '02/03/2023',
            journal: [{ date: '02/03/2023 08:00', libelle: 'Compte créé' }, { date: '14/05/2026 16:00', libelle: 'Compte désactivé (motif : mutation hors service actif)' }] }
    ];

    const DEFAULT_REGISTRATIONS = [
        {
            id: 9001, matricule: '6118-E', nom: 'NIKIÉMA', prenom: 'Salif', prenoms: 'Salif', fullName: 'Salif NIKIÉMA',
            sexe: 'Masculin', telephones: '+226 70 11 22 33', telephone: '+226 70 11 22 33', email: 'salif.nikiema@armee.bf',
            numeroInformatique: 'INF-6118', grade: 'Sergent', categorie: 'Sous-officier',
            numeroCim: 'CIM-206118', numeroIup: 'IUP-206118',
            armee: 'Armée de Terre', region: '1re Région Militaire (Ouagadougou)', corps: '11e Régiment d\'Infanterie Commando',
            service: 'Service Opérations', section: 'Section Instruction', sousSection: '',
            personneAPrevenir: 'Mariam NIKIÉMA', telPersonneAPrevenir: '+226 78 33 44 55',
            password: 'Demo2026!', statut: 'En attente de validation', numeroCama: '', dateCreation: '26/06/2026 09:12',
            journal: [{ date: '26/06/2026 09:12', libelle: 'Demande d\'inscription soumise par l\'assuré' }]
        },
        {
            id: 9002, matricule: '7322-F', nom: 'COMPAORÉ', prenom: 'Edwige', prenoms: 'Edwige', fullName: 'Edwige COMPAORÉ',
            sexe: 'Féminin', telephones: '+226 76 44 55 66', telephone: '+226 76 44 55 66', email: 'edwige.compaore@armee.bf',
            numeroInformatique: 'INF-7322', grade: 'Adjudant', categorie: 'Sous-officier',
            numeroCim: 'CIM-207322', numeroIup: 'IUP-207322',
            armee: 'Armée de l\'Air', region: '2e Région Militaire (Bobo-Dioulasso)', corps: '21e Régiment d\'Infanterie Commando',
            service: 'Service Administratif', section: 'Section Personnel', sousSection: '',
            personneAPrevenir: 'Paul COMPAORÉ', telPersonneAPrevenir: '+226 70 99 88 77',
            password: 'Demo2026!', statut: 'En attente de validation', numeroCama: '', dateCreation: '28/06/2026 16:40',
            journal: [{ date: '28/06/2026 16:40', libelle: 'Demande d\'inscription soumise par l\'assuré' }]
        }
    ];

    function readJson(key, fallback) {
        try {
            const raw = localStorage.getItem(key);
            if (!raw) return JSON.parse(JSON.stringify(fallback));
            return JSON.parse(raw);
        } catch {
            return JSON.parse(JSON.stringify(fallback));
        }
    }

    function writeJson(key, data) {
        localStorage.setItem(key, JSON.stringify(data));
    }

    function nowFr() {
        return new Date().toLocaleString('fr-FR');
    }

    function todayIso() {
        return new Date().toISOString().slice(0, 10);
    }

    function formatDateFr(iso) {
        if (!iso) return null;
        const [y, m, d] = iso.split('-');
        return `${d}/${m}/${y}`;
    }

    function generateRef() {
        return `CAMA-${new Date().getFullYear()}-${Math.floor(10000 + Math.random() * 90000)}`;
    }

    function getAssureProfile() {
        try {
            const session = JSON.parse(localStorage.getItem(ASSURE_SESSION_KEY) || 'null');
            if (session) return { ...ASSURE_PROFILE, ...session };
        } catch { /* ignore */ }
        return { ...ASSURE_PROFILE };
    }

    function getCurrentAssureName() {
        try {
            const session = JSON.parse(localStorage.getItem(ASSURE_SESSION_KEY) || 'null');
            if (session?.fullName) return session.fullName;
        } catch { /* ignore */ }
        return ASSURE_PROFILE.fullName;
    }

    function getDossiers() {
        const stored = readJson(DOSSIERS_KEY, DEFAULT_DOSSIERS);
        return stored.length ? stored : JSON.parse(JSON.stringify(DEFAULT_DOSSIERS));
    }

    function saveDossiers(dossiers) {
        writeJson(DOSSIERS_KEY, dossiers);
    }

    function getDossiersForAssure(assureNom) {
        const name = assureNom || getCurrentAssureName();
        return getDossiers().filter(d => d.assureNom === name);
    }

    function dossierToMembre(d, index) {
        const decisionEntry = [...(d.journal || [])].reverse().find(j =>
            j.libelle.includes('validé') || j.libelle.includes('refusé'));
        return {
            id: d.id,
            nom: d.nom || d.beneficiaire.split(' ').slice(-1)[0],
            prenom: d.prenom || d.beneficiaire.split(' ').slice(0, -1).join(' '),
            lien: d.lien,
            sexe: d.sexe || '—',
            dateNaissance: d.dateNaissance || '—',
            numeroCama: d.numeroCama || '',
            statut: d.statut,
            dossierId: d.ref,
            dateSoumission: formatDateFr(d.dateSoumission) || d.dateSoumission,
            dateDecision: decisionEntry ? decisionEntry.date.split(' ')[0] : null,
            motifRefus: d.motifRefus || null,
            lieuNaissance: d.lieuNaissance || '',
            groupeSanguin: d.groupeSanguin || '',
            refIdentite: d.refIdentite || '',
            telephone: d.telephone || '',
            profession: d.profession || '',
            lieuResidence: d.lieuResidence || '',
            refActeMariage: d.refActeMariage || '',
            refActeScolariteEtatCivil: d.refActeScolariteEtatCivil || '',
            nomPrenomsParent: d.nomPrenomsParent || '',
            pieces: d.pieces || [],
            historique: (d.journal || []).map(j => ({ date: j.date, libelle: j.libelle }))
        };
    }

    function getMembres(assureNom) {
        return getDossiersForAssure(assureNom).map((d, i) => dossierToMembre(d, i));
    }

    function parseFrDate(dateStr) {
        const [datePart, timePart] = (dateStr || '').split(' ');
        const [d, m, y] = datePart.split('/').map(Number);
        const [h, min] = (timePart || '00:00').split(':').map(Number);
        return new Date(y, m - 1, d, h, min);
    }

    function categorizeHistorique(libelle) {
        const l = (libelle || '').toLowerCase();
        if (l.includes('brouillon') || l.includes('membre ajouté')) return 'Membres';
        return 'Dossiers';
    }

    function getHistoriqueEvents(assureNom) {
        const membres = getMembres(assureNom);
        const memberEvents = membres.flatMap(m =>
            (m.historique || []).map(h => ({
                date: h.date,
                libelle: h.libelle,
                cat: categorizeHistorique(h.libelle),
                membre: `${m.prenom} ${m.nom}`
            }))
        );
        const accountEvents = ACCOUNT_EVENTS.map(e => ({ ...e, membre: null }));
        return [...memberEvents, ...accountEvents].sort((a, b) => parseFrDate(b.date) - parseFrDate(a.date));
    }

    function getSecurityLog() {
        return SECURITY_LOG.map(s => ({ ...s }));
    }

    function resolveLien(state) {
        if (state.lien === 'Enfant') return state.enfantType || 'Enfant biologique';
        if (state.lien === 'Parent' && state.lienParentPrecis) return `Parent (${state.lienParentPrecis})`;
        if (state.lien === 'Autre' && state.precisionAutre) return `Autre (${state.precisionAutre})`;
        return state.lien;
    }

    function buildPiecesFromWizard(state) {
        return Object.keys(state.pieces || {}).filter(k => state.pieces[k]).map(k => ({
            type: PIECE_LABELS[k] || k,
            statut: 'Soumise'
        }));
    }

    // Champs issus du formulaire officiel (sections 2 et 3) conservés sur le dossier
    // afin d'alimenter le back-office et l'export PDF.
    const MEMBER_PDF_FIELDS = [
        'lieuNaissance', 'groupeSanguin', 'refIdentite', 'telephone', 'profession',
        'lieuResidence', 'refActeMariage', 'refActeScolariteEtatCivil', 'nomPrenomsParent'
    ];

    function pickMemberFields(state) {
        const out = {};
        MEMBER_PDF_FIELDS.forEach(k => {
            if (state[k] !== undefined && state[k] !== null && state[k] !== '') out[k] = state[k];
        });
        return out;
    }

    function submitDossier(wizardState) {
        const dossiers = getDossiers();
        const ref = generateRef();
        const now = nowFr();
        const lien = resolveLien(wizardState);
        const dossier = {
            id: Math.max(0, ...dossiers.map(d => d.id)) + 1,
            ref,
            beneficiaire: `${wizardState.prenom} ${wizardState.nom}`.trim(),
            prenom: wizardState.prenom,
            nom: wizardState.nom,
            sexe: wizardState.sexe,
            dateNaissance: formatDateFr(wizardState.dateNaissance) || wizardState.dateNaissance,
            numeroCama: wizardState.numeroCamaMembre || '',
            assureNom: getCurrentAssureName(),
            lien,
            dateSoumission: todayIso(),
            gestionnaire: 'Non affecté',
            statut: 'Soumis',
            ...pickMemberFields(wizardState),
            pieces: buildPiecesFromWizard(wizardState),
            journal: [{ date: now, libelle: 'Dossier soumis par l\'assuré' }],
            messages: [],
            wizardMeta: { ...wizardState, pieces: undefined }
        };
        dossiers.unshift(dossier);
        saveDossiers(dossiers);

        addAssureNotification({
            type: 'soumission_dossier',
            titre: 'Dossier soumis',
            contenu: `Le dossier de ${dossier.beneficiaire} a été soumis avec succès (réf. ${ref}).`,
            lien: `mes-membres.html#membre-${dossier.id}`,
            dossierRef: ref
        });

        if (global.CamaAdminShell?.addNotif) {
            global.CamaAdminShell.addNotif({
                type: 'soumission',
                icon: 'upload_file',
                color: 'primary',
                titre: 'Nouvelle soumission',
                contenu: `${dossier.beneficiaire}dossier ${ref} soumis.`,
                date: now,
                lu: false,
                lien: 'dossiers.html'
            });
        }

        return dossier;
    }

    function getAssureComptesOverrides() {
        return readJson(ASSURES_COMPTES_KEY, {});
    }

    function saveAssureCompteOverride(assureNom, patch) {
        const overrides = getAssureComptesOverrides();
        overrides[assureNom] = { ...(overrides[assureNom] || {}), ...patch };
        writeJson(ASSURES_COMPTES_KEY, overrides);
    }

    function getAdminAssures() {
        const overrides = getAssureComptesOverrides();
        const dossiers = getDossiers();
        const names = new Set([
            ...DEFAULT_ASSURE_ACCOUNTS.map(a => a.assureNom),
            ...dossiers.map(d => d.assureNom)
        ]);

        const legacy = [...names].map((assureNom, index) => {
            const base = DEFAULT_ASSURE_ACCOUNTS.find(a => a.assureNom === assureNom) || {
                assureNom,
                matricule: '—',
                numeroCama: '—',
                statut: 'Actif',
                dateCreation: formatDateFr(todayIso()) || todayIso(),
                journal: []
            };
            const merged = { ...base, ...(overrides[assureNom] || {}) };
            return {
                id: index + 1,
                nom: assureNom,
                matricule: merged.matricule,
                numeroCama: merged.numeroCama,
                statut: merged.statut,
                dateCreation: merged.dateCreation,
                journal: merged.journal || [],
                membres: getDossiersForAssure(assureNom).map(d => ({
                    nom: d.beneficiaire,
                    lien: d.lien,
                    statut: d.statut
                }))
            };
        });

        // Comptes issus d'une inscription en ligne déjà validée (ou désactivée).
        const fromRegistrations = getRegistrations()
            .filter(r => r.statut === 'Actif' || r.statut === 'Désactivé')
            .map((r, i) => ({
                id: 90000 + i,
                nom: r.fullName,
                matricule: r.matricule,
                numeroCama: r.numeroCama || '—',
                statut: r.statut,
                dateCreation: r.dateCreation,
                journal: r.journal || [],
                membres: getDossiersForAssure(r.fullName).map(d => ({
                    nom: d.beneficiaire,
                    lien: d.lien,
                    statut: d.statut
                })),
                fromRegistration: true
            }))
            .filter(r => !legacy.some(l => l.nom === r.nom));

        return [...legacy, ...fromRegistrations];
    }

    function updateAssureCompteStatut(assureNom, nouveauStatut, motif) {
        const now = nowFr();

        // Si le compte provient d'une inscription en ligne, on agit sur l'enregistrement.
        const regs = getRegistrations();
        const reg = regs.find(r => r.fullName === assureNom && r.numeroCama);
        if (reg) {
            if (nouveauStatut === 'Désactivé') {
                reg.journal.push({ date: now, libelle: `Compte désactivé (motif : ${motif || 'non précisé'})` });
            } else if (nouveauStatut === 'Actif') {
                reg.journal.push({ date: now, libelle: 'Compte réactivé' });
            }
            reg.statut = nouveauStatut;
            saveRegistrations(regs);
            return getAdminAssures().find(a => a.nom === assureNom);
        }

        const overrides = getAssureComptesOverrides();
        const base = DEFAULT_ASSURE_ACCOUNTS.find(a => a.assureNom === assureNom) || {
            assureNom, matricule: '—', numeroCama: '—', statut: 'Actif', dateCreation: formatDateFr(todayIso()), journal: []
        };
        const current = { ...base, ...(overrides[assureNom] || {}) };
        const journal = [...(current.journal || [])];
        if (nouveauStatut === 'Désactivé') {
            journal.push({ date: now, libelle: `Compte désactivé (motif : ${motif || 'non précisé'})` });
        } else if (nouveauStatut === 'Actif' && current.statut === 'En attente de validation') {
            journal.push({ date: now, libelle: 'Compte validé par le gestionnaire' });
        } else if (nouveauStatut === 'Actif') {
            journal.push({ date: now, libelle: 'Compte réactivé' });
        }
        saveAssureCompteOverride(assureNom, { statut: nouveauStatut, journal });
        return getAdminAssures().find(a => a.nom === assureNom);
    }

    function groupDossiersByAssure(dossiers) {
        const list = dossiers || getDossiers();
        const map = new Map();
        list.forEach(d => {
            if (!map.has(d.assureNom)) map.set(d.assureNom, []);
            map.get(d.assureNom).push(d);
        });
        const accounts = getAdminAssures();
        return [...map.entries()].map(([assureNom, items]) => {
            const acc = accounts.find(a => a.nom === assureNom);
            const sorted = items.slice().sort((a, b) => b.dateSoumission.localeCompare(a.dateSoumission));
            const pending = sorted.filter(d => !['Validé', 'Refusé', 'Brouillon'].includes(d.statut)).length;
            const gestionnaire = sorted.find(d => d.gestionnaire && d.gestionnaire !== 'Non affecté')?.gestionnaire || sorted[0]?.gestionnaire || 'Non affecté';
            const lastJournal = sorted.flatMap(d => (d.journal || []).map(j => ({ ...j, ref: d.ref }))).sort((a, b) => String(b.date).localeCompare(String(a.date)))[0];
            const submitted = sorted.filter(d => d.statut !== 'Brouillon');
            const allValidated = submitted.length > 0 && submitted.every(d => d.statut === 'Validé');
            const hasRefus = submitted.some(d => d.statut === 'Refusé');
            const hasComplement = submitted.some(d => d.statut === 'Pièce manquante demandée');
            const queue = allValidated ? 'valides'
                : hasRefus && !submitted.some(d => !['Validé', 'Refusé', 'Brouillon'].includes(d.statut)) ? 'refuses'
                : hasComplement ? 'complement'
                : submitted.length > 0 ? 'a_traiter' : 'a_traiter';
            return {
                assureNom,
                matricule: acc?.matricule || '—',
                numeroCama: acc?.numeroCama || '—',
                statutCompte: acc?.statut || '—',
                gestionnaire,
                dossiers: sorted,
                total: sorted.length,
                pending,
                latestDate: sorted[0]?.dateSoumission || '',
                lastTraitement: lastJournal?.date || '',
                lastTraitementLibelle: lastJournal?.libelle || '',
                queue
            };
        }).sort((a, b) => (b.latestDate || '').localeCompare(a.latestDate || ''));
    }

    function saveDraftDossier(wizardState) {
        if (!wizardState.prenom?.trim() && !wizardState.nom?.trim()) {
            return { error: 'Indiquez au moins le prénom ou le nom du membre.' };
        }
        const dossiers = getDossiers();
        const now = nowFr();
        const lien = resolveLien(wizardState);
        const beneficiaire = `${wizardState.prenom || ''} ${wizardState.nom || ''}`.trim() || 'Membre sans nom';
        const existingId = wizardState.draftId ? parseInt(wizardState.draftId, 10) : null;
        let dossier = existingId ? dossiers.find(d => d.id === existingId) : null;

        if (dossier) {
            Object.assign(dossier, {
                beneficiaire,
                prenom: wizardState.prenom,
                nom: wizardState.nom,
                sexe: wizardState.sexe,
                dateNaissance: formatDateFr(wizardState.dateNaissance) || wizardState.dateNaissance,
                numeroCama: wizardState.numeroCamaMembre || dossier.numeroCama,
                lien,
                statut: 'Brouillon',
                ...pickMemberFields(wizardState),
                pieces: buildPiecesFromWizard(wizardState),
                wizardMeta: { ...wizardState, pieces: undefined }
            });
            dossier.journal = dossier.journal || [];
            dossier.journal.push({ date: now, libelle: 'Brouillon mis à jour par l\'assuré' });
        } else {
            dossier = {
                id: Math.max(0, ...dossiers.map(d => d.id)) + 1,
                ref: generateRef(),
                beneficiaire,
                prenom: wizardState.prenom,
                nom: wizardState.nom,
                sexe: wizardState.sexe,
                dateNaissance: formatDateFr(wizardState.dateNaissance) || wizardState.dateNaissance,
                numeroCama: wizardState.numeroCamaMembre || '',
                assureNom: getCurrentAssureName(),
                lien,
                dateSoumission: todayIso(),
                gestionnaire: 'Non affecté',
                statut: 'Brouillon',
                ...pickMemberFields(wizardState),
                pieces: buildPiecesFromWizard(wizardState),
                journal: [{ date: now, libelle: 'Brouillon créé par l\'assuré' }],
                messages: [],
                wizardMeta: { ...wizardState, pieces: undefined }
            };
            dossiers.unshift(dossier);
        }
        saveDossiers(dossiers);
        return { dossier, draftId: dossier.id };
    }

    function submitComplement(dossierId, pieceTypes) {
        const dossiers = getDossiers();
        const dossier = dossiers.find(d => d.id === dossierId);
        if (!dossier) return null;
        const now = nowFr();
        const types = pieceTypes?.length ? pieceTypes : dossier.pieces.filter(p => p.statut === 'Manquante').map(p => p.type);

        types.forEach(type => {
            const piece = dossier.pieces.find(p => p.type === type);
            if (piece) piece.statut = 'Soumise';
        });

        const stillMissing = dossier.pieces.some(p => p.statut === 'Manquante');
        dossier.statut = stillMissing ? 'Pièce manquante demandée' : 'En instruction';
        dossier.journal = dossier.journal || [];
        dossier.journal.push({
            date: now,
            libelle: stillMissing
                ? 'Pièce(s) complémentaire(s) partiellement soumise(s)'
                : 'Pièce(s) complémentaire(s) soumise(s)dossier en instruction'
        });
        saveDossiers(dossiers);

        addAssureNotification({
            type: 'soumission_dossier',
            titre: 'Complément envoyé',
            contenu: `Vos pièces pour ${dossier.beneficiaire} ont été transmises (réf. ${dossier.ref}).`,
            lien: `mes-membres.html#membre-${dossier.id}`,
            dossierRef: dossier.ref
        });

        if (global.CamaAdminShell?.addNotif) {
            global.CamaAdminShell.addNotif({
                type: 'soumission',
                icon: 'upload_file',
                color: 'primary',
                titre: 'Complément reçu',
                contenu: `${dossier.beneficiaire}pièces complémentaires pour ${dossier.ref}.`,
                date: now,
                lu: false,
                lien: 'dossiers.html'
            });
        }
        return dossier;
    }

    function getAssureNotifications() {
        return readJson(ASSURE_NOTIFS_KEY, DEFAULT_ASSURE_NOTIFS);
    }

    function saveAssureNotifications(notifs) {
        writeJson(ASSURE_NOTIFS_KEY, notifs);
    }

    function addAssureNotification(notif) {
        const notifs = getAssureNotifications();
        notifs.unshift({
            id: Date.now(),
            lu: false,
            date: nowFr(),
            ...notif
        });
        saveAssureNotifications(notifs);
        return notifs;
    }

    function notifyAssureFromAdminAction(dossier, action, extra) {
        const ref = dossier.ref;
        const name = dossier.beneficiaire;
        const membreLien = `mes-membres.html#membre-${dossier.id}`;

        if (action === 'valider' && dossier.statut === 'Validé') {
            addAssureNotification({
                type: 'validation_refus',
                titre: 'Dossier validé',
                contenu: `Le dossier de ${name} a été validé.`,
                lien: membreLien,
                dossierRef: ref
            });
        } else if (action === 'refuser') {
            addAssureNotification({
                type: 'validation_refus',
                titre: 'Dossier refusé',
                contenu: `Le dossier de ${name} a été refusé : ${extra?.motif || dossier.motifRefus || 'motif non précisé'}.`,
                lien: membreLien,
                dossierRef: ref
            });
        } else if (action === 'complement') {
            addAssureNotification({
                type: 'piece_complementaire',
                titre: 'Pièce complémentaire demandée',
                contenu: `Pièce(s) demandée(s) pour ${name} : ${(extra?.pieces || []).join(', ')}.`,
                lien: membreLien,
                dossierRef: ref
            });
        } else if (action === 'attente') {
            addAssureNotification({
                type: 'soumission_dossier',
                titre: 'Dossier en instruction',
                contenu: `Le dossier de ${name} (${ref}) est en cours d'instruction.`,
                lien: membreLien,
                dossierRef: ref
            });
        } else if (action === 'supervision') {
            addAssureNotification({
                type: 'soumission_dossier',
                titre: 'Dossier en supervision',
                contenu: `Le dossier de ${name} (${ref}) est en attente de validation finale.`,
                lien: membreLien,
                dossierRef: ref
            });
        }
    }

    function getUnreadAssureCount() {
        return getAssureNotifications().filter(n => !n.lu).length;
    }

    function markAssureNotifRead(id) {
        const notifs = getAssureNotifications();
        const n = notifs.find(x => x.id === id);
        if (n) n.lu = true;
        saveAssureNotifications(notifs);
    }

    function markAllAssureNotifsRead() {
        const notifs = getAssureNotifications().map(n => ({ ...n, lu: true }));
        saveAssureNotifications(notifs);
    }

    /* ------------------------------------------------------------------ *
     * Inscription en ligne des assurés + authentification
     * ------------------------------------------------------------------ */

    function getRegistrations() {
        return readJson(REGISTRATIONS_KEY, DEFAULT_REGISTRATIONS);
    }

    function saveRegistrations(list) {
        writeJson(REGISTRATIONS_KEY, list);
    }

    function getPendingRegistrationsCount() {
        return getRegistrations().filter(r => r.statut === 'En attente de validation').length;
    }

    function normalizeEmail(email) {
        return (email || '').trim().toLowerCase();
    }

    // Écrit directement dans le flux de notifications du back-office, afin que
    // l'espace public (où admin-shell.js n'est pas chargé) puisse alerter l'admin.
    function pushAdminNotif(notif) {
        let list;
        try { list = JSON.parse(localStorage.getItem(ADMIN_NOTIFS_KEY) || 'null'); } catch { list = null; }
        if (!Array.isArray(list)) list = [];
        list.unshift({ lu: false, ...notif });
        localStorage.setItem(ADMIN_NOTIFS_KEY, JSON.stringify(list));
    }

    function emailExists(email) {
        const e = normalizeEmail(email);
        if (e === normalizeEmail(ASSURE_PROFILE.email)) return true;
        return getRegistrations().some(r => normalizeEmail(r.email) === e);
    }

    function matriculeExists(matricule) {
        const m = (matricule || '').trim().toLowerCase();
        if (!m) return false;
        if (ASSURE_PROFILE.matricule.toLowerCase() === m) return true;
        if (DEFAULT_ASSURE_ACCOUNTS.some(a => (a.matricule || '').toLowerCase() === m)) return true;
        return getRegistrations().some(r => (r.matricule || '').toLowerCase() === m);
    }

    function generateCamaNumber() {
        return `CAMA-1${Math.floor(10000 + Math.random() * 90000)}`;
    }

    // Convertit un enregistrement d'inscription en profil de session complet
    // (tous les champs présents pour éviter d'hériter des valeurs du compte démo).
    function registrationToProfile(reg) {
        return {
            nom: reg.nom,
            prenom: reg.prenom,
            prenoms: reg.prenoms || reg.prenom,
            fullName: reg.fullName,
            sexe: reg.sexe || '',
            matricule: reg.matricule,
            numeroInformatique: reg.numeroInformatique || '',
            grade: reg.grade || '',
            categorie: reg.categorie || '',
            numeroCim: reg.numeroCim || '',
            numeroCama: reg.numeroCama || '',
            numeroIup: reg.numeroIup || '',
            armee: reg.armee || '',
            region: reg.region || '',
            corps: reg.corps || '',
            service: reg.service || '',
            section: reg.section || '',
            sousSection: reg.sousSection || '',
            telephones: reg.telephones || reg.telephone || '',
            telephone: reg.telephone || reg.telephones || '',
            email: reg.email,
            personneAPrevenir: reg.personneAPrevenir || '',
            telPersonneAPrevenir: reg.telPersonneAPrevenir || '',
            statut: reg.statut,
            deuxFA: true,
            dateCreation: reg.dateCreation
        };
    }

    function registerAssure(data) {
        const required = ['matricule', 'nom', 'prenom', 'email', 'password'];
        const missing = required.filter(k => !String(data[k] || '').trim());
        if (missing.length) return { error: 'Veuillez renseigner tous les champs obligatoires.' };
        if (emailExists(data.email)) return { error: 'Un compte existe déjà avec cette adresse e-mail.', field: 'email' };
        if (matriculeExists(data.matricule)) return { error: 'Ce matricule militaire est déjà enregistré.', field: 'matricule' };

        const regs = getRegistrations();
        const now = nowFr();
        const tel = (data.telephones || data.telephone || '').trim();
        const reg = {
            id: Date.now(),
            matricule: data.matricule.trim(),
            nom: data.nom.trim(),
            prenom: data.prenom.trim(),
            prenoms: data.prenom.trim(),
            fullName: `${data.prenom.trim()} ${data.nom.trim()}`.trim(),
            sexe: data.sexe || '',
            numeroInformatique: (data.numeroInformatique || '').trim(),
            grade: data.grade || '',
            categorie: data.categorie || '',
            numeroCim: (data.numeroCim || '').trim(),
            numeroCama: (data.numeroCama || '').trim(),
            numeroIup: (data.numeroIup || '').trim(),
            armee: data.armee || '',
            region: data.region || '',
            corps: data.corps || '',
            service: data.service || '',
            section: data.section || '',
            sousSection: data.sousSection || '',
            telephones: tel,
            telephone: tel,
            email: data.email.trim(),
            personneAPrevenir: (data.personneAPrevenir || '').trim(),
            telPersonneAPrevenir: (data.telPersonneAPrevenir || '').trim(),
            password: data.password,
            statut: 'En attente de validation',
            dateCreation: now,
            journal: [{ date: now, libelle: 'Demande d\'inscription soumise par l\'assuré' }]
        };
        regs.unshift(reg);
        saveRegistrations(regs);

        pushAdminNotif({
            type: 'compte', icon: 'person_add', color: 'tertiary',
            titre: 'Nouvelle inscription assuré',
            contenu: `${reg.fullName} (matricule ${reg.matricule}) a soumis une demande d'inscription.`,
            date: now,
            lien: 'inscriptions.html'
        });

        return { ok: true, registration: reg, profile: registrationToProfile(reg) };
    }

    function loginAssure(email, password) {
        const e = normalizeEmail(email);
        if (!e || !password) return { error: 'Veuillez saisir votre e-mail et votre mot de passe.' };

        // Compte de démonstration historique.
        if (e === normalizeEmail(ASSURE_PROFILE.email) && password === 'Demo2026!') {
            setAssureSession();
            return { ok: true };
        }

        const reg = getRegistrations().find(r => normalizeEmail(r.email) === e);
        if (!reg) return { error: 'Aucun compte ne correspond à cette adresse e-mail.' };
        if (reg.password !== password) return { error: 'Mot de passe incorrect.' };

        // Un compte encore en attente de validation peut accéder au tableau de bord
        // en accès limité (aucune soumission tant que la CAMA n'a pas validé).
        if (reg.statut === 'Refusé') {
            return { status: 'refused', error: `Votre demande d'inscription a été refusée${reg.motifRefus ? ' : ' + reg.motifRefus : ''}. Contactez la CAMA pour plus d'informations.` };
        }
        if (reg.statut === 'Désactivé') {
            return { status: 'disabled', error: 'Ce compte a été désactivé. Veuillez contacter la CAMA.' };
        }

        setAssureSession(registrationToProfile(reg));
        return { ok: true };
    }

    // Vérifie les identifiants SANS ouvrir de session (préalable à la 2FA).
    function verifyAssureCredentials(email, password) {
        const e = normalizeEmail(email);
        if (!e || !password) return { error: 'Veuillez saisir votre e-mail et votre mot de passe.' };

        if (e === normalizeEmail(ASSURE_PROFILE.email) && password === 'Demo2026!') {
            return { ok: true, profile: null, email: ASSURE_PROFILE.email };
        }
        const reg = getRegistrations().find(r => normalizeEmail(r.email) === e);
        if (!reg) return { error: 'Aucun compte ne correspond à cette adresse e-mail.' };
        if (reg.password !== password) return { error: 'Mot de passe incorrect.' };
        // Les comptes en attente de validation sont autorisés à se connecter (accès limité).
        if (reg.statut === 'Refusé') return { status: 'refused', error: `Votre demande d'inscription a été refusée${reg.motifRefus ? ' : ' + reg.motifRefus : ''}. Contactez la CAMA.` };
        if (reg.statut === 'Désactivé') return { status: 'disabled', error: 'Ce compte a été désactivé. Veuillez contacter la CAMA.' };
        return {
            ok: true,
            email: reg.email,
            pending: reg.statut === 'En attente de validation',
            profile: registrationToProfile(reg)
        };
    }

    function assureAccountExists(email) {
        const e = normalizeEmail(email);
        if (e === normalizeEmail(ASSURE_PROFILE.email)) return true;
        return getRegistrations().some(r => normalizeEmail(r.email) === e);
    }

    function resetAssurePassword(email, newPassword) {
        const e = normalizeEmail(email);
        if (e === normalizeEmail(ASSURE_PROFILE.email)) {
            return { ok: true, demo: true }; // compte de démonstration : non persistant
        }
        const regs = getRegistrations();
        const reg = regs.find(r => normalizeEmail(r.email) === e);
        if (!reg) return { error: 'Aucun compte ne correspond à cette adresse e-mail.' };
        reg.password = newPassword;
        reg.journal.push({ date: nowFr(), libelle: 'Mot de passe réinitialisé par l\'assuré' });
        saveRegistrations(regs);
        return { ok: true };
    }

    function validateRegistration(id) {
        const regs = getRegistrations();
        const reg = regs.find(r => r.id === id);
        if (!reg) return null;
        const now = nowFr();
        if (!reg.numeroCama) reg.numeroCama = generateCamaNumber();
        reg.statut = 'Actif';
        delete reg.motifRefus;
        reg.journal.push({ date: now, libelle: `Inscription validée — N° Carte CAMA ${reg.numeroCama} attribué, compte activé` });
        saveRegistrations(regs);

        // Si l'assuré est connecté (accès limité), on met sa session à jour.
        const session = getAssureSession();
        if (session && normalizeEmail(session.email) === normalizeEmail(reg.email)) {
            setAssureSession(registrationToProfile(reg));
        }
        return reg;
    }

    function rejectRegistration(id, motif) {
        const regs = getRegistrations();
        const reg = regs.find(r => r.id === id);
        if (!reg) return null;
        const now = nowFr();
        reg.statut = 'Refusé';
        reg.motifRefus = motif || 'Non précisé';
        reg.journal.push({ date: now, libelle: `Inscription refusée : ${reg.motifRefus}` });
        saveRegistrations(regs);
        return reg;
    }

    /* ------------------------------------------------------------------ *
     * Paramètres globaux (configurables côté back-office)
     * ------------------------------------------------------------------ */

    function getSettings() {
        return { ...DEFAULT_SETTINGS, ...readJson(SETTINGS_KEY, {}) };
    }

    function saveSettings(patch) {
        const current = getSettings();
        const next = { ...current, ...patch };
        if (typeof next.ageMaxEnfant === 'number') {
            next.ageMaxEnfant = Math.max(1, Math.min(AGE_MAX_ENFANT_PLAFOND, Math.round(next.ageMaxEnfant)));
        }
        writeJson(SETTINGS_KEY, next);
        return next;
    }

    function getAgeMaxEnfant() {
        return getSettings().ageMaxEnfant;
    }

    /* ------------------------------------------------------------------ *
     * Structure militaire de rattachement (Région > Corps > Service >
     * Section > Sous-section), configurable depuis le back-office.
     * ------------------------------------------------------------------ */

    function getOrgStructure() {
        const stored = readJson(ORG_STRUCTURE_KEY, DEFAULT_ORG_STRUCTURE);
        return {
            armees: stored.armees || DEFAULT_ORG_STRUCTURE.armees,
            categories: stored.categories || DEFAULT_ORG_STRUCTURE.categories,
            groupesSanguins: stored.groupesSanguins || DEFAULT_ORG_STRUCTURE.groupesSanguins,
            regions: stored.regions || DEFAULT_ORG_STRUCTURE.regions
        };
    }

    function saveOrgStructure(structure) {
        writeJson(ORG_STRUCTURE_KEY, structure);
        return getOrgStructure();
    }

    function getArmees() {
        return getOrgStructure().armees.slice();
    }

    function getCategories() {
        return getOrgStructure().categories.slice();
    }

    function getGroupesSanguins() {
        return getOrgStructure().groupesSanguins.slice();
    }

    // Détermine si l'assuré est autorisé à soumettre des dossiers.
    // Un compte encore « En attente de validation » peut préparer des brouillons
    // mais ne peut pas soumettre tant que la CAMA n'a pas activé son compte.
    function canSubmitDossiers(profile) {
        const p = profile || getAssureProfile();
        return (p.statut || 'Actif') === 'Actif';
    }

    function updateAssureContact(patch) {
        const session = getAssureSession();
        if (!session) return { error: 'Session expirée. Veuillez vous reconnecter.' };
        const email = normalizeEmail(patch.email || session.email);
        if (!email) return { error: 'Adresse e-mail invalide.' };
        const other = getRegistrations().find(r => normalizeEmail(r.email) === email && normalizeEmail(r.email) !== normalizeEmail(session.email));
        if (other) return { error: 'Cette adresse e-mail est déjà utilisée.' };

        const telephones = (patch.telephones || patch.telephone || session.telephones || session.telephone || '').trim();
        const telPersonneAPrevenir = (patch.telPersonneAPrevenir || session.telPersonneAPrevenir || '').trim();
        const personneAPrevenir = (patch.personneAPrevenir || session.personneAPrevenir || '').trim();
        const numeroCama = (patch.numeroCama !== undefined ? patch.numeroCama : session.numeroCama || '').trim();

        const updated = {
            ...session,
            email,
            telephones,
            telephone: telephones.split('|')[0] || telephones,
            telPersonneAPrevenir,
            personneAPrevenir,
            numeroCama: numeroCama || session.numeroCama
        };
        setAssureSession(updated);

        const regs = getRegistrations();
        const idx = regs.findIndex(r => normalizeEmail(r.email) === normalizeEmail(session.email));
        if (idx >= 0) {
            regs[idx] = { ...regs[idx], email, telephones, telephone: updated.telephone, telPersonneAPrevenir, personneAPrevenir, numeroCama: updated.numeroCama };
            saveRegistrations(regs);
        }
        return { ok: true, profile: getAssureProfile() };
    }

    function getAssureDetailForAdmin(assureNom) {
        const acc = getAdminAssures().find(a => a.nom === assureNom);
        const reg = getRegistrations().find(r => r.fullName === assureNom);
        const override = getAssureComptesOverrides()[assureNom] || {};
        if (!reg && !acc) return null;
        const base = reg ? registrationToProfile(reg) : {};
        return {
            nom: assureNom,
            matricule: override.matricule || acc?.matricule || base.matricule || '—',
            numeroCama: override.numeroCama || acc?.numeroCama || base.numeroCama || '—',
            statut: override.statut || acc?.statut || base.statut || '—',
            email: override.email || base.email || '—',
            sexe: override.sexe || base.sexe || '',
            grade: override.grade || base.grade || '',
            categorie: override.categorie || base.categorie || '',
            numeroInformatique: override.numeroInformatique || base.numeroInformatique || '',
            numeroCim: override.numeroCim || base.numeroCim || '',
            numeroIup: override.numeroIup || base.numeroIup || '',
            armee: override.armee || base.armee || '',
            region: override.region || base.region || '',
            corps: override.corps || base.corps || '',
            service: override.service || base.service || '',
            section: override.section || base.section || '',
            sousSection: override.sousSection || base.sousSection || '',
            telephones: override.telephones || base.telephones || base.telephone || '—',
            personneAPrevenir: override.personneAPrevenir || base.personneAPrevenir || '—',
            telPersonneAPrevenir: override.telPersonneAPrevenir || base.telPersonneAPrevenir || '—',
            dateCreation: acc?.dateCreation || base.dateCreation || '—'
        };
    }

    function getGestionnairesCama() {
        return [...GESTIONNAIRES_CAMA];
    }

    function getGestionnaireCharge(dossiers) {
        const list = dossiers || getDossiers();
        const today = new Date();
        const retardJours = 14;
        return GESTIONNAIRES_CAMA.map(nom => {
            const mine = list.filter(d => d.gestionnaire === nom && d.statut !== 'Brouillon');
            const assignes = mine.length;
            const nonTraites = mine.filter(d => DOSSIER_OPEN_STATUTS.includes(d.statut)).length;
            const valides = mine.filter(d => d.statut === 'Validé').length;
            const refuses = mine.filter(d => d.statut === 'Refusé').length;
            const retard = mine.filter(d => {
                if (!DOSSIER_OPEN_STATUTS.includes(d.statut)) return false;
                const parts = (d.dateSoumission || '').split('-').map(Number);
                if (parts.length < 3) return false;
                const soumis = new Date(parts[0], parts[1] - 1, parts[2]);
                return (today - soumis) / 86400000 > retardJours;
            }).length;
            return {
                nom,
                assignes,
                nonTraites,
                valides,
                refuses,
                ouverts: nonTraites,
                traites: valides + refuses,
                retard
            };
        });
    }

    function assignDossiersToGestionnaire(assureNom, gestionnaire) {
        const dossiers = getDossiers();
        const now = nowFr();
        let count = 0;
        dossiers.forEach(d => {
            if (d.assureNom === assureNom) {
                d.gestionnaire = gestionnaire;
                d.journal = d.journal || [];
                d.journal.push({ date: now, libelle: `Dossier familial affecté à ${gestionnaire}` });
                count++;
            }
        });
        if (count) saveDossiers(dossiers);
        return count;
    }

    function updateAssureProfileAdmin(assureNom, patch) {
        if (!assureNom || !patch) return { error: 'Données invalides.' };
        const now = nowFr();
        const nameParts = assureNom.trim().split(/\s+/);
        const nom = patch.nom || nameParts[nameParts.length - 1] || '';
        const prenom = patch.prenom || nameParts.slice(0, -1).join(' ') || '';
        const fullName = patch.fullName || `${prenom} ${nom}`.trim() || assureNom;

        const fields = {
            nom, prenom, prenoms: prenom, fullName,
            sexe: patch.sexe || '',
            matricule: (patch.matricule || '').trim(),
            numeroCama: (patch.numeroCama || '').trim(),
            numeroInformatique: (patch.numeroInformatique || '').trim(),
            grade: patch.grade || '',
            categorie: patch.categorie || '',
            numeroCim: (patch.numeroCim || '').trim(),
            numeroIup: (patch.numeroIup || '').trim(),
            armee: patch.armee || '',
            region: patch.region || '',
            corps: patch.corps || '',
            service: patch.service || '',
            section: patch.section || '',
            sousSection: patch.sousSection || '',
            email: normalizeEmail(patch.email || ''),
            telephones: (patch.telephones || patch.telephone || '').trim(),
            personneAPrevenir: (patch.personneAPrevenir || '').trim(),
            telPersonneAPrevenir: (patch.telPersonneAPrevenir || '').trim()
        };
        fields.telephone = fields.telephones.split('|')[0] || fields.telephones;

        const regs = getRegistrations();
        const regIdx = regs.findIndex(r => r.fullName === assureNom);
        if (regIdx >= 0) {
            if (fields.email && regs.some((r, i) => i !== regIdx && normalizeEmail(r.email) === fields.email)) {
                return { error: 'Cette adresse e-mail est déjà utilisée.' };
            }
            regs[regIdx] = { ...regs[regIdx], ...fields, fullName };
            if (patch.statut) regs[regIdx].statut = patch.statut;
            regs[regIdx].journal = regs[regIdx].journal || [];
            regs[regIdx].journal.push({ date: now, libelle: 'Profil modifié par un agent CAMA' });
            saveRegistrations(regs);
        }

        const prevOverride = getAssureComptesOverrides()[assureNom] || {};
        const journal = [...(prevOverride.journal || []), { date: now, libelle: 'Profil assuré mis à jour par le back-office' }];
        const profileOverride = {
            ...prevOverride,
            ...fields,
            statut: patch.statut || prevOverride.statut,
            journal
        };

        if (fullName !== assureNom) {
            const dossiers = getDossiers();
            let renamed = false;
            dossiers.forEach(d => {
                if (d.assureNom === assureNom) {
                    d.assureNom = fullName;
                    renamed = true;
                }
            });
            if (renamed) saveDossiers(dossiers);

            const overrides = getAssureComptesOverrides();
            if (overrides[assureNom]) {
                delete overrides[assureNom];
                writeJson(ASSURES_COMPTES_KEY, overrides);
            }
            saveAssureCompteOverride(fullName, profileOverride);
        } else {
            saveAssureCompteOverride(assureNom, profileOverride);
        }

        const session = getAssureSession();
        if (session && (session.fullName === assureNom || `${session.prenom} ${session.nom}`.trim() === assureNom)) {
            setAssureSession({ ...session, ...fields, fullName, statut: patch.statut || session.statut });
        }

        return { ok: true, profile: getAssureDetailForAdmin(fullName) };
    }

    function setAssureSession(profile) {
        const session = { ...ASSURE_PROFILE, ...profile, derniereConnexion: nowFr() };
        localStorage.setItem(ASSURE_SESSION_KEY, JSON.stringify(session));
    }

    function getAssureSession() {
        try {
            return JSON.parse(localStorage.getItem(ASSURE_SESSION_KEY) || 'null');
        } catch {
            return null;
        }
    }

    function logoutAssure() {
        localStorage.removeItem(ASSURE_SESSION_KEY);
    }

    function requireAssureSession() {
        if (getAssureSession()) return true;
        window.location.href = '../espace-assure.html';
        return false;
    }

    function getAdminEffectiveRole() {
        return localStorage.getItem('cama_admin_role') || getAdminSession()?.role || 'gestionnaire';
    }

    function isAdminGestionnaireView() {
        return getAdminEffectiveRole() === 'gestionnaire';
    }

    function getAdminGestionnaireNom() {
        if (!isAdminGestionnaireView()) return null;
        const session = getAdminSession();
        if (session?.email) {
            const acc = ADMIN_ACCOUNTS[session.email.trim().toLowerCase()];
            if (acc?.role === 'gestionnaire' && acc.nom) return acc.nom;
        }
        if (session?.role === 'gestionnaire' && session.nom) return session.nom;
        return localStorage.getItem('cama_demo_gestionnaire_nom') || GESTIONNAIRES_CAMA[0] || null;
    }

    function getAdminIdentity() {
        const session = getAdminSession();
        const effectiveRole = getAdminEffectiveRole();
        const ROLE_LABELS = {
            gestionnaire: 'Gestionnaire CAMA',
            superviseur: 'Superviseur / Responsable',
            administrateur: 'Administrateur technique',
            direction: 'Direction Générale'
        };
        const DEMO_IDS = {
            gestionnaire: 'INT-0231',
            superviseur: 'INT-0102',
            administrateur: 'INT-0050',
            direction: 'INT-0001'
        };
        const DEMO_NOMS = {
            gestionnaire: getAdminGestionnaireNom() || GESTIONNAIRES_CAMA[0],
            superviseur: 'Cdt. Paul SAWADOGO',
            administrateur: 'Ing. Awa OUÉDRAOGO',
            direction: 'Col-Maj. Issa COMPAORÉ'
        };

        if (session?.email) {
            const acc = ADMIN_ACCOUNTS[session.email.trim().toLowerCase()];
            if (acc) {
                return {
                    nom: acc.nom,
                    email: session.email,
                    role: acc.role,
                    roleLabel: acc.roleLabel,
                    id: acc.id,
                    lastLogin: session.at ? new Date(session.at).toLocaleString('fr-FR') : '—'
                };
            }
        }

        return {
            nom: DEMO_NOMS[effectiveRole] || DEMO_NOMS.gestionnaire,
            email: session?.email || '—',
            role: effectiveRole,
            roleLabel: ROLE_LABELS[effectiveRole] || effectiveRole,
            id: DEMO_IDS[effectiveRole] || '—',
            lastLogin: session?.at ? new Date(session.at).toLocaleString('fr-FR') : '—'
        };
    }

    function getGestionnaireWorkload(nom) {
        const charge = getGestionnaireCharge().find(g => g.nom === nom);
        return charge || { nom, assignes: 0, nonTraites: 0, valides: 0, refuses: 0, retard: 0, traites: 0 };
    }

    function computeDelaiMoyenJours(dossiers) {
        const traites = (dossiers || []).filter(d => ['Validé', 'Refusé'].includes(d.statut) && d.dateSoumission);
        if (!traites.length) return 0;
        const today = new Date();
        let total = 0;
        let n = 0;
        traites.forEach(d => {
            const parts = d.dateSoumission.split('-').map(Number);
            if (parts.length < 3) return;
            const soumis = new Date(parts[0], parts[1] - 1, parts[2]);
            const lastEntry = [...(d.journal || [])].reverse().find(j => /validé|refusé/i.test(j.libelle || ''));
            let fin = today;
            if (lastEntry?.date) {
                const m = String(lastEntry.date).match(/(\d{2})\/(\d{2})\/(\d{4})/);
                if (m) fin = new Date(+m[3], +m[2] - 1, +m[1]);
            }
            total += Math.max(0, (fin - soumis) / 86400000);
            n++;
        });
        return n ? Math.round((total / n) * 10) / 10 : 0;
    }

    function getDashboardStats() {
        const allDossiers = getDossiers();
        const dossiers = allDossiers.filter(d => d.statut !== 'Brouillon');
        const assures = getAdminAssures();
        const byStatut = {};
        dossiers.forEach(d => { byStatut[d.statut] = (byStatut[d.statut] || 0) + 1; });

        const enAttente = dossiers.filter(d => DOSSIER_OPEN_STATUTS.includes(d.statut)).length;
        const valides = byStatut['Validé'] || 0;
        const refuses = byStatut['Refusé'] || 0;
        const total = dossiers.length;
        const tauxValidation = total ? Math.round((valides / total) * 1000) / 10 : 0;
        const nonAffectes = dossiers.filter(d => !d.gestionnaire || d.gestionnaire === 'Non affecté').length;
        const familles = groupDossiersByAssure(allDossiers).length;
        const famillesATraiter = groupDossiersByAssure(allDossiers).filter(g => g.queue === 'a_traiter').length;
        const charges = getGestionnaireCharge(allDossiers);
        const totalRetard = charges.reduce((s, g) => s + g.retard, 0);
        const totalAssignes = charges.reduce((s, g) => s + g.assignes, 0);

        const priority = dossiers
            .filter(d => DOSSIER_OPEN_STATUTS.includes(d.statut))
            .sort((a, b) => a.dateSoumission.localeCompare(b.dateSoumission))
            .slice(0, 6)
            .map(d => ({ ref: d.ref, nom: d.beneficiaire, statut: d.statut, id: d.id }));

        const recentActivity = dossiers
            .flatMap(d => (d.journal || []).map(j => ({
                ref: d.ref,
                beneficiaire: d.beneficiaire,
                libelle: j.libelle,
                date: j.date,
                statut: d.statut
            })))
            .sort((a, b) => String(b.date).localeCompare(String(a.date)))
            .slice(0, 8);

        return {
            assuresTotal: assures.length,
            assuresActifs: assures.filter(a => a.statut === 'Actif').length,
            inscriptionsEnAttente: getPendingRegistrationsCount(),
            dossiersEnAttente: enAttente,
            dossiersValides: valides,
            dossiersRefuses: refuses,
            dossiersTotal: total,
            tauxValidation,
            nonAffectes,
            famillesDossiers: familles,
            famillesATraiter,
            delaiMoyenJours: computeDelaiMoyenJours(dossiers),
            totalRetard,
            totalAssignes,
            byStatut,
            charges,
            priority,
            recentActivity
        };
    }

    function getDossiersForAdminView() {
        const all = getDossiers();
        if (!isAdminGestionnaireView()) return all;
        const nom = getAdminGestionnaireNom();
        if (!nom) return [];
        return all.filter(d => d.gestionnaire === nom);
    }

    function loginAdmin(email, password) {
        const acc = ADMIN_ACCOUNTS[(email || '').trim().toLowerCase()];
        if (!acc || acc.password !== password) return null;
        const session = { email: (email || '').trim(), role: acc.role, nom: acc.nom, at: Date.now() };
        localStorage.setItem(ADMIN_SESSION_KEY, JSON.stringify(session));
        localStorage.setItem('cama_admin_role', acc.role);
        if (acc.role === 'gestionnaire') {
            localStorage.setItem('cama_demo_gestionnaire_nom', acc.nom);
            localStorage.setItem('cama_admin_affect_tab', 'tous');
        }
        return session;
    }

    function getAdminSession() {
        try {
            return JSON.parse(localStorage.getItem(ADMIN_SESSION_KEY) || 'null');
        } catch {
            return null;
        }
    }

    function logoutAdmin() {
        localStorage.removeItem(ADMIN_SESSION_KEY);
        localStorage.removeItem('cama_admin_role');
    }

    function requireAdminSession() {
        const path = window.location.pathname.replace(/\\/g, '/');
        if (path.includes('login.html')) return true;
        if (getAdminSession()) return true;
        window.location.replace(path.includes('/admin/cms/') ? '../login.html' : path.includes('/admin/') ? 'login.html' : 'admin/login.html');
        return false;
    }

    global.CamaAssureData = {
        DOSSIERS_KEY,
        ASSURE_NOTIFS_KEY,
        getAssureProfile,
        getCurrentAssureName,
        getDossiers,
        saveDossiers,
        getDossiersForAssure,
        getMembres,
        getHistoriqueEvents,
        getSecurityLog,
        submitDossier,
        saveDraftDossier,
        submitComplement,
        getAdminAssures,
        groupDossiersByAssure,
        getAssureDetailForAdmin,
        getGestionnairesCama,
        getGestionnaireCharge,
        assignDossiersToGestionnaire,
        updateAssureContact,
        updateAssureProfileAdmin,
        updateAssureCompteStatut,
        getRegistrations,
        getPendingRegistrationsCount,
        registerAssure,
        loginAssure,
        verifyAssureCredentials,
        assureAccountExists,
        resetAssurePassword,
        validateRegistration,
        rejectRegistration,
        getSettings,
        saveSettings,
        getAgeMaxEnfant,
        AGE_MAX_ENFANT_PLAFOND,
        getOrgStructure,
        saveOrgStructure,
        getArmees,
        getCategories,
        getGroupesSanguins,
        canSubmitDossiers,
        registrationToProfile,
        getAssureNotifications,
        saveAssureNotifications,
        addAssureNotification,
        notifyAssureFromAdminAction,
        getUnreadAssureCount,
        markAssureNotifRead,
        markAllAssureNotifsRead,
        setAssureSession,
        getAssureSession,
        logoutAssure,
        requireAssureSession,
        loginAdmin,
        getAdminSession,
        getAdminIdentity,
        getGestionnaireWorkload,
        getDashboardStats,
        getAdminEffectiveRole,
        isAdminGestionnaireView,
        getAdminGestionnaireNom,
        getDossiersForAdminView,
        logoutAdmin,
        requireAdminSession
    };
})(window);
