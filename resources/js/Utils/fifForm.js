// Champs ajoutés par l'administration (formulaire FIF configurable) : contrôles côté navigateur.
// Le serveur refait les mêmes contrôles (FifSchemaService).

/** Tous les champs d'un ensemble de sections, à plat. */
export function fifFields(sections) {
    return (sections ?? []).flatMap((section) => section.fields ?? []);
}

/** Valeur vide ? (une case à cocher décochée compte comme vide quand elle est obligatoire) */
export function isFifEmpty(field, value) {
    if (field.type === 'checkbox') return !value;
    return value === null || value === undefined || String(value).trim() === '';
}

/** Libellés des champs obligatoires non renseignés. */
export function missingFifFields(sections, values) {
    return fifFields(sections)
        .filter((field) => field.obligatoire && isFifEmpty(field, values?.[field.code]))
        .map((field) => field.libelle);
}
