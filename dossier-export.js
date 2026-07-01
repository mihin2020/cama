/**
 * Export des dossiers d'ayants droit CAMA.
 * - PDF individuel d'un membre
 * - Archive ZIP de l'ensemble des membres rattachés à un assuré
 * Chaque fichier est nommé selon une logique incluant le numéro CAMA de l'assuré.
 * Dépendances (chargées par la page) : jsPDF (window.jspdf), JSZip (window.JSZip).
 */
(function (global) {
    'use strict';

    const PRIMARY = [158, 0, 31];
    const GREY = [92, 64, 63];

    function sanitize(s) {
        return String(s || '')
            .normalize('NFD').replace(/[̀-ͯ]/g, '')
            .replace(/[^A-Za-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '') || 'NA';
    }

    function camaTag(assure) {
        return sanitize(assure && (assure.numeroCama || assure.matricule) || 'CAMA');
    }

    // Nom de fichier : <NUMERO-CAMA-ASSURE>_<SEQ>_<Prenom-Nom>.pdf
    function fileName(assure, membre, seq) {
        const n = String(seq || 1).padStart(2, '0');
        const who = sanitize(`${membre.prenom || ''}-${membre.nom || ''}`);
        return `${camaTag(assure)}_${n}_${who}.pdf`;
    }

    function zipName(assure) {
        return `Dossiers_${camaTag(assure)}.zip`;
    }

    function ensureLibs() {
        if (!global.jspdf || !global.jspdf.jsPDF) throw new Error('jsPDF non chargé');
    }

    function buildPdf(membre, assure, seq) {
        ensureLibs();
        const { jsPDF } = global.jspdf;
        const doc = new jsPDF({ unit: 'pt', format: 'a4' });
        const W = doc.internal.pageSize.getWidth();
        const H = doc.internal.pageSize.getHeight();
        const M = 40;
        let y = 0;

        // En-tête
        doc.setFillColor.apply(doc, PRIMARY);
        doc.rect(0, 0, W, 72, 'F');
        doc.setTextColor(255, 255, 255);
        doc.setFont('helvetica', 'bold'); doc.setFontSize(15);
        doc.text("CAMA — Caisse d'Assurance Maladie des Armées", M, 30);
        doc.setFont('helvetica', 'normal'); doc.setFontSize(10);
        doc.text("Dossier d'enrôlement d'un ayant droit", M, 48);
        doc.setFontSize(9);
        doc.text(`Réf. dossier : ${membre.dossierId || '—'}`, M, 62);
        y = 96;

        function sectionTitle(label) {
            y = ensureSpace(28);
            doc.setFillColor(240, 237, 237);
            doc.rect(M, y - 12, W - 2 * M, 20, 'F');
            doc.setTextColor.apply(doc, PRIMARY);
            doc.setFont('helvetica', 'bold'); doc.setFontSize(10);
            doc.text(label.toUpperCase(), M + 6, y + 2);
            y += 20;
        }

        function row(label, value) {
            y = ensureSpace(16);
            doc.setTextColor.apply(doc, GREY);
            doc.setFont('helvetica', 'normal'); doc.setFontSize(9);
            doc.text(String(label), M + 6, y);
            doc.setTextColor(27, 28, 28);
            doc.setFont('helvetica', 'bold'); doc.setFontSize(9.5);
            const val = (value === undefined || value === null || value === '') ? '—' : String(value);
            const lines = doc.splitTextToSize(val, W - 2 * M - 170);
            doc.text(lines, M + 170, y);
            y += Math.max(14, lines.length * 12);
        }

        function ensureSpace(needed) {
            if (y + needed > H - 50) { doc.addPage(); return 60; }
            return y;
        }

        // Assuré
        sectionTitle('Assuré');
        row('Nom de l\'assuré', assure.fullName || `${assure.prenom || ''} ${assure.nom || ''}`.trim());
        row('Numéro CAMA', assure.numeroCama);
        row('Matricule', assure.matricule);

        // Membre / ayant droit
        sectionTitle('Ayant droit');
        row('Nom', membre.nom);
        row('Prénom(s)', membre.prenom);
        row('Lien de parenté', membre.lien);
        row('Sexe', membre.sexe);
        row('Date de naissance', membre.dateNaissance);
        row('Numéro CAMA du membre', membre.numeroCama);
        row('Statut du dossier', membre.statut);
        row('Date de soumission', membre.dateSoumission);
        if (membre.dateDecision) row('Date de décision', membre.dateDecision);
        if (membre.motifRefus) row('Motif de refus', membre.motifRefus);

        // Pièces
        sectionTitle('Pièces justificatives');
        if (membre.pieces && membre.pieces.length) {
            membre.pieces.forEach(p => row(p.type, p.statut));
        } else {
            row('—', 'Aucune pièce enregistrée');
        }

        // Historique
        sectionTitle('Historique du dossier');
        if (membre.historique && membre.historique.length) {
            membre.historique.forEach(h => row(h.date, h.libelle));
        } else {
            row('—', 'Aucun évènement');
        }

        // Pied de page sur chaque page
        const pages = doc.internal.getNumberOfPages();
        for (let i = 1; i <= pages; i++) {
            doc.setPage(i);
            doc.setDrawColor(229, 189, 187);
            doc.line(M, H - 36, W - M, H - 36);
            doc.setTextColor.apply(doc, GREY);
            doc.setFont('helvetica', 'normal'); doc.setFontSize(8);
            doc.text(`Document généré le ${new Date().toLocaleString('fr-FR')}`, M, H - 22);
            doc.text(`${fileName(assure, membre, seq)} — page ${i}/${pages}`, W - M, H - 22, { align: 'right' });
        }
        return doc;
    }

    function exportMembre(membre, assure, seq) {
        const doc = buildPdf(membre, assure, seq || 1);
        doc.save(fileName(assure, membre, seq || 1));
    }

    async function exportZip(membres, assure) {
        if (!global.JSZip) throw new Error('JSZip non chargé');
        const zip = new global.JSZip();
        membres.forEach((m, i) => {
            const doc = buildPdf(m, assure, i + 1);
            zip.file(fileName(assure, m, i + 1), doc.output('arraybuffer'));
        });
        const blob = await zip.generateAsync({ type: 'blob' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url; a.download = zipName(assure);
        document.body.appendChild(a); a.click(); a.remove();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
    }

    global.CamaDossierExport = { fileName, zipName, buildPdf, exportMembre, exportZip };
})(window);
