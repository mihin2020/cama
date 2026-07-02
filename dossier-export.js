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
    let _logoDataUrl = null;

    function loadLogoDataUrl() {
        if (_logoDataUrl !== null) return Promise.resolve(_logoDataUrl);
        return new Promise(resolve => {
            const img = new Image();
            img.crossOrigin = 'anonymous';
            const bases = ['images/logo_cama.png', '../images/logo_cama.png', '/images/logo_cama.png'];
            let i = 0;
            const tryNext = () => {
                if (i >= bases.length) { _logoDataUrl = false; resolve(false); return; }
                img.src = bases[i++];
            };
            img.onload = () => {
                try {
                    const c = document.createElement('canvas');
                    c.width = img.naturalWidth || img.width;
                    c.height = img.naturalHeight || img.height;
                    c.getContext('2d').drawImage(img, 0, 0);
                    _logoDataUrl = c.toDataURL('image/png');
                } catch { _logoDataUrl = false; }
                resolve(_logoDataUrl);
            };
            img.onerror = () => tryNext();
            tryNext();
        });
    }

    function drawFifHeader(doc, W, M, assure, logoData) {
        const y0 = 24;
        const cx = W / 2;
        const lh = 10;
        const leftX = M;
        const rightX = W - M;
        doc.setTextColor(0, 0, 0);

        let yL = y0;
        doc.setFont('helvetica', 'bold'); doc.setFontSize(8);
        doc.text('Ministère de la Guerre et de la Défense Patriotique', leftX, yL); yL += lh;
        doc.setFont('helvetica', 'normal'); doc.setFontSize(7);
        doc.text('**************', leftX, yL); yL += lh;
        doc.text('Secrétariat général', leftX, yL); yL += lh;
        doc.text('*************', leftX, yL); yL += lh;
        doc.setFont('helvetica', 'bold'); doc.setFontSize(8);
        doc.text("Caisse d'Assurance Maladie des Armées", leftX, yL);

        let yR = y0;
        doc.setFont('helvetica', 'bold'); doc.setFontSize(8);
        doc.text('Burkina Faso', rightX, yR, { align: 'right' }); yR += lh;
        doc.setFont('helvetica', 'normal'); doc.setFontSize(7);
        doc.text('**************', rightX, yR, { align: 'right' }); yR += lh;
        doc.setFont('helvetica', 'italic'); doc.setFontSize(7);
        const motto = doc.splitTextToSize('La patrie ou la Mort , Nous vaincrons', 160);
        doc.text(motto, rightX, yR, { align: 'right' });

        const logoW = 72, logoH = 72;
        if (logoData) {
            doc.addImage(logoData, 'PNG', cx - logoW / 2, y0, logoW, logoH);
        }

        let y = Math.max(yL, yR + motto.length * lh, y0 + logoH) + 14;
        doc.setFont('helvetica', 'normal'); doc.setFontSize(7);
        doc.text('CAMA : 11 BP 1174 Ouagadougou CMS/ Secrétariat +226 25 30 81 03/Email :', cx, y, { align: 'center' });
        y += 14;
        doc.setFont('helvetica', 'bold'); doc.setFontSize(9);
        const fifNum = assure.numeroCama || assure.matricule || '……………………………………………………';
        const d = new Date();
        const dateStr = `${String(d.getDate()).padStart(2, '0')}/${String(d.getMonth() + 1).padStart(2, '0')}/${d.getFullYear()}`;
        doc.text(`FICHE D'IDENTIFICATION FAMILLE (FIF) N° : ${fifNum}`, M, y);
        doc.text(`Date : ${dateStr}`, W - M, y, { align: 'right' });
        return y + 18;
    }

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

    /* ------------------------------------------------------------------ *
     * Formulaire officiel complet (sections 1 à 3) — mise en page fidèle
     * au document d'immatriculation / enrôlement des ayants droit CAMA.
     * ------------------------------------------------------------------ */

    function sectionBar(doc, M, y, W, label) {
        doc.setFillColor(240, 237, 237);
        doc.rect(M, y, W - 2 * M, 18, 'F');
        doc.setTextColor.apply(doc, PRIMARY);
        doc.setFont('helvetica', 'bold'); doc.setFontSize(9);
        doc.text(String(label).toUpperCase(), M + 6, y + 12.5);
        return y + 24;
    }

    function kvGrid(doc, M, y, W, H, pairs, cols) {
        const cw = (W - 2 * M) / cols;
        const ch = 30;
        pairs.forEach((p, i) => {
            const col = i % cols;
            if (col === 0 && i > 0) y += ch;
            if (y + ch > H - 40) { doc.addPage(); y = 50; }
            const x = M + col * cw;
            doc.setDrawColor(220, 220, 220);
            doc.rect(x, y, cw, ch);
            doc.setTextColor.apply(doc, GREY);
            doc.setFont('helvetica', 'normal'); doc.setFontSize(6.5);
            doc.text(String(p[0]).toUpperCase(), x + 4, y + 10);
            doc.setTextColor(27, 28, 28);
            doc.setFont('helvetica', 'bold'); doc.setFontSize(8);
            const v = (p[1] === undefined || p[1] === null || p[1] === '') ? '—' : String(p[1]);
            doc.text(doc.splitTextToSize(v, cw - 8).slice(0, 2), x + 4, y + 22);
        });
        return y + ch;
    }

    function famTable(doc, M, y, W, H, columns, rows) {
        const tableW = columns.reduce((s, c) => s + c.w, 0);
        const FONT = 7.5;
        function header() {
            const hH = 26;
            doc.setFillColor(240, 237, 237);
            doc.rect(M, y, tableW, hH, 'F');
            doc.setTextColor.apply(doc, PRIMARY);
            doc.setFont('helvetica', 'bold'); doc.setFontSize(6.8);
            doc.setDrawColor(200, 200, 200);
            let cx = M;
            columns.forEach(c => { doc.rect(cx, y, c.w, hH); doc.text(doc.splitTextToSize(c.title, c.w - 4), cx + 2, y + 9); cx += c.w; });
            y += hH;
        }
        header();
        doc.setFont('helvetica', 'normal'); doc.setTextColor(27, 28, 28); doc.setFontSize(FONT);
        const iter = rows && rows.length ? rows : [null, null];
        iter.forEach((r, ri) => {
            const cellLines = columns.map(c => {
                let v = r === null ? (c.key === 'num' ? String(ri + 1) : '') : c.get(r, ri);
                if (v === undefined || v === null || v === '') v = c.key === 'num' ? String(ri + 1) : '';
                const l = doc.splitTextToSize(String(v), c.w - 4);
                return l.length ? l : [''];
            });
            const maxLines = Math.max(1, ...cellLines.map(l => l.length));
            const rowH = Math.max(16, maxLines * 9 + 6);
            if (y + rowH > H - 40) { doc.addPage(); y = 50; header(); doc.setFont('helvetica', 'normal'); doc.setFontSize(FONT); doc.setTextColor(27, 28, 28); }
            let cx = M;
            doc.setDrawColor(220, 220, 220);
            columns.forEach((c, ci) => { doc.rect(cx, y, c.w, rowH); doc.text(cellLines[ci], cx + 2, y + 11); cx += c.w; });
            y += rowH;
        });
        return y;
    }

    function dateLieu(m) {
        return [m.dateNaissance, m.lieuNaissance].filter(Boolean).join(' à ');
    }

    function buildFormulaireComplet(assure, conjoints, enfants, logoData) {
        ensureLibs();
        const { jsPDF } = global.jspdf;
        const doc = new jsPDF({ unit: 'pt', format: 'a4', orientation: 'landscape' });
        const W = doc.internal.pageSize.getWidth();
        const H = doc.internal.pageSize.getHeight();
        const M = 32;
        let y = drawFifHeader(doc, W, M, assure, logoData);

        y = sectionBar(doc, M, y, W, '1. Informations administratives du militaire');
        y = kvGrid(doc, M, y, W, H, [
            ['Nom', assure.nom], ['Prénom(s)', assure.prenoms || assure.prenom], ['Sexe', assure.sexe], ['Matricule militaire', assure.matricule],
            ['N° informatique', assure.numeroInformatique], ['Grade', assure.grade], ['Catégorie', assure.categorie], ['N° CIM', assure.numeroCim],
            ['N° Carte CAMA', assure.numeroCama], ['N° IUP', assure.numeroIup], ['Armée', assure.armee], ['Région', assure.region],
            ['Corps', assure.corps], ['Service', assure.service], ['Section', assure.section], ['Sous-section', assure.sousSection],
            ['Téléphones', assure.telephones || assure.telephone], ['E-mail', assure.email], ['Personne à prévenir', assure.personneAPrevenir], ['Tél. à prévenir', assure.telPersonneAPrevenir]
        ], 4);
        y += 12;

        y = sectionBar(doc, M, y, W, '2. Conjoint(e)s (marié(s) à la mairie uniquement)');
        y = famTable(doc, M, y, W, H, [
            { title: 'N°', w: 24, key: 'num', get: (r, ri) => ri + 1 },
            { title: 'Nom', w: 70, get: r => r.nom },
            { title: 'Prénoms', w: 78, get: r => r.prenom },
            { title: 'Date et lieu de naissance', w: 96, get: dateLieu },
            { title: 'Sexe', w: 44, get: r => r.sexe },
            { title: 'G.S.', w: 32, get: r => r.groupeSanguin },
            { title: "Réf. document d'identité", w: 88, get: r => r.refIdentite },
            { title: 'Réf. Acte de mariage', w: 84, get: r => r.refActeMariage },
            { title: 'Profession', w: 78, get: r => r.profession },
            { title: 'Lieu de résidence', w: 82, get: r => r.lieuResidence },
            { title: 'Téléphone', w: 72, get: r => r.telephone }
        ], conjoints || []);
        y += 12;

        const ageMax = (global.CamaAssureData && global.CamaAssureData.getAgeMaxEnfant) ? global.CamaAssureData.getAgeMaxEnfant() : 26;
        const parentTitle = 'Nom & prénoms mère (père si pers. féminin)';
        y = sectionBar(doc, M, y, W, `3. Enfant(s) (de 0 à ${ageMax} ans, du plus âgé au plus jeune)`);
        y = famTable(doc, M, y, W, H, [
            { title: 'N°', w: 24, key: 'num', get: (r, ri) => ri + 1 },
            { title: 'Nom', w: 74, get: r => r.nom },
            { title: 'Prénoms', w: 82, get: r => r.prenom },
            { title: 'Date et lieu de naissance', w: 100, get: dateLieu },
            { title: 'Sexe', w: 42, get: r => r.sexe },
            { title: 'G.S.', w: 32, get: r => r.groupeSanguin },
            { title: "Réf. d'identité", w: 92, get: r => r.refIdentite },
            { title: "Réf. Acte scolarité / état civil", w: 104, get: r => r.refActeScolariteEtatCivil },
            { title: parentTitle, w: 96, get: r => r.nomPrenomsParent },
            { title: 'Téléphone', w: 76, get: r => r.telephone }
        ], enfants || []);

        const pages = doc.internal.getNumberOfPages();
        for (let i = 1; i <= pages; i++) {
            doc.setPage(i);
            doc.setDrawColor(229, 189, 187);
            doc.line(M, H - 30, W - M, H - 30);
            doc.setTextColor.apply(doc, GREY);
            doc.setFont('helvetica', 'normal'); doc.setFontSize(7.5);
            doc.text(`CAMA — Formulaire d'enrôlement — ${assure.numeroCama || assure.matricule || ''}`, M, H - 18);
            doc.text(`Généré le ${new Date().toLocaleString('fr-FR')} — page ${i}/${pages}`, W - M, H - 18, { align: 'right' });
        }
        return doc;
    }

    function splitMembres(membres) {
        const list = membres || [];
        return {
            conjoints: list.filter(m => (m.lien || '') === 'Conjoint(e)'),
            enfants: list.filter(m => (m.lien || '').startsWith('Enfant'))
        };
    }

    function exportDossierFamilial(assure, membres) {
        const { conjoints, enfants } = splitMembres(membres);
        return loadLogoDataUrl().then(logo => {
            const doc = buildFormulaireComplet(assure, conjoints, enfants, logo || null);
            doc.save(`Formulaire_${camaTag(assure)}.pdf`);
        });
    }

    // Export d'un dossier individuel à partir de sa référence (back-office).
    function exportDossierByRef(ref) {
        const data = global.CamaAssureData;
        if (!data) throw new Error('Données CAMA indisponibles');
        const d = data.getDossiers().find(x => x.ref === ref);
        if (!d) throw new Error('Dossier introuvable');
        const membre = {
            nom: d.nom, prenom: d.prenom, lien: d.lien, sexe: d.sexe,
            dateNaissance: d.dateNaissance, numeroCama: d.numeroCama, statut: d.statut,
            dossierId: d.ref, dateSoumission: d.dateSoumission, motifRefus: d.motifRefus,
            pieces: d.pieces || []
        };
        const assure = data.getAssureDetailForAdmin ? data.getAssureDetailForAdmin(d.assureNom) : { fullName: d.assureNom, numeroCama: d.numeroCama, matricule: '' };
        const doc = buildPdf(membre, assure, 1);
        doc.save(fileName(assure, membre, 1));
    }

    function exportFormulaireFamilialByAssure(assureNom) {
        const data = global.CamaAssureData;
        if (!data) throw new Error('Données CAMA indisponibles');
        const detail = data.getAssureDetailForAdmin(assureNom);
        const membres = data.getDossiers().filter(d => d.assureNom === assureNom);
        const assure = { ...detail, nom: assureNom.split(' ').slice(-1)[0], prenoms: assureNom.split(' ').slice(0, -1).join(' ') };
        return exportDossierFamilial(assure, membres.map(d => ({
            nom: d.nom, prenom: d.prenom, lien: d.lien, sexe: d.sexe, dateNaissance: d.dateNaissance,
            lieuNaissance: d.lieuNaissance, groupeSanguin: d.groupeSanguin, refIdentite: d.refIdentite,
            refActeMariage: d.refActeMariage, profession: d.profession, lieuResidence: d.lieuResidence,
            telephone: d.telephone, refActeScolariteEtatCivil: d.refActeScolariteEtatCivil, nomPrenomsParent: d.nomPrenomsParent
        })));
    }

    global.CamaDossierExport = {
        fileName, zipName, buildPdf, exportMembre, exportZip,
        buildFormulaireComplet, exportDossierFamilial, exportDossierByRef, loadLogoDataUrl
    };
})(window);
