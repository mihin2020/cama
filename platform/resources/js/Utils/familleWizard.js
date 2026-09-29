export const PIECE_LABELS = {
    acte_mariage: 'Acte de mariage',
    cnib_conjoint: 'Copie CNIB du conjoint',
    acte_divorce: 'Acte de divorce du précédent conjoint',
    acte_naissance: 'Acte de naissance',
    cnib_parent: 'Copie CNIB du parent',
    certificat_scolarite: 'Certificat de scolarité',
    certificat_tutelle: 'Certificat de tutelle',
    photo_membre: 'Photo du membre',
    acte_naissance_enfant: 'Acte de naissance',
    acte_mariage_parent: 'Acte de mariage avec le parent',
    piece_garde: 'Pièce justifiant la garde',
};

export const MAX_FILE_SIZE = 5 * 1024 * 1024;
export const ACCEPTED_TYPES = ['application/pdf', 'image/jpeg', 'image/png'];
export const PHOTO_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
export const FIF_PIECE_TYPE = 'FIF signée';

function isoToFr(iso) {
    if (!iso) return '';
    const [y, m, d] = iso.split('-');
    return `${d}/${m}/${y}`;
}

let uidSeq = 1;
export const uid = () => `r${uidSeq++}`;

export function newConjoint() {
    return {
        uid: uid(),
        dossier_id: null,
        nom: '',
        prenoms: '',
        dateNaissance: '',
        lieuNaissance: '',
        sexe: '',
        groupeSanguin: '',
        refIdentite: '',
        refActeMariage: '',
        profession: '',
        lieuResidence: '',
        nationalite: '',
        telephone: '',
        photo: null,
        photoPreview: '',
        pieces: {},
        existing: [],
        removedTypes: [],
    };
}

export function newEnfant() {
    return {
        uid: uid(),
        dossier_id: null,
        nom: '',
        prenoms: '',
        dateNaissance: '',
        lieuNaissance: '',
        sexe: '',
        groupeSanguin: '',
        refIdentite: '',
        refActeScolariteEtatCivil: '',
        nomPrenomsParent: '',
        telephone: '',
        filiation: 'Enfant biologique',
        photo: null,
        photoPreview: '',
        pieces: {},
        existing: [],
        removedTypes: [],
    };
}

export function computeAge(iso) {
    if (!iso) return null;
    const dob = new Date(iso);
    const today = new Date();
    let age = today.getFullYear() - dob.getFullYear();
    const m = today.getMonth() - dob.getMonth();
    if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) age--;
    return age;
}

export function pieceMatrixKey(row, list) {
    if (list === 'conjoints') return 'Conjoint(e)';
    return row.filiation || 'Enfant biologique';
}

export function needsCertificatScolarite(row, { ageMax, certificatScolarite }) {
    if (!certificatScolarite?.actif) return false;
    const age = computeAge(row.dateNaissance);
    return age !== null && age >= ageMax;
}

/** Matrice effective (base + certificat scolarité selon âge). */
export function effectivePiecesForRow(row, list, piecesMatrix, options = {}) {
    const key = pieceMatrixKey(row, list);
    const matrix = [...(piecesMatrix[key] || [])];
    if (list === 'enfants' && needsCertificatScolarite(row, options)) {
        const label = options.certificatScolarite?.label || 'Certificat de scolarité';
        if (!matrix.some((p) => p.key === 'certificat_scolarite')) {
            matrix.push({ key: 'certificat_scolarite', label, required: true });
        } else {
            matrix.forEach((p) => {
                if (p.key === 'certificat_scolarite') {
                    p.label = label;
                    p.required = true;
                }
            });
        }
    }
    return matrix;
}

export function photoSettingsForList(list, membrePhoto) {
    const slot = list === 'conjoints' ? membrePhoto?.conjoint : membrePhoto?.enfant;
    return {
        actif: !!slot?.actif,
        required: !!slot?.required,
    };
}

export function existingPhoto(row) {
    return (row.existing || []).find(
        (e) => e.key === 'photo_membre' || e.type === PIECE_LABELS.photo_membre || e.type === 'Photo du membre',
    ) || null;
}

/** Fichiers nouvellement téléversés (instances File) indexés par clé de pièce. */
export function newFilesObject(piecesObj) {
    const out = {};
    Object.keys(piecesObj || {}).forEach((k) => {
        if (piecesObj[k] instanceof File) {
            out[k] = piecesObj[k];
        }
    });
    return out;
}

/** Retrouve une pièce déjà stockée côté serveur correspondant à un emplacement de la matrice. */
export function existingPiece(row, matrixItem) {
    return (row.existing || []).find(
        (e) => (e.key && e.key === matrixItem.key) || e.type === matrixItem.label,
    ) || null;
}

export function rowToPayload(row, list) {
    const pieceFiles = newFilesObject(row.pieces);
    if (row.photo instanceof File) {
        pieceFiles.photo_membre = row.photo;
    }

    const base = {
        id: row.dossier_id || null,
        nom: row.nom,
        prenom: row.prenoms,
        sexe: row.sexe,
        date_naissance: row.dateNaissance || null,
        lieu_naissance: row.lieuNaissance || null,
        groupe_sanguin: row.groupeSanguin || null,
        ref_identite: row.refIdentite || null,
        telephone: row.telephone || null,
        piece_files: pieceFiles,
        removed_pieces: row.removedTypes || [],
    };

    if (list === 'conjoints') {
        return {
            ...base,
            lien: 'Conjoint(e)',
            ref_acte_mariage: row.refActeMariage || null,
            profession: row.profession || null,
            lieu_residence: row.lieuResidence || null,
            nationalite: row.nationalite || null,
        };
    }

    return {
        ...base,
        lien: row.filiation || 'Enfant biologique',
        ref_acte_scolarite: row.refActeScolariteEtatCivil || null,
        nom_prenoms_parent: row.nomPrenomsParent || null,
    };
}

