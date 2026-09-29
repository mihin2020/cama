export const STATUS_STYLES = {
    Brouillon: 'bg-surface-container-high text-on-surface-variant',
    Soumis: 'bg-primary/10 text-primary',
    'En instruction': 'bg-tertiary/10 text-tertiary',
    'Pièce manquante demandée': 'bg-tertiary text-on-tertiary',
    'En attente supervision': 'bg-primary-fixed text-on-primary-fixed-variant',
    Validé: 'bg-secondary text-on-secondary',
    Refusé: 'bg-error text-on-error',
};

export const STATUT_BAR_COLORS = {
    Soumis: 'bg-primary',
    'En instruction': 'bg-tertiary',
    'Pièce manquante demandée': 'bg-tertiary',
    'En attente supervision': 'bg-primary-fixed',
    Validé: 'bg-secondary',
    Refusé: 'bg-error',
};

export const NOTIF_ICONS = {
    validation_compte: { icon: 'how_to_reg', color: 'secondary' },
    soumission_dossier: { icon: 'upload_file', color: 'primary' },
    piece_complementaire: { icon: 'description', color: 'tertiary' },
    validation_refus: { icon: 'fact_check', color: 'secondary' },
    message_gestionnaire: { icon: 'forum', color: 'primary' },
    alerte_securite: { icon: 'security', color: 'error' },
    creation_compte: { icon: 'person_add', color: 'primary' },
};

export function statusBadgeClass(statut) {
    return STATUS_STYLES[statut] || 'bg-surface-container-high text-on-surface-variant';
}

export function pieceBadgeClass(statut) {
    const map = {
        Validée: 'bg-secondary/10 text-secondary',
        Manquante: 'bg-tertiary text-on-tertiary',
        Refusée: 'bg-error/10 text-error',
        Soumise: 'bg-primary/10 text-primary',
    };
    return map[statut] || 'bg-surface-container-high text-on-surface-variant';
}

export function activityColor(libelle) {
    if (/validé/i.test(libelle)) return 'secondary';
    if (/refusé/i.test(libelle)) return 'error';
    if (/soumis|complément|pièce/i.test(libelle)) return 'primary';
    return 'tertiary';
}
