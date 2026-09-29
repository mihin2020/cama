/**
 * Export FIF CAMA — reproduction fidèle du formulaire officiel
 * (Fiche d'Identification Famille, réf. Ministère Défense / CAMA).
 *
 * - PDF familial complet (sections 1 à 3 + signatures)
 * - PDF individuel par membre (même mise en page, une ligne remplie)
 * - Archive ZIP groupée
 *
 * Dépendances : jsPDF (window.jspdf), JSZip (window.JSZip).
 */
(function (global) {
    'use strict';

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

    function ensureLibs() {
        if (!global.jspdf || !global.jspdf.jsPDF) throw new Error('jsPDF non chargé');
    }

    function sanitize(s) {
        return String(s || '')
            .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
            .replace(/[^A-Za-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '') || 'NA';
    }

    function camaTag(assure) {
        return sanitize(assure && (assure.numeroCama || assure.matricule) || 'CAMA');
    }

    function fileName(assure, membre, seq) {
        const n = String(seq || 1).padStart(2, '0');
        const who = sanitize(`${membre.prenom || ''}-${membre.nom || ''}`);
        return `${camaTag(assure)}_${n}_${who}.pdf`;
    }

    function zipName(assure) {
        return `Dossiers_${camaTag(assure)}.zip`;
    }

    function dots(val, len) {
        const s = String(val == null ? '' : val).trim();
        if (s) return s;
        return '…'.repeat(Math.max(8, len || 12));
    }

    function parseFrDate(dateStr) {
        if (!dateStr) return null;
        const m = String(dateStr).match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
        if (!m) return null;
        return new Date(parseInt(m[3], 10), parseInt(m[2], 10) - 1, parseInt(m[1], 10));
    }

    function sortEnfantsByAge(enfants) {
        return [...(enfants || [])].sort((a, b) => {
            const da = parseFrDate(a.dateNaissance);
            const db = parseFrDate(b.dateNaissance);
            if (!da && !db) return 0;
            if (!da) return 1;
            if (!db) return -1;
            return da - db;
        });
    }

    function dateLieu(m) {
        return [m.dateNaissance, m.lieuNaissance].filter(Boolean).join(' à ') || '';
    }

    function todayFr() {
        const d = new Date();
        return `${String(d.getDate()).padStart(2, '0')}/${String(d.getMonth() + 1).padStart(2, '0')}/${d.getFullYear()}`;
    }

    /* ── En-tête officiel (identique au PDF FIF, colonnes centrées comme le modèle) ── */
    function drawFifHeader(doc, W, M, assure, logoData) {
        const y0 = 22;
        const cx = W / 2;
        const lh = 10;
        const leftCx = (M + W / 3) / 2;
        const rightCx = (2 * W / 3 + (W - M)) / 2;
        doc.setTextColor(0, 0, 0);

        let yL = y0;
        doc.setFont('helvetica', 'bold'); doc.setFontSize(8);
        doc.text('Ministère de la Guerre et de la Défense Patriotique', leftCx, yL, { align: 'center' }); yL += lh;
        doc.setFont('helvetica', 'normal'); doc.setFontSize(7);
        doc.text('**************', leftCx, yL, { align: 'center' }); yL += lh;
        doc.text('Secrétariat général', leftCx, yL, { align: 'center' }); yL += lh;
        doc.text('*************', leftCx, yL, { align: 'center' }); yL += lh;
        doc.setFont('helvetica', 'bold'); doc.setFontSize(8);
        doc.text("Caisse d'Assurance Maladie des Armées", leftCx, yL, { align: 'center' });

        let yR = y0;
        doc.setFont('helvetica', 'bold'); doc.setFontSize(8);
        doc.text('Burkina Faso', rightCx, yR, { align: 'center' }); yR += lh;
        doc.setFont('helvetica', 'normal'); doc.setFontSize(7);
        doc.text('**************', rightCx, yR, { align: 'center' }); yR += lh;
        doc.setFont('helvetica', 'italic'); doc.setFontSize(7);
        doc.text('La patrie ou la Mort , Nous vaincrons', rightCx, yR, { align: 'center' });

        const logoW = 70, logoH = 70;
        if (logoData) {
            doc.addImage(logoData, 'PNG', cx - logoW / 2, y0 - 2, logoW, logoH);
        }

        let y = Math.max(yL, yR + lh, y0 + logoH) + 12;
        doc.setFont('helvetica', 'normal'); doc.setFontSize(7);
        doc.text('CAMA : 11 BP 1174 Ouagadougou CMS/ Secrétariat +226 25 30 81 03/Email :', cx, y, { align: 'center' });
        y += 14;
        doc.setFont('helvetica', 'bold'); doc.setFontSize(9);
        const fifNum = (assure.numeroCama || assure.matricule || '').trim() || dots('', 28);
        doc.text(`FICHE D'IDENTIFICATION FAMILLE (FIF) N° : ${fifNum}     Date : ${todayFr()}`, cx, y, { align: 'center' });
        return y + 16;
    }

    function sectionTitle(doc, M, y, W, label) {
        doc.setFont('helvetica', 'bold'); doc.setFontSize(8.5);
        doc.setTextColor(0, 0, 0);
        doc.text(String(label).toUpperCase(), M, y);
        return y + 14;
    }

    function inlineField(doc, x, y, label, value, maxW) {
        doc.setFont('helvetica', 'bold'); doc.setFontSize(7);
        doc.setTextColor(0, 0, 0);
        const lbl = `${label} : `;
        doc.text(lbl, x, y);
        const vx = x + doc.getTextWidth(lbl);
        doc.setFont('helvetica', 'normal');
        const val = (value === undefined || value === null || value === '') ? dots('', 10) : String(value);
        const lines = doc.splitTextToSize(val, maxW - (vx - x));
        doc.text(lines, vx, y);
        return y + Math.max(11, lines.length * 9);
    }

    function drawSection1(doc, M, y, W, assure) {
        y = sectionTitle(doc, M, y, W, '1. Informations administratives du militaire');

        const rowW = W - 2 * M;
        let x = M;
        y = inlineField(doc, x, y, 'NOM', assure.nom, rowW / 4);
        y -= 11;
        x = M + rowW * 0.22;
        y = inlineField(doc, x, y, 'PRENOMS', assure.prenoms || assure.prenom, rowW * 0.28);
        y -= 11;
        x = M + rowW * 0.52;
        y = inlineField(doc, x, y, 'SEXE', assure.sexe, rowW * 0.12);
        y -= 11;
        x = M + rowW * 0.64;
        y = inlineField(doc, x, y, 'MATRICULE MILITAIRE', assure.matricule, rowW * 0.36);
        y += 2;

        x = M;
        y = inlineField(doc, x, y, 'N° INFORMATIQUE', assure.numeroInformatique, rowW * 0.28);
        y -= 11;
        x = M + rowW * 0.3;
        y = inlineField(doc, x, y, 'GRADE', assure.grade, rowW * 0.22);
        y -= 11;
        x = M + rowW * 0.54;
        y = inlineField(doc, x, y, 'CATEGORIE', assure.categorie, rowW * 0.46);
        y += 2;

        x = M;
        y = inlineField(doc, x, y, 'N° CIM', assure.numeroCim, rowW * 0.28);
        y -= 11;
        x = M + rowW * 0.3;
        y = inlineField(doc, x, y, 'N° Carte CAMA', assure.numeroCama, rowW * 0.28);
        y -= 11;
        x = M + rowW * 0.6;
        y = inlineField(doc, x, y, 'N° IUP (3)', assure.numeroIup, rowW * 0.4);
        y += 4;

        doc.setFont('helvetica', 'bold'); doc.setFontSize(7);
        doc.text('STRUCT. DE RATTACHEMENT :', M, y);
        const structY = y;
        let sx = M + doc.getTextWidth('STRUCT. DE RATTACHEMENT : ') + 2;
        const pairs = [
            ['ARMEE', assure.armee],
            ['REGION', assure.region],
            ['CORPS', assure.corps],
            ['SERVICE', assure.service],
            ['SECTION', assure.section],
            ['SOUS-SECTION', assure.sousSection]
        ];
        doc.setFont('helvetica', 'normal');
        pairs.forEach(([lbl, val]) => {
            doc.setFont('helvetica', 'bold'); doc.text(`${lbl}:`, sx, structY);
            sx += doc.getTextWidth(`${lbl}: `);
            doc.setFont('helvetica', 'normal');
            const v = (val || '').trim() || dots('', 6);
            doc.text(v, sx, structY);
            sx += doc.getTextWidth(v + '   ') + 4;
        });
        y = structY + 14;

        x = M;
        y = inlineField(doc, x, y, 'TELEPHONES', assure.telephones || assure.telephone, rowW * 0.35);
        y -= 11;
        x = M + rowW * 0.38;
        y = inlineField(doc, x, y, 'E-MAIL', assure.email, rowW * 0.62);
        y += 2;

        x = M;
        y = inlineField(doc, x, y, 'PERSONNE A PREVENIR', assure.personneAPrevenir, rowW * 0.55);
        y -= 11;
        x = M + rowW * 0.58;
        y = inlineField(doc, x, y, 'TEL.', assure.telPersonneAPrevenir, rowW * 0.42);

        return y + 10;
    }

    function famTable(doc, M, y, W, H, columns, rows, minRows) {
        const tableW = Math.min(columns.reduce((s, c) => s + c.w, 0), W - 2 * M);
        const FONT = 6.5;

        function drawHeader() {
            const hH = 28;
            doc.setFillColor(245, 245, 245);
            doc.rect(M, y, tableW, hH, 'F');
            doc.setTextColor(0, 0, 0);
            doc.setFont('helvetica', 'bold'); doc.setFontSize(6);
            doc.setDrawColor(180, 180, 180);
            let cx = M;
            columns.forEach(c => {
                doc.rect(cx, y, c.w, hH);
                doc.text(doc.splitTextToSize(c.title, c.w - 3), cx + 2, y + 8);
                cx += c.w;
            });
            y += hH;
            return y;
        }

        y = drawHeader();
        doc.setFont('helvetica', 'normal'); doc.setFontSize(FONT); doc.setTextColor(0, 0, 0);

        const count = Math.max(minRows || 2, (rows && rows.length) || 0);
        const iter = [];
        for (let i = 0; i < count; i++) iter.push(rows && rows[i] ? rows[i] : null);

        iter.forEach((r, ri) => {
            const cellLines = columns.map(c => {
                let v = r === null ? (c.key === 'num' ? String(ri + 1) : '') : c.get(r, ri);
                if (v === undefined || v === null) v = c.key === 'num' ? String(ri + 1) : '';
                const l = doc.splitTextToSize(String(v), c.w - 4);
                return l.length ? l : [''];
            });
            const maxLines = Math.max(1, ...cellLines.map(l => l.length));
            const rowH = Math.max(18, maxLines * 8 + 8);
            if (y + rowH > H - 80) {
                doc.addPage();
                y = 40;
                y = drawHeader();
                doc.setFont('helvetica', 'normal'); doc.setFontSize(FONT);
            }
            let cx = M;
            doc.setDrawColor(200, 200, 200);
            columns.forEach((c, ci) => {
                doc.rect(cx, y, c.w, rowH);
                doc.text(cellLines[ci], cx + 2, y + 10);
                cx += c.w;
            });
            y += rowH;
        });
        return y;
    }

    function drawFootnotes(doc, M, y, ageMax) {
        doc.setFont('helvetica', 'normal'); doc.setFontSize(6.5);
        doc.setTextColor.apply(doc, GREY);
        const notes = [
            `(1) Pour les enfants de plus de 21 ans, un document de scolarité est obligatoire`,
            `(3) IUP : Identifiant Unique de la Personne`,
            `(2) Obligatoire pour les enfants de plus de 15 ans, conformément à la règlementation en vigueur au Burkina Faso`
        ];
        notes.forEach(n => { doc.text(n, M, y); y += 9; });
        return y + 6;
    }

    function drawSignatures(doc, M, y, W, H, assure) {
        if (y + 90 > H) { doc.addPage(); y = 40; }
        const blockW = (W - 2 * M) / 3 - 8;
        const blocks = [
            { label: 'Le militaire (Nom, prénom(s) et signature)', name: `${assure.prenoms || assure.prenom || ''} ${assure.nom || ''}`.trim() },
            { label: 'Le chef de corps (identité, signature et cachet)', name: '' },
            { label: 'Le GRH du Corps (Identité, signature et cachet)', name: '' }
        ];
        blocks.forEach((b, i) => {
            const bx = M + i * (blockW + 12);
            doc.setFont('helvetica', 'normal'); doc.setFontSize(7);
            doc.setTextColor(0, 0, 0);
            doc.text(`Date : ${dots('', 14)}`, bx, y);
            doc.setFont('helvetica', 'bold'); doc.setFontSize(6.8);
            doc.text(doc.splitTextToSize(b.label, blockW), bx, y + 12);
            if (b.name) {
                doc.setFont('helvetica', 'normal'); doc.setFontSize(7);
                doc.text(b.name, bx, y + 28);
            }
            doc.setDrawColor(160, 160, 160);
            doc.rect(bx, y + 36, blockW, 42);
        });
        return y + 90;
    }

    function buildFormulaireComplet(assure, conjoints, enfants, logoData) {
        ensureLibs();
        const { jsPDF } = global.jspdf;
        const doc = new jsPDF({ unit: 'pt', format: 'a4', orientation: 'landscape' });
        const W = doc.internal.pageSize.getWidth();
        const H = doc.internal.pageSize.getHeight();
        const M = 28;

        const ageMax = (global.CamaAssureData && global.CamaAssureData.getAgeMaxEnfant)
            ? global.CamaAssureData.getAgeMaxEnfant() : 26;
        const sortedEnfants = sortEnfantsByAge(enfants);

        let y = drawFifHeader(doc, W, M, assure, logoData);
        y = drawSection1(doc, M, y, W, assure);

        y = sectionTitle(doc, M, y, W, '2. Conjoint(es) (Marié(s) à la mairie uniquement)');
        y = famTable(doc, M, y, W, H, [
            { title: 'N°', w: 20, key: 'num', get: (r, ri) => ri + 1 },
            { title: 'Nom', w: 62, get: r => r.nom },
            { title: 'Prénoms', w: 68, get: r => r.prenom },
            { title: 'Date et lieu de naissance', w: 88, get: dateLieu },
            { title: 'Sexe', w: 36, get: r => r.sexe },
            { title: 'G.S.', w: 28, get: r => r.groupeSanguin },
            { title: "Références document d'identité", w: 82, get: r => r.refIdentite },
            { title: 'Réf. Acte de mariage', w: 78, get: r => r.refActeMariage },
            { title: 'Profession', w: 68, get: r => r.profession },
            { title: 'Lieu de résidence', w: 72, get: r => r.lieuResidence },
            { title: 'Téléphone', w: 62, get: r => r.telephone }
        ], conjoints || [], 2);
        y += 8;

        y = sectionTitle(doc, M, y, W, `3. Enfant(s) (de 0 à ${ageMax} ans et du plus âgé au plus jeune) (1)`);
        y = famTable(doc, M, y, W, H, [
            { title: 'N°', w: 20, key: 'num', get: (r, ri) => ri + 1 },
            { title: 'Nom', w: 58, get: r => r.nom },
            { title: 'Prénoms', w: 64, get: r => r.prenom },
            { title: 'Date et lieu Naissance', w: 86, get: dateLieu },
            { title: 'Sexe', w: 34, get: r => r.sexe },
            { title: 'G.S.', w: 26, get: r => r.groupeSanguin },
            { title: "Références d'identité (2)", w: 78, get: r => r.refIdentite },
            { title: "Réf. Acte de scolarité / d'état civil", w: 92, get: r => r.refActeScolariteEtatCivil },
            { title: 'Nom et prénoms de la mère (du père si personnel féminin)', w: 98, get: r => r.nomPrenomsParent },
            { title: 'Téléphone', w: 58, get: r => r.telephone }
        ], sortedEnfants, 2);

        y = drawFootnotes(doc, M, y + 6, ageMax);
        drawSignatures(doc, M, y, W, H, assure);

        return doc;
    }

    function splitMembres(membres) {
        const list = membres || [];
        return {
            conjoints: list.filter(m => (m.lien || '') === 'Conjoint(e)'),
            enfants: sortEnfantsByAge(list.filter(m => (m.lien || '').startsWith('Enfant')))
        };
    }

    function membreToRow(m) {
        return {
            nom: m.nom, prenom: m.prenom, lien: m.lien, sexe: m.sexe,
            dateNaissance: m.dateNaissance, lieuNaissance: m.lieuNaissance,
            groupeSanguin: m.groupeSanguin, refIdentite: m.refIdentite,
            refActeMariage: m.refActeMariage, profession: m.profession,
            lieuResidence: m.lieuResidence, telephone: m.telephone,
            refActeScolariteEtatCivil: m.refActeScolariteEtatCivil,
            nomPrenomsParent: m.nomPrenomsParent, numeroCama: m.numeroCama,
            statut: m.statut, dossierId: m.dossierId || m.ref,
            dateSoumission: m.dateSoumission, motifRefus: m.motifRefus,
            pieces: m.pieces || []
        };
    }

    function exportMembre(membre, assure, seq) {
        const row = membreToRow(membre);
        const isConjoint = (row.lien || '') === 'Conjoint(e)';
        const conjoints = isConjoint ? [row] : [];
        const enfants = isConjoint ? [] : [row];
        return loadLogoDataUrl().then(logo => {
            const doc = buildFormulaireComplet(assure, conjoints, enfants, logo || null);
            doc.save(fileName(assure, membre, seq || 1));
        });
    }

    async function exportZip(membres, assure) {
        if (!global.JSZip) throw new Error('JSZip non chargé');
        const logo = await loadLogoDataUrl();
        const zip = new global.JSZip();
        const rows = (membres || []).map(membreToRow);
        const { conjoints, enfants } = splitMembres(rows);

        const fullDoc = buildFormulaireComplet(assure, conjoints, enfants, logo || null);
        zip.file(`FIF_${camaTag(assure)}_complet.pdf`, fullDoc.output('arraybuffer'));

        rows.forEach((m, i) => {
            const isConjoint = (m.lien || '') === 'Conjoint(e)';
            const doc = buildFormulaireComplet(
                assure,
                isConjoint ? [m] : [],
                isConjoint ? [] : [m],
                logo || null
            );
            zip.file(fileName(assure, m, i + 1), doc.output('arraybuffer'));
        });

        const blob = await zip.generateAsync({ type: 'blob' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url; a.download = zipName(assure);
        document.body.appendChild(a); a.click(); a.remove();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
    }

    function exportDossierFamilial(assure, membres) {
        const rows = (membres || []).map(membreToRow);
        const { conjoints, enfants } = splitMembres(rows);
        return loadLogoDataUrl().then(logo => {
            const doc = buildFormulaireComplet(assure, conjoints, enfants, logo || null);
            doc.save(`FIF_${camaTag(assure)}.pdf`);
        });
    }

    function exportDossierByRef(ref) {
        const data = global.CamaAssureData;
        if (!data) throw new Error('Données CAMA indisponibles');
        const d = data.getDossiers().find(x => x.ref === ref);
        if (!d) throw new Error('Dossier introuvable');
        const membre = membreToRow(d);
        const detail = data.getAssureDetailForAdmin ? data.getAssureDetailForAdmin(d.assureNom) : {};
        const assure = {
            ...detail,
            nom: d.nom ? d.assureNom.split(' ').slice(-1)[0] : '',
            prenoms: d.assureNom ? d.assureNom.split(' ').slice(0, -1).join(' ') : '',
            fullName: d.assureNom,
            numeroCama: detail.numeroCama,
            numeroCim: detail.numeroCim,
            matricule: detail.matricule
        };
        return exportMembre(membre, assure, 1);
    }

    function exportFormulaireFamilialByAssure(assureNom) {
        const data = global.CamaAssureData;
        if (!data) throw new Error('Données CAMA indisponibles');
        const detail = data.getAssureDetailForAdmin(assureNom);
        const membres = data.getDossiers().filter(d => d.assureNom === assureNom).map(membreToRow);
        const parts = assureNom.split(' ');
        const assure = {
            ...detail,
            nom: parts.slice(-1)[0] || '',
            prenoms: parts.slice(0, -1).join(' '),
            fullName: assureNom
        };
        return exportDossierFamilial(assure, membres);
    }

    global.CamaDossierExport = {
        fileName, zipName,
        buildFormulaireComplet,
        exportMembre, exportZip,
        exportDossierFamilial,
        exportDossierByRef,
        exportFormulaireFamilialByAssure,
        loadLogoDataUrl
    };
})(window);
