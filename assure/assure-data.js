/**
 * Données assuré ↔ adminlocalStorage partagé (prototype CAMA).
 */
(function (global) {
    const DOSSIERS_KEY = 'cama_dossiers';
    const ASSURE_NOTIFS_KEY = 'cama_assure_notifs';
    const ASSURE_SESSION_KEY = 'cama_assure_session';
    const ADMIN_SESSION_KEY = 'cama_admin_session';
    const ASSURES_COMPTES_KEY = 'cama_assures_comptes';

    const ASSURE_PROFILE = {
        nom: 'TRAORÉ',
        prenom: 'Issouf',
        fullName: 'Issouf TRAORÉ',
        matricule: '4521-B',
        numeroCama: 'CAMA-104521',
        statut: 'Actif',
        email: 'issouf.traore@armee.bf',
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
            pieces: [{ type: 'Acte de mariage', statut: 'Validée' }, { type: 'Copie CNIB du conjoint', statut: 'Validée' }],
            journal: [{ date: '15/02/2025 10:02', libelle: 'Dossier soumis par l\'assuré' }, { date: '20/02/2025 14:30', libelle: 'Affecté à Lt. Aminata KABORÉ' }, { date: '02/03/2025 09:15', libelle: 'Dossier validé' }],
            messages: [{ auteur: 'assure', texte: 'Bonjour, mon dossier est-il complet ?', date: '16/02/2025 08:00' }, { auteur: 'gestionnaire', texte: 'Bonjour, oui tout est en ordre, en cours d\'instruction.', date: '16/02/2025 09:12' }] },
        { id: 2, ref: 'CAMA-2025-91007', beneficiaire: 'Boubacar TRAORÉ', assureNom: 'Issouf TRAORÉ', lien: 'Enfant biologique', dateSoumission: '2026-05-01', gestionnaire: 'Lt. Aminata KABORÉ', statut: 'Pièce manquante demandée', prenom: 'Boubacar', nom: 'TRAORÉ', sexe: 'Masculin', dateNaissance: '22/09/2014',
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
        if (state.lien === 'Autre' && state.precisionAutre) return `Autre (${state.precisionAutre})`;
        return state.lien;
    }

    function buildPiecesFromWizard(state) {
        return Object.keys(state.pieces || {}).filter(k => state.pieces[k]).map(k => ({
            type: PIECE_LABELS[k] || k,
            statut: 'Soumise'
        }));
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

        return [...names].map((assureNom, index) => {
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
    }

    function updateAssureCompteStatut(assureNom, nouveauStatut, motif) {
        const now = nowFr();
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
            return {
                assureNom,
                matricule: acc?.matricule || '—',
                numeroCama: acc?.numeroCama || '—',
                statutCompte: acc?.statut || '—',
                dossiers: sorted,
                total: sorted.length,
                pending,
                latestDate: sorted[0]?.dateSoumission || ''
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

    function loginAdmin(email, password) {
        const accounts = {
            'gestionnaire@cama.bf': { role: 'gestionnaire', nom: 'Lt. Aminata KABORÉ', password: 'Demo2026!' },
            'superviseur@cama.bf': { role: 'superviseur', nom: 'Cdt. Paul SAWADOGO', password: 'Demo2026!' },
            'admin@cama.bf': { role: 'administrateur', nom: 'Ing. Awa OUÉDRAOGO', password: 'Demo2026!' }
        };
        const acc = accounts[(email || '').trim().toLowerCase()];
        if (!acc || acc.password !== password) return null;
        const session = { email, role: acc.role, nom: acc.nom, at: Date.now() };
        localStorage.setItem(ADMIN_SESSION_KEY, JSON.stringify(session));
        localStorage.setItem('cama_admin_role', acc.role);
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
        window.location.href = path.includes('/admin/cms/') ? '../login.html' : path.includes('/admin/') ? 'login.html' : 'admin/login.html';
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
        updateAssureCompteStatut,
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
        logoutAdmin,
        requireAdminSession
    };
})(window);
