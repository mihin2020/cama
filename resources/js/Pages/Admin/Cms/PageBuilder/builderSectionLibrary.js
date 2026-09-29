/**
 * Bibliothèque de sections prêtes à insérer dans le Page Builder CAMA.
 * Chaque entrée décrit une section (colonnes + widgets partiels).
 */

export const SECTION_LIBRARY = [
    {
        id: 'hero-cama',
        name: 'Hero institutionnel',
        icon: 'crop_landscape',
        description: 'Titre fort, texte d\'accroche et bouton d\'action',
        category: 'Heros',
        settings: { contentWidth: 'boxed', bgColor: 'surface-container-low', padding: { t: 64, r: 24, b: 64, l: 24 } },
        columns: [{
            width: 100,
            widgets: [
                { type: 'heading', content: { text: 'La santé de nos héros, notre priorité', tag: 'h1' }, style: { align: 'center', size: 'text-4xl', weight: 'font-bold', textColor: 'on-surface' } },
                { type: 'text', content: { html: '<p>La CAMA accompagne les militaires et leurs ayants droit sur l\'ensemble du territoire national.</p>' }, style: { align: 'center', size: 'text-lg', textColor: 'on-surface-variant' } },
                { type: 'button', content: { text: 'En savoir plus', href: '/apropos', icon: 'arrow_forward' }, style: { align: 'center', bgColor: 'primary', textColor: 'white' } },
            ],
        }],
    },
    {
        id: 'hero-split',
        name: 'Hero image + texte',
        icon: 'vertical_split',
        description: 'Image à gauche, titre et bouton à droite',
        category: 'Heros',
        columns: [
            { width: 50, widgets: [{ type: 'image', content: { src: '/images/CAMA_8.jfif', alt: 'CAMA', ratio: 'aspect-video' }, style: { align: 'center', radius: 12 } }] },
            { width: 50, widgets: [
                { type: 'heading', content: { text: 'Une mission de service public', tag: 'h2' }, style: { size: 'text-3xl', weight: 'font-bold' } },
                { type: 'text', content: { html: '<p>Protection santé, remboursements et accompagnement des ayants droit.</p>' }, style: { textColor: 'on-surface-variant' } },
                { type: 'button', content: { text: 'Nos services', href: '/services', icon: '' }, style: { bgColor: 'primary', textColor: 'white' } },
            ] },
        ],
    },
    {
        id: 'banner-text',
        name: 'Bannière + texte',
        icon: 'title',
        description: 'Titre centré sur fond clair',
        category: 'Contenu',
        settings: { bgColor: 'surface-container-low', padding: { t: 48, r: 24, b: 48, l: 24 } },
        columns: [{
            width: 100,
            widgets: [
                { type: 'heading', content: { text: 'À propos de la CAMA', tag: 'h2' }, style: { align: 'center', size: 'text-3xl', weight: 'font-bold' } },
                { type: 'text', content: { html: '<p>La Caisse d\'Assurance Maladie des Armées protège les familles militaires sur tout le territoire.</p>' }, style: { align: 'center', textColor: 'on-surface-variant', size: 'text-lg' } },
            ],
        }],
    },
    {
        id: 'cards-3',
        name: '3 atouts (cartes)',
        icon: 'grid_view',
        description: 'Titre + 3 cartes icône',
        category: 'Contenu',
        columns: [{
            width: 100,
            widgets: [
                { type: 'heading', content: { text: 'Nos engagements', tag: 'h2' }, style: { align: 'center', size: 'text-3xl', weight: 'font-bold' } },
                { type: 'cards', content: { columns: 3, items: [
                    { icon: 'health_and_safety', title: 'Protection', text: 'Couverture complète pour vous et vos ayants droit.' },
                    { icon: 'payments', title: 'Remboursements', text: 'Traitement rapide de vos demandes.' },
                    { icon: 'groups', title: 'Solidarité', text: 'Un système mutualisé au service des militaires.' },
                ] } },
            ],
        }],
    },
    {
        id: 'stats',
        name: 'Chiffres clés',
        icon: 'bar_chart',
        description: '4 indicateurs chiffrés',
        category: 'Contenu',
        settings: { bgColor: 'surface-container-low', padding: { t: 48, r: 24, b: 48, l: 24 } },
        columns: [{ width: 100, widgets: [{ type: 'stats' }] }],
    },
    {
        id: 'cta-band',
        name: 'Bandeau CTA',
        icon: 'ads_click',
        description: 'Appel à l\'action sur fond rouge CAMA',
        category: 'CTA',
        columns: [{ width: 100, widgets: [{ type: 'cta' }] }],
    },
    {
        id: 'cta-contact',
        name: 'CTA contact',
        icon: 'support_agent',
        description: 'Invitation à contacter la CAMA',
        category: 'CTA',
        columns: [{
            width: 100,
            widgets: [{
                type: 'cta',
                content: { title: 'Besoin d\'un accompagnement ?', text: 'Notre équipe vous répond du lundi au vendredi.', button: 'Nous contacter', href: '/contact' },
                style: { align: 'center', bgColor: 'secondary', textColor: 'white', radius: 16 },
            }],
        }],
    },
    {
        id: 'faq',
        name: 'FAQ',
        icon: 'quiz',
        description: 'Questions fréquentes en accordéon',
        category: 'Contenu',
        columns: [{
            width: 100,
            widgets: [
                { type: 'heading', content: { text: 'Questions fréquentes', tag: 'h2' }, style: { align: 'center', size: 'text-3xl', weight: 'font-bold' } },
                { type: 'accordion' },
            ],
        }],
    },
    {
        id: 'faq-elementor',
        name: 'FAQ (Elementor)',
        icon: 'expand_circle_down',
        description: 'Accordéon Elementor avec 3 questions',
        category: 'Contenu',
        columns: [{
            width: 100,
            widgets: [{
                type: 'elementor_accordion',
                content: {
                    items: [
                        { title: 'Qui peut bénéficier de la CAMA ?', content: 'Les militaires en activité et leurs ayants droit.' },
                        { title: 'Comment soumettre un dossier ?', content: 'Via votre espace assuré ou auprès d\'un guichet CAMA.' },
                        { title: 'Quels sont les délais de remboursement ?', content: 'Sous 15 jours ouvrés en moyenne après validation.' },
                    ],
                },
            }],
        }],
    },
    {
        id: 'iconboxes-3',
        name: '3 encarts icône',
        icon: 'dashboard_customize',
        description: '3 colonnes avec icône, titre et texte',
        category: 'Contenu',
        settings: { padding: { t: 48, r: 16, b: 48, l: 16 } },
        columns: [
            { width: 33, widgets: [{ type: 'iconbox', content: { icon: 'medical_services', title: 'Soins', text: 'Prise en charge des consultations et hospitalisations.' } }] },
            { width: 34, widgets: [{ type: 'iconbox', content: { icon: 'payments', title: 'Remboursements', text: 'Suivi transparent de vos demandes.' } }] },
            { width: 33, widgets: [{ type: 'iconbox', content: { icon: 'groups', title: 'Ayants droit', text: 'Couverture étendue à toute la famille.' } }] },
        ],
    },
    {
        id: 'posts-grid',
        name: 'Actualités récentes',
        icon: 'article',
        description: 'Grille d\'articles CMS dynamiques',
        category: 'Dynamique',
        columns: [{
            width: 100,
            widgets: [{
                type: 'elementor_posts',
                content: { title: 'Dernières actualités', source: 'cms_articles', count: 3 },
                style: { columns: 3, gap: 24 },
            }],
        }],
    },
    {
        id: 'text-image-row',
        name: 'Texte + image',
        icon: 'photo_library',
        description: 'Contenu éditorial avec visuel',
        category: 'Contenu',
        columns: [
            { width: 60, widgets: [
                { type: 'heading', content: { text: 'Notre engagement', tag: 'h2' }, style: { size: 'text-2xl', weight: 'font-bold' } },
                { type: 'text', content: { html: '<p>Depuis sa création, la CAMA garantit l\'accès aux soins pour les forces armées et leurs familles.</p><p>Notre réseau couvre l\'ensemble du territoire avec des antennes régionales.</p>' } },
            ] },
            { width: 40, widgets: [{ type: 'image', content: { src: '/images/CAMA_1.jfif', alt: 'CAMA', ratio: 'aspect-square' }, style: { radius: 12 } }] },
        ],
    },
];