export function validateFamille(state, {
    ageMax,
    piecesMatrix,
    certificatScolarite = { actif: true, label: 'Certificat de scolarité' },
    membrePhoto = { conjoint: { actif: true, required: false }, enfant: { actif: true, required: false } },
    fifSignee = null,
    requireFif = false,
}) {
    const problems = [];
    const options = { ageMax, certificatScolarite };

    const check = (row, list, i) => {
        const label = `${list === 'conjoints' ? 'Conjoint' : 'Enfant'} ${i + 1}`;
        if (!row.nom?.trim() || !row.prenoms?.trim() || !row.dateNaissance || !row.sexe) {
            problems.push(`${label} : nom, prénoms, date de naissance et sexe sont obligatoires.`);
        }

        const photoCfg = photoSettingsForList(list, membrePhoto);
        if (photoCfg.actif && photoCfg.required) {
            const hasNew = row.photo instanceof File;
            const kept = existingPhoto(row) && !(row.removedTypes || []).includes(PIECE_LABELS.photo_membre);
            if (!hasNew && !kept) {
                problems.push(`${label} : photo obligatoire manquante.`);
            }
        }

        effectivePiecesForRow(row, list, piecesMatrix, options)
            .filter((p) => p.required)
            .forEach((p) => {
                const hasNew = !!row.pieces?.[p.key];
                const kept = existingPiece(row, p) && !(row.removedTypes || []).includes(p.label);
                if (!hasNew && !kept) {
                    problems.push(`${label} : pièce obligatoire manquante (${p.label}).`);
                }
            });
    };

    state.conjoints.forEach((r, i) => check(r, 'conjoints', i));
    state.enfants.forEach((r, i) => check(r, 'enfants', i));

    if (requireFif && !fifSignee) {
        problems.push('La FIF signée (scan) est obligatoire pour soumettre le lot familial.');
    }

    return problems;
}

export function buildExportMembresFromState(state) {
    const membres = [];

    state.conjoints.forEach((row) => {
        if (!row.nom?.trim() && !row.prenoms?.trim()) return;
        membres.push({
            nom: row.nom,
            prenom: row.prenoms,
            prenoms: row.prenoms,
            lien: 'Conjoint(e)',
            sexe: row.sexe,
            dateNaissance: isoToFr(row.dateNaissance),
            lieuNaissance: row.lieuNaissance,
            groupeSanguin: row.groupeSanguin,
            refIdentite: row.refIdentite,
            refActeMariage: row.refActeMariage,
            profession: row.profession,
            lieuResidence: row.lieuResidence,
            nationalite: row.nationalite,
            telephone: row.telephone,
        });
    });

    state.enfants.forEach((row) => {
        if (!row.nom?.trim() && !row.prenoms?.trim()) return;
        membres.push({
            nom: row.nom,
            prenom: row.prenoms,
            prenoms: row.prenoms,
            lien: row.filiation || 'Enfant biologique',
            sexe: row.sexe,
            dateNaissance: isoToFr(row.dateNaissance),
            lieuNaissance: row.lieuNaissance,
            groupeSanguin: row.groupeSanguin,
            refIdentite: row.refIdentite,
            refActeScolariteEtatCivil: row.refActeScolariteEtatCivil,
            nomPrenomsParent: row.nomPrenomsParent,
            telephone: row.telephone,
        });
    });

    return membres;
}

export function dossierToConjoint(d) {
    const meta = d.wizard_meta || {};
    return {
        ...newConjoint(),
        dossier_id: d.id,
        nom: d.nom || '',
        prenoms: d.prenom || '',
        sexe: d.sexe || '',
        dateNaissance: meta.date_naissance || d.dateNaissance?.split('/')?.reverse()?.join('-') || '',
        lieuNaissance: d.lieuNaissance || meta.lieu_naissance || '',
        groupeSanguin: d.groupeSanguin || meta.groupe_sanguin || '',
        refIdentite: d.refIdentite || meta.ref_identite || '',
        refActeMariage: d.refActeMariage || meta.ref_acte_mariage || '',
        profession: d.profession || meta.profession || '',
        lieuResidence: d.lieuResidence || meta.lieu_residence || '',
        nationalite: d.nationalite || meta.nationalite || '',
        telephone: d.telephone || meta.telephone || '',
    };
}

export function dossierToEnfant(d) {
    const meta = d.wizard_meta || {};
    const lien = d.lien || 'Enfant biologique';
    return {
        ...newEnfant(),
        dossier_id: d.id,
        nom: d.nom || '',
        prenoms: d.prenom || '',
        sexe: d.sexe || '',
        dateNaissance: meta.date_naissance || d.dateNaissance?.split('/')?.reverse()?.join('-') || '',
        lieuNaissance: d.lieuNaissance || meta.lieu_naissance || '',
        groupeSanguin: d.groupeSanguin || meta.groupe_sanguin || '',
        refIdentite: d.refIdentite || meta.ref_identite || '',
        refActeScolariteEtatCivil: d.refActeScolariteEtatCivil || meta.ref_acte_scolarite || '',
        nomPrenomsParent: d.nomPrenomsParent || meta.nom_prenoms_parent || '',
        telephone: d.telephone || meta.telephone || '',
        filiation: lien,
    };
}
