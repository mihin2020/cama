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

    // Les fichiers portent le numéro FIF de la famille (à défaut, le N° CAMA ou le matricule).
    function camaTag(assure) {
        return sanitize(assure && (assure.numeroFif || assure.numeroCama || assure.matricule) || 'CAMA');
    }

    function fileName(assure, membre, seq) {
        const n = String(seq || 1).padStart(2, '0');
        const who = sanitize(`${membre.prenom || ''}-${membre.nom || ''}`);
        return `${camaTag(assure)}_${n}_${who}.pdf`;
    }

    // « FIF-2026-K7M4Q9 » tel quel ; préfixe FIF_ pour les anciens profils sans numéro.
    function fifTag(assure) {
        return assure && assure.numeroFif ? sanitize(assure.numeroFif) : `FIF_${camaTag(assure)}`;
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
            { x: 410, y: 496, size: 9, text: g('numeroFif'), maxW: 180 }, // FIF N° (propre à la famille)
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
            { x: 680, y: 438, text: g('numeroIup'), maxW: 140 }, // après « N° IUP (3) : »

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

    /* ── Pages de suite : champs ajoutés par l'administration, dans la forme du formulaire officiel ──
       Mêmes titres numérotés en majuscules, mêmes tableaux à cases (colonne N°, Nom, Prénoms, puis une
       colonne par information), mêmes signatures — placées à la toute fin du document. Seules les
       informations textuelles ou numériques sont reprises : jamais un fichier chargé. */

    // Helvetica (WinAnsi) ne sait pas écrire tous les caractères : on remplace ceux qu'elle refuse.
    function safeText(font, text) {
        let out = '';
        for (const ch of String(text == null ? '' : text).replace(/[\r\n\t]+/g, ' ')) {
            try { font.encodeText(ch); out += ch; } catch (e) { out += '?'; }
        }
        return out;
    }

    function wrapText(font, text, size, maxW) {
        const words = safeText(font, text).split(/\s+/).filter(Boolean);
        const lines = [];
        let line = '';
        words.forEach((word) => {
            let w = word;
            while (font.widthOfTextAtSize(w, size) > maxW && w.length > 1) {
                let cut = w.length - 1;
                while (cut > 1 && font.widthOfTextAtSize(w.slice(0, cut), size) > maxW) cut--;
                if (line) { lines.push(line); line = ''; }
                lines.push(w.slice(0, cut));
                w = w.slice(cut);
            }
            const test = line ? `${line} ${w}` : w;
            if (font.widthOfTextAtSize(test, size) <= maxW) { line = test; }
            else { lines.push(line); line = w; }
        });
        if (line) lines.push(line);
        return lines.length ? lines : [''];
    }

    // Sections à imprimer : lignes « imprimer_pdf » uniquement, jamais les fichiers ; section vide ignorée.
    function printableSections(sections) {
        return (sections || [])
            .map((s) => ({ titre: s.titre, rows: (s.rows || []).filter((r) => r.imprimer !== false && r.type !== 'fichier') }))
            .filter((s) => s.rows.length);
    }

    // Une table par section : colonnes = champs de la section, une ligne par membre.
    function memberTables(members) {
        const tables = [];
        (members || []).forEach((m, index) => {
            printableSections(m.fifSections).forEach((section) => {
                let table = tables.find((t) => t.titre === section.titre);
                if (!table) {
                    table = { titre: section.titre, columns: section.rows.map((r) => ({ code: r.code, label: r.label })), rows: [] };
                    tables.push(table);
                }
                const values = {};
                section.rows.forEach((r) => { values[r.code] = r.value; });
                table.rows.push({ n: index + 1, nom: m.nom, prenom: m.prenom, values });
            });
        });
        return tables;
    }

    function annexContent(assure, conjoints, enfants) {
        return {
            assure: printableSections((assure || {}).fifSections),
            conjoints: memberTables(conjoints),
            enfants: memberTables(enfants),
        };
    }

    function annexIsEmpty(c) {
        return !c.assure.length && !c.conjoints.length && !c.enfants.length;
    }

    // Les signatures du modèle sont déplacées à la fin quand des pages de suite existent : on les masque.
    function maskSignatures(page) {
        const { rgb } = global.PDFLib;
        page.drawRectangle({ x: 20, y: 63, width: 805, height: 48, color: rgb(1, 1, 1) });
    }

    async function addAnnexPages(out, fonts, assure, content) {
        const { rgb } = global.PDFLib;
        const { font, bold, italic } = fonts;
        const black = rgb(0, 0, 0);
        const L = 34;
        const R = PAGE_W - 34;
        const W = R - L;
        const SIZE = 8;
        const LEAD = 10;
        const PAD = 3;
        const BOTTOM = 46;
        const numero = (assure && (assure.numeroFif || assure.numeroCama || assure.matricule)) || '';

        let page = null;
        let y = 0;
        let pageNo = 0;
        let resume = null; // redessine le titre de section et l'en-tête du tableau après un saut de page

        const rect = (x, yTop, w, h) => page.drawRectangle({ x, y: yTop - h, width: w, height: h, borderWidth: 0.7, borderColor: black });
        const text = (t, x, yy, f, size) => page.drawText(safeText(f, t), { x, y: yy, size: size || SIZE, font: f, color: black });
        const centered = (t, cx, yy, f, size) => {
            const s = safeText(f, t);
            page.drawText(s, { x: cx - f.widthOfTextAtSize(s, size) / 2, y: yy, size, font: f, color: black });
        };

        const newPage = () => {
            page = out.addPage([PAGE_W, PAGE_H]);
            pageNo += 1;
            // Cadre d'en-tête, comme celui du formulaire : titre, numéro FIF et date.
            rect(L, PAGE_H - 28, W, 44);
            centered(`FICHE D'IDENTIFICATION FAMILLE (FIF) N° ${numero}`, PAGE_W / 2, PAGE_H - 48, bold, 13);
            centered('Informations complémentaires — suite du formulaire', PAGE_W / 2, PAGE_H - 62, italic, 9);
            const d = `Date : ${todayFr()}`;
            text(d, R - 8 - font.widthOfTextAtSize(d, 8.5), PAGE_H - 48, font, 8.5);
            text(`Page ${pageNo + 1}`, L, 24, font, 7);
            y = PAGE_H - 88;
        };

        const ensure = (h) => {
            if (page && y - h >= BOTTOM) return;
            newPage();
            if (resume) resume();
        };

        const heading = (t) => {
            ensure(34);
            text(t, L, y, bold, 10);
            y -= 14;
        };

        const subTitle = (t) => {
            ensure(30);
            text(t, L, y, bold, 8.5);
            page.drawLine({ start: { x: L, y: y - 2 }, end: { x: L + bold.widthOfTextAtSize(safeText(bold, t), 8.5), y: y - 2 }, thickness: 0.6, color: black });
            y -= 12;
        };

        // Informations du militaire : cases « libellé | valeur », deux paires par ligne.
        const drawAssure = (sections) => {
            heading('1.  INFORMATIONS ADMINISTRATIVES COMPLÉMENTAIRES DU MILITAIRE');
            sections.forEach((section) => {
                subTitle(section.titre);
                const labelW = 150;
                const valueW = W / 2 - labelW;
                for (let i = 0; i < section.rows.length; i += 2) {
                    const pair = section.rows.slice(i, i + 2);
                    const lines = pair.map((r) => [wrapText(font, r.label, SIZE, labelW - 2 * PAD), wrapText(font, r.value || '', SIZE, valueW - 2 * PAD)]);
                    const h = Math.max(...lines.map(([a, b]) => Math.max(a.length, b.length))) * LEAD + 2 * PAD;
                    resume = () => subTitle(`${section.titre} (suite)`);
                    ensure(h);
                    pair.forEach((r, k) => {
                        const x = L + k * (W / 2);
                        rect(x, y, labelW, h);
                        rect(x + labelW, y, valueW, h);
                        lines[k][0].forEach((ln, j) => text(ln, x + PAD, y - PAD - SIZE - j * LEAD + 1, bold, SIZE));
                        lines[k][1].forEach((ln, j) => text(ln, x + labelW + PAD, y - PAD - SIZE - j * LEAD + 1, font, SIZE));
                    });
                    if (pair.length === 1) rect(L + W / 2, y, W / 2, h);
                    y -= h;
                }
                y -= 10;
            });
            resume = null;
        };

        // Conjoints / enfants : tableau à cases, N° | Nom | Prénoms | une colonne par information.
        const drawTables = (title, tables) => {
            if (!tables.length) return;
            heading(title);
            const nW = 26;
            const nomW = 95;
            const prenomW = 110;
            const fixed = nW + nomW + prenomW;
            tables.forEach((table) => {
                const perChunk = 6;
                for (let c = 0; c < table.columns.length; c += perChunk) {
                    const cols = table.columns.slice(c, c + perChunk);
                    const colW = (W - fixed) / cols.length;
                    const widths = [nW, nomW, prenomW, ...cols.map(() => colW)];
                    const headers = ['N°', 'Nom', 'Prénoms', ...cols.map((k) => k.label)];
                    const xs = widths.reduce((acc, w, i) => [...acc, acc[i] + w], [L]);

                    const drawHeader = () => {
                        const wrapped = headers.map((hd, i) => wrapText(bold, hd, SIZE, widths[i] - 2 * PAD));
                        const h = Math.max(...wrapped.map((l) => l.length)) * LEAD + 2 * PAD;
                        ensure(h + 30);
                        wrapped.forEach((lns, i) => {
                            rect(xs[i], y, widths[i], h);
                            lns.forEach((ln, j) => centered(ln, xs[i] + widths[i] / 2, y - PAD - SIZE - j * LEAD + 1, bold, SIZE));
                        });
                        y -= h;
                    };

                    subTitle(`${table.titre}${cols.length < table.columns.length ? ` (${Math.floor(c / perChunk) + 1}/${Math.ceil(table.columns.length / perChunk)})` : ''}`);
                    resume = () => { subTitle(`${table.titre} (suite)`); drawHeader(); };
                    drawHeader();

                    table.rows.forEach((row) => {
                        const cells = [String(row.n), row.nom || '', row.prenom || '', ...cols.map((k) => row.values[k.code] || '')];
                        const wrapped = cells.map((t, i) => wrapText(font, t, SIZE, widths[i] - 2 * PAD));
                        const h = Math.max(...wrapped.map((l) => l.length)) * LEAD + 2 * PAD + 4;
                        ensure(h);
                        wrapped.forEach((lns, i) => {
                            rect(xs[i], y, widths[i], h);
                            lns.forEach((ln, j) => (i === 0 ? centered(ln, xs[i] + widths[i] / 2, y - PAD - SIZE - j * LEAD + 3, font, SIZE) : text(ln, xs[i] + PAD, y - PAD - SIZE - j * LEAD + 3, font, SIZE)));
                        });
                        y -= h;
                    });
                    y -= 12;
                }
            });
            resume = null;
        };

        // Signatures : à la toute fin, comme au bas du formulaire officiel.
        const drawSignatures = () => {
            ensure(90);
            y -= 34; // espace pour signer
            const cols = [
                { x: 34, titre: 'Le militaire', suite: ' (Nom, prénom(s) et signature)' },
                { x: 309, titre: 'Le chef de corps', suite: ' (identité, signature et cachet)' },
                { x: 617, titre: 'Le GRH du Corps', suite: ' (Identité, signature et cachet)' },
            ];
            cols.forEach((col) => {
                text('Date : ', col.x, y, bold, 8.5);
                text('.......................................................', col.x + bold.widthOfTextAtSize('Date : ', 8.5), y, font, 8.5);
                text(col.titre, col.x, y - 15, bold, 8.5);
                page.drawLine({ start: { x: col.x, y: y - 17 }, end: { x: col.x + bold.widthOfTextAtSize(col.titre, 8.5), y: y - 17 }, thickness: 0.6, color: black });
                text(col.suite, col.x + bold.widthOfTextAtSize(col.titre, 8.5), y - 15, italic, 8);
            });
            const nomMil = ((assure && (assure.prenoms || assure.prenom)) || '') + ' ' + ((assure && assure.nom) || '');
            if (nomMil.trim()) text(nomMil.trim(), 34, y - 30, font, 7);
            y -= 30;
        };

        newPage();
        if (content.assure.length) drawAssure(content.assure);
        drawTables('2.  CONJOINT(ES) — INFORMATIONS COMPLÉMENTAIRES', content.conjoints);
        drawTables('3.  ENFANT(S) — INFORMATIONS COMPLÉMENTAIRES (du plus âgé au plus jeune)', content.enfants);
        drawSignatures();
    }

    async function buildFormulaireComplet(assure, conjoints, enfants) {
        ensureLibs();
        const { PDFDocument, StandardFonts, rgb } = global.PDFLib;
        const tmplBytes = await loadTemplateBytes();

        const out = await PDFDocument.create();
        const font = await out.embedFont(StandardFonts.Helvetica);
        const bold = await out.embedFont(StandardFonts.HelveticaBold);
        const italic = await out.embedFont(StandardFonts.HelveticaOblique);
        const black = rgb(0, 0, 0);

        const sortedEnfants = sortEnfantsByAge(enfants);
        const conjChunks = chunk(conjoints || [], CONJOINT_ROWS_Y.length);
        const enfChunks = chunk(sortedEnfants, ENFANT_ROWS_Y.length);
        const pages = Math.max(1, conjChunks.length, enfChunks.length);
        const annex = annexContent(assure, conjoints, sortedEnfants);
        const hasAnnex = !annexIsEmpty(annex);

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

            if (hasAnnex) {
                // Pages de suite : les signatures passent à la toute fin du document, on masque celles du modèle.
                maskSignatures(page);
            } else {
                // Nom du militaire sous le bloc signature.
                const nomMil = `${assure.prenoms || assure.prenom || ''} ${assure.nom || ''}`.trim();
                if (nomMil) {
                    drawField(page, font, black, 26.6, 66, nomMil, 7, 170);
                }
            }
        }

        // Champs ajoutés par l'administration : pages de suite dans la forme du formulaire, signatures à la fin.
        if (hasAnnex) {
            await addAnnexPages(out, { font, bold, italic }, assure || {}, annex);
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
            fifSections: m.fifSections || [],
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
        zip.file(`${fifTag(assure)}_complet.pdf`, fullBytes);

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
        downloadBytes(bytes, `${fifTag(assure)}.pdf`);
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
