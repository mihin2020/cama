/** Informations d'un membre (conjoint, enfant) à afficher à l'administration : libellé + valeur, sans les lignes vides. */
export function beneficiaireInfo(d) {
    const rows = [
        ['Sexe', d.sexe],
        ['Date de naissance', d.dateNaissance],
        ['Lieu de naissance', d.lieuNaissance],
        ['Groupe sanguin', d.groupeSanguin],
        ['Nationalité', d.nationalite],
        ['N° Carte CAMA', d.numeroCama],
        ['Téléphone', d.telephone],
        ["Réf. document d'identité", d.refIdentite],
    ];
    if ((d.lien || '').startsWith('Enfant')) {
        rows.push(['Réf. acte scolarité / état civil', d.refActeScolariteEtatCivil]);
        rows.push(['Nom et prénoms du parent', d.nomPrenomsParent]);
    } else if (d.lien === 'Conjoint(e)') {
        rows.push(['Réf. acte de mariage', d.refActeMariage]);
        rows.push(['Profession', d.profession]);
        rows.push(['Lieu de résidence', d.lieuResidence]);
    }
    return rows.filter(([, v]) => v && String(v).trim() && v !== '—');
}
