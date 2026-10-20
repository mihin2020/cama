// Mise en forme des textes des mentions légales, partagée par la page publique et l'aperçu
// du module admin (rendu identique). Aucun HTML n'est interprété : le texte reste du texte.

/** Ancre d'une rubrique (« Vos droits » → vos-droits), utilisée dans l'adresse /mention_legales#… */
export function legalAnchor(article, index = 0) {
    const slug = String(article?.anchor || article?.title || '')
        .normalize('NFD').replace(/[̀-ͯ]/g, '')
        .toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
    return slug || `article-${index + 1}`;
}

/** Texte saisi → blocs : une ligne vide sépare deux paragraphes, une ligne « - … » devient une puce. */
export function legalBlocks(body) {
    const blocks = [];
    String(body ?? '').split(/\n\s*\n/).forEach((chunk) => {
        let paragraph = [];
        let list = null;
        const flushParagraph = () => {
            if (paragraph.length) blocks.push({ type: 'p', text: paragraph.join(' ') });
            paragraph = [];
        };
        const flushList = () => {
            if (list) blocks.push({ type: 'ul', items: list });
            list = null;
        };
        chunk.split('\n').map((line) => line.trim()).filter(Boolean).forEach((line) => {
            if (/^[-•]\s+/.test(line)) {
                flushParagraph();
                (list ??= []).push(line.replace(/^[-•]\s+/, ''));
            } else {
                flushList();
                paragraph.push(line);
            }
        });
        flushParagraph();
        flushList();
    });
    return blocks;
}
