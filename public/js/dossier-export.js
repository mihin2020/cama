/**
 * Export FIF CAMA — superposition sur le formulaire officiel.
 *
 * Le PDF officiel (public/templates/fif-cama.pdf) est utilisé tel quel comme
 * fond : les données de l'assuré et des membres sont simplement écrites
 * par-dessus, aux coordonnées exactes des champs. Le rendu est donc
 * strictement identique au modèle officiel « FIF CAMA.pdf ».
 *
 * Dépendances : pdf-lib (window.PDFLib), JSZip (window.JSZip pour l'archive).
 */
(function (global) {
    'use strict';

    const TEMPLATE_URLS = ['/templates/fif-cama.pdf', 'templates/fif-cama.pdf', '../templates/fif-cama.pdf'];

    // Dimensions du modèle officiel (A4 paysage, unité point, origine bas-gauche).
    const PAGE_W = 841.92;
    const PAGE_H = 595.32;

    let _templateBytes = null;

    function ensureLibs() {
        if (!global.PDFLib || !global.PDFLib.PDFDocument) {
            throw new Error('pdf-lib non chargé');
        }
    }

    async function loadTemplateBytes() {
        if (_templateBytes) return _templateBytes;
        let lastErr = null;
        for (const url of TEMPLATE_URLS) {
            try {
                const res = await fetch(url);
                if (res.ok) {
                    _templateBytes = await res.arrayBuffer();
                    return _templateBytes;
                }
            } catch (e) { lastErr = e; }
        }
        throw lastErr || new Error('Modèle FIF introuvable (public/templates/fif-cama.pdf)');
    }

    /* ── Utilitaires ── */
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

    function chunk(arr, size) {
        const out = [];
        for (let i = 0; i < (arr || []).length; i += size) out.push(arr.slice(i, i + size));
        return out.length ? out : [[]];
    }

    /* ── Coordonnées des champs (relevées sur le modèle officiel) ── */
    // Section 1 — informations administratives du militaire.
    function section1Fields(assure) {
        const g = (k) => {
            const v = assure && assure[k];
            return (v === undefined || v === null) ? '' : String(v).trim();
        };
        const prenoms = g('prenoms') || g('prenom');
        return [
            { x: 410, y: 496, size: 9, text: (g('numeroCama') || g('matricule')), maxW: 180 }, // FIF N°
            { x: 636, y: 496, size: 9, text: todayFr(), maxW: 78 }, // Date en-tête

            { x: 44, y: 456, text: g('nom'), maxW: 125 },
            { x: 228, y: 456, text: prenoms, maxW: 140 },
            { x: 400, y: 456, text: (g('sexe') ? g('sexe').charAt(0).toUpperCase() : ''), maxW: 18 },
            { x: 521, y: 456, text: g('matricule'), maxW: 90 },
            { x: 742, y: 456, text: g('numeroInformatique'), maxW: 90 },

            { x: 55, y: 438, text: g('grade'), maxW: 120 },
            { x: 238, y: 438, text: g('categorie'), maxW: 120 },
            { x: 372, y: 438, text: g('numeroCim'), maxW: 90 },
            { x: 536, y: 438, text: g('numeroCama'), maxW: 95 },
            { x: 668, y: 438, text: g('numeroIup'), maxW: 150 },

            { x: 190, y: 419, text: g('armee'), maxW: 30 },
            { x: 258, y: 419, text: g('region'), maxW: 52 },
            { x: 342, y: 419, text: g('corps'), maxW: 55 },
            { x: 447, y: 419, text: g('service'), maxW: 62 },
            { x: 578, y: 419, text: g('section'), maxW: 35 },
            { x: 716, y: 419, text: g('sousSection'), maxW: 95 },

            { x: 80, y: 401, text: (g('telephones') || g('telephone')), maxW: 120 },
            { x: 250, y: 401, text: g('email'), maxW: 135 },
            { x: 585, y: 401, text: g('personneAPrevenir'), maxW: 85 },
            { x: 745, y: 401, text: g('telPersonneAPrevenir'), maxW: 70 },
        ];
    }

    // Colonnes du tableau des conjoints (x gauche, largeur max).
    const CONJOINT_COLS = [
        { x: 58, w: 100, get: (r) => r.nom },
        { x: 148, w: 100, get: (r) => r.prenom },
        { x: 250, w: 82, get: dateLieu },
        { x: 344, w: 32, get: (r) => r.sexe },
        { x: 378, w: 30, get: (r) => r.groupeSanguin },
        { x: 416, w: 120, get: (r) => r.refIdentite },
        { x: 540, w: 105, get: (r) => r.refActeMariage },
        { x: 648, w: 60, get: (r) => r.profession },
        { x: 710, w: 62, get: (r) => r.lieuResidence },
        { x: 772, w: 62, get: (r) => r.telephone },
    ];
    const CONJOINT_ROWS_Y = [321, 286.5];

    // Colonnes du tableau des enfants.
    const ENFANT_COLS = [
        { x: 58, w: 100, get: (r) => r.nom },
        { x: 148, w: 100, get: (r) => r.prenom },
        { x: 250, w: 90, get: dateLieu },
        { x: 344, w: 32, get: (r) => r.sexe },
        { x: 378, w: 30, get: (r) => r.groupeSanguin },
        { x: 416, w: 108, get: (r) => r.refIdentite },
        { x: 528, w: 120, get: (r) => r.refActeScolariteEtatCivil },
        { x: 650, w: 120, get: (r) => r.nomPrenomsParent },
        { x: 772, w: 62, get: (r) => r.telephone },
    ];
    const ENFANT_ROWS_Y = [204, 169.5, 135];

    function clip(font, text, size, maxW) {
        let s = String(text == null ? '' : text);
        if (!s) return '';
        if (font.widthOfTextAtSize(s, size) <= maxW) return s;
        while (s.length > 1 && font.widthOfTextAtSize(s + '…', size) > maxW) {
            s = s.slice(0, -1);
        }
        return s + '…';
    }

    function drawField(page, font, black, x, y, text, size, maxW) {
        const s = clip(font, text, size, maxW || 200);
        if (!s) return;
        page.drawText(s, { x, y, size, font, color: black });
    }

    function drawTableRow(page, font, black, cols, y, row, size) {
        cols.forEach((c) => {
            const v = c.get(row);
            drawField(page, font, black, c.x, y, v, size, c.w);
        });
    }

    async function buildFormulaireComplet(assure, conjoints, enfants) {
        ensureLibs();
        const { PDFDocument, StandardFonts, rgb } = global.PDFLib;
        const tmplBytes = await loadTemplateBytes();

        const out = await PDFDocument.create();
        const font = await out.embedFont(StandardFonts.Helvetica);
        const black = rgb(0, 0, 0);

        const conjChunks = chunk(conjoints || [], CONJOINT_ROWS_Y.length);
        const enfChunks = chunk(sortEnfantsByAge(enfants), ENFANT_ROWS_Y.length);
        const pages = Math.max(1, conjChunks.length, enfChunks.length);

        for (let p = 0; p < pages; p++) {
            const tmpl = await PDFDocument.load(tmplBytes);
            const [page] = await out.copyPages(tmpl, [0]);
            out.addPage(page);

            section1Fields(assure).forEach((f) => {
                drawField(page, font, black, f.x, f.y, f.text, f.size || 8, f.maxW);
            });

            (conjChunks[p] || []).forEach((row, i) => {
                if (CONJOINT_ROWS_Y[i] != null) {
                    drawTableRow(page, font, black, CONJOINT_COLS, CONJOINT_ROWS_Y[i], row, 7);
                }
            });

            (enfChunks[p] || []).forEach((row, i) => {
                if (ENFANT_ROWS_Y[i] != null) {
                    drawTableRow(page, font, black, ENFANT_COLS, ENFANT_ROWS_Y[i], row, 7);
                }
            });

            // Nom du militaire sous le bloc signature.
            const nomMil = `${assure.prenoms || assure.prenom || ''} ${assure.nom || ''}`.trim();
            if (nomMil) {
                drawField(page, font, black, 26.6, 66, nomMil, 7, 170);
            }
        }

        return out.save();
    }

    function downloadBytes(bytes, filename) {
        const blob = new Blob([bytes], { type: 'application/pdf' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url; a.download = filename;
        document.body.appendChild(a); a.click(); a.remove();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
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

    function splitMembres(membres) {
        const list = membres || [];
        return {
            conjoints: list.filter((m) => (m.lien || '') === 'Conjoint(e)'),
            enfants: sortEnfantsByAge(list.filter((m) => (m.lien || '').startsWith('Enfant')))
        };
    }

    async function exportMembre(membre, assure, seq) {
        const row = membreToRow(membre);
        const isConjoint = (row.lien || '') === 'Conjoint(e)';
        const bytes = await buildFormulaireComplet(
            assure,
            isConjoint ? [row] : [],
            isConjoint ? [] : [row]
        );
        downloadBytes(bytes, fileName(assure, membre, seq || 1));
    }

    async function exportZip(membres, assure) {
        if (!global.JSZip) throw new Error('JSZip non chargé');
        const zip = new global.JSZip();
        const rows = (membres || []).map(membreToRow);
        const { conjoints, enfants } = splitMembres(rows);

        const fullBytes = await buildFormulaireComplet(assure, conjoints, enfants);
        zip.file(`FIF_${camaTag(assure)}_complet.pdf`, fullBytes);

        for (let i = 0; i < rows.length; i++) {
            const m = rows[i];
            const isConjoint = (m.lien || '') === 'Conjoint(e)';
            const bytes = await buildFormulaireComplet(
                assure,
                isConjoint ? [m] : [],
                isConjoint ? [] : [m]
            );
            zip.file(fileName(assure, m, i + 1), bytes);
        }

        const blob = await zip.generateAsync({ type: 'blob' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url; a.download = zipName(assure);
        document.body.appendChild(a); a.click(); a.remove();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
    }

    async function exportDossierFamilial(assure, membres) {
        const rows = (membres || []).map(membreToRow);
        const { conjoints, enfants } = splitMembres(rows);
        const bytes = await buildFormulaireComplet(assure, conjoints, enfants);
        downloadBytes(bytes, `FIF_${camaTag(assure)}.pdf`);
    }

    function exportDossierByRef(ref) {
        const data = global.CamaAssureData;
        if (!data) throw new Error('Données CAMA indisponibles');
        const d = data.getDossiers().find((x) => x.ref === ref);
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
        const membres = data.getDossiers().filter((d) => d.assureNom === assureNom).map(membreToRow);
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
        loadTemplateBytes,
        loadLogoDataUrl: () => Promise.resolve(null),
    };
})(window);