export const SECTION_LIBRARY_CATEGORIES = [...new Set(SECTION_LIBRARY.map(t => t.category))];

/**
 * Construit une section normalisée à partir d'un template.
 * @param {object} template
 * @param {function} uid
 * @param {function} createWidget
 * @param {function} spacing
 */
export function buildSectionFromTemplate(template, uid, createWidget, spacing) {
    const defaultSectionSettings = {
        contentWidth: 'boxed',
        bgColor: 'transparent',
        padding: { t: 48, r: 16, b: 48, l: 16 },
        margin: { t: 0, r: 0, b: 0, l: 0 },
        radius: 0,
        bgImage: '',
        minHeight: 0,
        animation: 'none',
        hidden: false,
    };

    return {
        uid: uid(),
        type: 'section',
        settings: {
            ...defaultSectionSettings,
            ...(template.settings ?? {}),
            padding: spacing(template.settings?.padding, defaultSectionSettings.padding),
            margin: spacing(template.settings?.margin),
        },
        columns: (template.columns ?? []).map((col) => ({
            uid: uid(),
            type: 'column',
            width: Number(col.width ?? 100),
            settings: {
                padding: { t: 0, r: 0, b: 0, l: 0 },
                margin: { t: 0, r: 0, b: 0, l: 0 },
                bgColor: '',
                radius: 0,
                animation: 'none',
                hidden: false,
                ...(col.settings ?? {}),
                padding: spacing(col.settings?.padding),
                margin: spacing(col.settings?.margin),
            },
            widgets: (col.widgets ?? []).map((partial) => {
                const base = createWidget(partial.type);
                return {
                    ...base,
                    uid: uid(),
                    content: { ...base.content, ...(partial.content ?? {}) },
                    style: { ...base.style, ...(partial.style ?? {}) },
                    advanced: { ...base.advanced, ...(partial.advanced ?? {}) },
                };
            }),
        })),
    };
}
