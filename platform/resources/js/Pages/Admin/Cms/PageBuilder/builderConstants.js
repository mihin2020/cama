// Constantes statiques du Page Builder (données pures, sans état réactif).
// Extrait de Index.vue pour alléger le composant.

export const PALETTE = {
    primary: '#9e001f',
    secondary: '#006e27',
    tertiary: '#745b00',
    'surface-container-low': '#f6f3f2',
    'surface-container': '#f0eded',
    'surface-container-high': '#eae7e7',
    'outline-variant': '#e5bdbb',
    'on-surface': '#1b1c1c',
    'on-surface-variant': '#5c403f',
    white: '#ffffff',
    transparent: 'transparent',
};

export const ANIMATIONS = {
    none: 'Aucune',
    fade: 'Fade',
    'slide-up': 'Slide haut',
    'slide-left': 'Slide gauche',
    zoom: 'Zoom',
};

export const ELEMENTOR_TABS = {
    content: 'Contenu',
    style: 'Style',
    advanced: 'Avancé',
};

export const ELEMENTOR_ADVANCED_SECTIONS = [
    { key: 'layout', label: 'Mise en page', controls: ['elementWidth', 'customWidth', 'position', 'zIndex'] },
    { key: 'motion', label: 'Mouvement', controls: ['animation', 'animationDuration', 'animationDelay'] },
    { key: 'transform', label: 'Transformations', controls: ['rotate', 'translateX', 'translateY', 'scale', 'skewX', 'skewY'] },
    { key: 'background', label: 'Arrière-plan', controls: ['backgroundType', 'backgroundColor', 'backgroundImage'] },
    { key: 'border', label: 'Bordure', controls: ['borderType', 'borderWidth', 'borderColor', 'borderRadius', 'boxShadow'] },
    { key: 'mask', label: 'Masque', controls: ['maskSwitch', 'maskShape', 'maskImage', 'maskSize', 'maskPosition'] },
    { key: 'responsive', label: 'Responsive', controls: ['hideDesktop', 'hideTablet', 'hideMobile'] },
    { key: 'attributes', label: 'Attributs', controls: ['elementId', 'cssClasses', 'customAttributes'] },
    { key: 'customCss', label: 'CSS personnalisé', controls: ['customCss'] },
];

export const ELEMENTOR_WIDGET_SCHEMAS = {
    elementor_heading: {
        content: [
            { key: 'title', label: 'Titre', type: 'textarea' },
            { key: 'link.url', label: 'Lien', type: 'text' },
            { key: 'tag', label: 'Taille HTML', type: 'select', options: ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span', 'p'] },
            { key: 'size', label: 'Taille', type: 'text' },
            { key: 'align', label: 'Alignement', type: 'select', target: 'style', options: ['', 'left', 'center', 'right'] },
        ],
        style: [
            { key: 'titleColor', label: 'Couleur', type: 'color' },
            { key: 'align', label: 'Alignement', type: 'responsive-align' },
            { key: 'typography', label: 'Typographie', type: 'typography' },
            { key: 'textStroke', label: 'Contour de texte', type: 'text-stroke' },
            { key: 'textShadow', label: 'Ombre du texte', type: 'text-shadow' },
            { key: 'blendMode', label: 'Mode de fusion', type: 'text' },
        ],
    },
    elementor_text_editor: {
        content: [
            { key: 'html', label: 'Éditeur de texte', type: 'textarea' },
        ],
        style: [
            { key: 'textColor', label: 'Couleur texte', type: 'color' },
            { key: 'align', label: 'Alignement', type: 'responsive-align' },
            { key: 'typography', label: 'Typographie', type: 'typography' },
        ],
    },
    elementor_button: {
        content: [
            { key: 'text', label: 'Texte', type: 'text' },
            { key: 'link.url', label: 'Lien', type: 'text' },
            { key: 'icon', label: 'Icône', type: 'text' },
            { key: 'iconPosition', label: 'Position icône', type: 'select', options: ['before', 'after'] },
        ],
        style: [
            { key: 'textColor', label: 'Couleur texte', type: 'color' },
            { key: 'bgColor', label: 'Couleur fond', type: 'color' },
            { key: 'align', label: 'Alignement', type: 'responsive-align' },
            { key: 'typography', label: 'Typographie', type: 'typography' },
            { key: 'borderRadius', label: 'Arrondi', type: 'number' },
        ],
    },
    elementor_image: {
        content: [
            { key: 'src', label: 'Image', type: 'text' },
            { key: 'alt', label: 'Texte alternatif', type: 'text' },
            { key: 'caption', label: 'Légende', type: 'text' },
            { key: 'link.url', label: 'Lien', type: 'text' },
        ],
        style: [
            { key: 'width', label: 'Largeur', type: 'number' },
            { key: 'align', label: 'Alignement', type: 'responsive-align' },
            { key: 'borderRadius', label: 'Arrondi', type: 'number' },
            { key: 'boxShadow', label: 'Ombre', type: 'text-shadow' },
        ],
    },
    elementor_video: {
        content: [
            { key: 'url', label: 'URL vidéo', type: 'text' },
            { key: 'source', label: 'Source', type: 'select', options: ['youtube', 'vimeo', 'self_hosted'] },
            { key: 'autoplay', label: 'Lecture automatique', type: 'checkbox' },
            { key: 'controls', label: 'Contrôles', type: 'checkbox' },
        ],
        style: [
            { key: 'aspectRatio', label: 'Ratio', type: 'select', options: ['16/9', '4/3', '1/1'] },
            { key: 'borderRadius', label: 'Arrondi', type: 'number' },
        ],
    },
    elementor_icon: {
        content: [
            { key: 'icon', label: 'Icône', type: 'text' },
            { key: 'link.url', label: 'Lien', type: 'text' },
        ],
        style: [
            { key: 'textColor', label: 'Couleur', type: 'color' },
            { key: 'size', label: 'Taille', type: 'number' },
            { key: 'align', label: 'Alignement', type: 'responsive-align' },
        ],
    },
    elementor_icon_box: {
        content: [
            { key: 'icon', label: 'Icône', type: 'text' },
            { key: 'title', label: 'Titre', type: 'text' },
            { key: 'text', label: 'Description', type: 'textarea' },
            { key: 'link.url', label: 'Lien', type: 'text' },
        ],
        style: [
            { key: 'textColor', label: 'Couleur texte', type: 'color' },
            { key: 'iconColor', label: 'Couleur icône', type: 'color' },
            { key: 'align', label: 'Alignement', type: 'responsive-align' },
            { key: 'typography', label: 'Typographie', type: 'typography' },
        ],
    },
    elementor_image_box: {
        content: [
            { key: 'src', label: 'Image', type: 'text' },
            { key: 'title', label: 'Titre', type: 'text' },
            { key: 'text', label: 'Description', type: 'textarea' },
            { key: 'link.url', label: 'Lien', type: 'text' },
        ],
        style: [
            { key: 'align', label: 'Alignement', type: 'responsive-align' },
            { key: 'imageWidth', label: 'Largeur image', type: 'number' },
            { key: 'borderRadius', label: 'Arrondi', type: 'number' },
        ],
    },
    elementor_accordion: {
        content: [
            { key: 'items', label: 'Éléments', type: 'repeater', fields: ['title', 'content'] },
        ],
        style: [
            { key: 'titleColor', label: 'Couleur titre', type: 'color' },
            { key: 'textColor', label: 'Couleur contenu', type: 'color' },
            { key: 'typography', label: 'Typographie', type: 'typography' },
        ],
    },
    elementor_tabs: {
        content: [
            { key: 'items', label: 'Onglets', type: 'repeater', fields: ['title', 'content'] },
        ],
        style: [
            { key: 'titleColor', label: 'Couleur titre', type: 'color' },
            { key: 'textColor', label: 'Couleur contenu', type: 'color' },
            { key: 'typography', label: 'Typographie', type: 'typography' },
        ],
    },
    elementor_carousel: {
        content: [
            { key: 'items', label: 'Slides', type: 'repeater', fields: ['title', 'text', 'image'] },
        ],
        style: [
            { key: 'slidesPerView', label: 'Slides visibles', type: 'number' },
            { key: 'gap', label: 'Espacement', type: 'number' },
        ],
    },
    elementor_container: {
        content: [
            { key: 'direction', label: 'Direction', type: 'select', options: ['row', 'column'] },
            { key: 'gap', label: 'Gap', type: 'number' },
        ],
        style: [
            { key: 'bgColor', label: 'Fond', type: 'color' },
            { key: 'borderRadius', label: 'Arrondi', type: 'number' },
        ],
    },
};

export const WIDGETS = {
    heading: {
        label: 'Titre',
        icon: 'title',
        category: 'Base',
        defaults: () => ({
            type: 'heading',
            content: { text: 'Titre de section', tag: 'h2' },
            style: { align: 'left', textColor: 'on-surface', size: 'text-3xl', weight: 'font-bold' },
        }),
    },
    text: {
        label: 'Texte',
        icon: 'notes',
        category: 'Base',
        defaults: () => ({
            type: 'text',
            content: { html: 'Saisissez votre texte ici. La CAMA accompagne les familles militaires au quotidien.' },
            style: { align: 'left', textColor: 'on-surface-variant', size: 'text-base' },
        }),
    },
    button: {
        label: 'Bouton',
        icon: 'smart_button',
        category: 'Base',
        defaults: () => ({
            type: 'button',
            content: { text: 'En savoir plus', href: '#', icon: '' },
            style: { align: 'left', bgColor: 'primary', textColor: 'white' },
        }),
    },
    image: {
        label: 'Image',
        icon: 'image',
        category: 'Base',
        defaults: () => ({
            type: 'image',
            content: { src: '/images/CAMA_1.jfif', alt: '', ratio: 'aspect-video' },
            style: { align: 'center', radius: 8 },
        }),
    },
    iconbox: {
        label: 'Encart icône',
        icon: 'dashboard_customize',
        category: 'Composants',
        defaults: () => ({
            type: 'iconbox',
            content: { icon: 'health_and_safety', title: 'Protection santé', text: 'Une couverture complète pour vous et vos ayants droit.' },
            style: { align: 'center' },
        }),
    },
    cards: {
        label: 'Cartes',
        icon: 'grid_view',
        category: 'Composants',
        defaults: () => ({
            type: 'cards',
            content: {
                columns: 3,
                items: [
                    { icon: 'medical_services', title: 'Soins', text: 'Prise en charge des consultations.' },
                    { icon: 'payments', title: 'Remboursements', text: 'Traitement rapide de vos demandes.' },
                    { icon: 'groups', title: 'Ayants droit', text: 'Couverture étendue à la famille.' },
                ],
            },
            style: {},
        }),
    },
    stats: {
        label: 'Chiffres clés',
        icon: 'bar_chart',
        category: 'Composants',
        defaults: () => ({
            type: 'stats',
            content: {
                items: [
                    { value: '45 000', label: 'Assurés couverts' },
                    { value: '120', label: 'Centres partenaires' },
                    { value: '80%', label: 'Taux de prise en charge' },
                ],
            },
            style: { bgColor: 'surface-container-low', radius: 12 },
        }),
    },
    accordion: {
        label: 'FAQ',
        icon: 'expand_circle_down',
        category: 'Composants',
        defaults: () => ({
            type: 'accordion',
            content: {
                items: [
                    { question: 'Qui peut bénéficier de la CAMA ?', answer: 'Les militaires et leurs ayants droit.' },
                    { question: 'Comment soumettre un dossier ?', answer: 'Via votre espace assuré ou auprès d’un guichet CAMA.' },
                ],
            },
            style: {},
        }),
    },
    cta: {
        label: 'Appel à l’action',
        icon: 'ads_click',
        category: 'Composants',
        defaults: () => ({
            type: 'cta',
            content: { title: 'Besoin d’un accompagnement ?', text: 'Notre équipe vous répond du lundi au vendredi.', button: 'Nous contacter', href: '/contact' },
            style: { align: 'center', bgColor: 'primary', textColor: 'white', radius: 16 },
        }),
    },
    elementor_heading: {
        label: 'Heading Elementor',
        icon: 'title',
        category: 'Elementor',
        defaults: () => ({
            type: 'elementor_heading',
            content: {
                title: " Chaque <span style='color:#1950D1'> compétence </span> vous rapproche de vos <span style='color:#1950D1'>  ambitions.</span>  ",
                tag: 'h1',
                link: { url: '', is_external: '', nofollow: '', custom_attributes: '' },
                size: 'default',
                rawSettings: {
                    title: " Chaque <span style='color:#1950D1'> compétence </span> vous rapproche de vos <span style='color:#1950D1'>  ambitions.</span>  ",
                    title_color: '#0A083B',
                    typography_typography: 'custom',
                    typography_font_family: 'Poppins',
                    typography_font_size: { unit: 'px', size: 49, sizes: [] },
                    typography_font_size_tablet: { unit: 'px', size: 45, sizes: [] },
                    typography_font_size_mobile: { unit: 'px', size: 35, sizes: [] },
                    typography_font_weight: '700',
                    typography_line_height: { unit: 'em', size: 1.1, sizes: [] },
                    typography_letter_spacing: { unit: 'px', size: -0.5, sizes: [] },
                    header_size: 'h1',
                    _animation: 'none',
                    align_mobile: 'center',
                },
            },
            style: {
                textColor: '#0A083B',
                titleColor: '#0A083B',
                fontFamily: 'Poppins',
                fontSize: 49,
                fontSizeTablet: 45,
                fontSizeMobile: 35,
                fontWeight: '700',
                lineHeight: 1.1,
                letterSpacing: -0.5,
                align: '',
                alignTablet: '',
                alignMobile: 'center',
                textTransform: '',
                fontStyle: '',
                textDecoration: '',
                padding: { t: 0, r: 0, b: 0, l: 0 },
                paddingTablet: { t: 0, r: 0, b: 0, l: 0 },
                paddingMobile: { t: 10, r: 0, b: 0, l: 0 },
                margin: { t: 0, r: 0, b: 0, l: 0 },
                marginTablet: { t: 0, r: 0, b: 0, l: 0 },
                marginMobile: { t: 0, r: 0, b: 0, l: 0 },
                textStrokeWidth: '',
                textStrokeColor: '#000',
                textShadow: { horizontal: 0, vertical: 0, blur: 10, color: 'rgba(0,0,0,0.3)' },
                blendMode: '',
                titleHoverColor: '',
                hoverTransitionDuration: '',
            },
            advanced: {
                animation: 'none',
                title: '',
                elementWidth: '',
                customWidth: '',
                position: '',
                zIndex: '',
                elementId: '',
                cssClasses: '',
                flexGrow: 1,
                flexShrink: 1,
                offsetX: 0,
                offsetY: 0,
                hideDesktop: '',
                hideTablet: '',
                hideMobile: '',
                background: {},
                border: {},
                boxShadow: { horizontal: 0, vertical: 0, blur: 10, spread: 0, color: 'rgba(0,0,0,0.5)' },
            },
        }),
    },
    elementor_text_editor: {
        label: 'Text Editor',
        icon: 'notes',
        category: 'Elementor',
        defaults: () => ({
            type: 'elementor_text_editor',
            content: { html: '<p>Ajoutez votre texte ici.</p>' },
            style: { textColor: '#1b1c1c', align: '', fontFamily: 'Inter', fontSize: 16, fontWeight: '400', lineHeight: 1.5 },
            advanced: defaultElementorAdvanced(),
        }),
    },
    elementor_button: {
        label: 'Button',
        icon: 'smart_button',
        category: 'Elementor',
        defaults: () => ({
            type: 'elementor_button',
            content: { text: 'Cliquez ici', link: { url: '#', is_external: '', nofollow: '', custom_attributes: '' }, icon: '', iconPosition: 'before' },
            style: { textColor: '#ffffff', bgColor: '#9e001f', align: '', fontFamily: 'Inter', fontSize: 14, fontWeight: '700', borderRadius: 8, padding: { t: 12, r: 24, b: 12, l: 24 } },
            advanced: defaultElementorAdvanced(),
        }),
    },
    elementor_image: {
        label: 'Image',
        icon: 'image',
        category: 'Elementor',
        defaults: () => ({
            type: 'elementor_image',
            content: { src: '/images/CAMA_1.jfif', alt: '', caption: '', link: { url: '', is_external: '', nofollow: '', custom_attributes: '' } },
            style: { align: '', width: 100, borderRadius: 0 },
            advanced: defaultElementorAdvanced(),
        }),
    },
    elementor_video: {
        label: 'Video',
        icon: 'play_circle',
        category: 'Elementor',
        defaults: () => ({
            type: 'elementor_video',
            content: { source: 'youtube', url: '', autoplay: false, controls: true },
            style: { aspectRatio: '16/9', borderRadius: 0 },
            advanced: defaultElementorAdvanced(),
        }),
    },
    elementor_icon: {
        label: 'Icon',
        icon: 'star',
        category: 'Elementor',
        defaults: () => ({
            type: 'elementor_icon',
            content: { icon: 'star', link: { url: '', is_external: '', nofollow: '', custom_attributes: '' } },
            style: { textColor: '#9e001f', size: 48, align: 'center' },
            advanced: defaultElementorAdvanced(),
        }),
    },
    elementor_icon_box: {
        label: 'Icon Box',
        icon: 'select_window',
        category: 'Elementor',
        defaults: () => ({
            type: 'elementor_icon_box',
            content: { icon: 'health_and_safety', title: 'Titre', text: 'Description du bloc.', link: { url: '', is_external: '', nofollow: '', custom_attributes: '' } },
            style: { iconColor: '#9e001f', textColor: '#1b1c1c', align: 'center', fontFamily: 'Inter', fontSize: 16 },
            advanced: defaultElementorAdvanced(),
        }),
    },
    elementor_image_box: {
        label: 'Image Box',
        icon: 'image_search',
        category: 'Elementor',
        defaults: () => ({
            type: 'elementor_image_box',
            content: { src: '/images/CAMA_1.jfif', title: 'Titre', text: 'Description du bloc.', link: { url: '', is_external: '', nofollow: '', custom_attributes: '' } },
            style: { align: 'center', imageWidth: 100, borderRadius: 8 },
            advanced: defaultElementorAdvanced(),
        }),
    },
    elementor_accordion: {
        label: 'Accordion',
        icon: 'expand_circle_down',
        category: 'Elementor',
        defaults: () => ({
            type: 'elementor_accordion',
            content: { items: [{ title: 'Élément 1', content: 'Contenu de l’élément 1.' }, { title: 'Élément 2', content: 'Contenu de l’élément 2.' }] },
            style: { titleColor: '#1b1c1c', textColor: '#5c403f', fontFamily: 'Inter', fontSize: 16 },
            advanced: defaultElementorAdvanced(),
        }),
    },
    elementor_tabs: {
        label: 'Tabs',
        icon: 'tab',
        category: 'Elementor',
        defaults: () => ({
            type: 'elementor_tabs',
            content: { items: [{ title: 'Onglet 1', content: 'Contenu onglet 1.' }, { title: 'Onglet 2', content: 'Contenu onglet 2.' }] },
            style: { titleColor: '#1b1c1c', textColor: '#5c403f', fontFamily: 'Inter', fontSize: 16 },
            advanced: defaultElementorAdvanced(),
        }),
    },
    elementor_carousel: {
        label: 'Carousel',
        icon: 'view_carousel',
        category: 'Elementor',
        defaults: () => ({
            type: 'elementor_carousel',
            content: { items: [{ title: 'Slide 1', text: 'Description', image: '/images/CAMA_1.jfif' }, { title: 'Slide 2', text: 'Description', image: '/images/CAMA_8.jfif' }] },
            style: { slidesPerView: 2, gap: 24 },
            advanced: defaultElementorAdvanced(),
        }),
    },
    elementor_container: {
        label: 'Container',
        icon: 'data_object',
        category: 'Elementor',
        defaults: () => ({
            type: 'elementor_container',
            content: { direction: 'column', gap: 16 },
            style: { bgColor: '', borderRadius: 0, padding: { t: 24, r: 24, b: 24, l: 24 } },
            advanced: defaultElementorAdvanced(),
        }),
    },
    elementor_form: {
        label: 'Form',
        icon: 'dynamic_form',
        category: 'Elementor',
        defaults: () => ({
            type: 'elementor_form',
            content: { title: 'Formulaire', fields: [{ label: 'Nom', type: 'text' }, { label: 'Email', type: 'email' }], button: 'Envoyer' },
            style: { bgColor: '', textColor: '#1b1c1c', borderRadius: 8 },
            advanced: defaultElementorAdvanced(),
        }),
    },
    elementor_posts: {
        label: 'Posts',
        icon: 'article',
        category: 'Elementor',
        defaults: () => ({
            type: 'elementor_posts',
            content: { title: 'Articles récents', source: 'cms_articles', count: 3 },
            style: { columns: 3, gap: 24 },
            advanced: defaultElementorAdvanced(),
        }),
    },
    elementor_loop_grid: {
        label: 'Loop Grid',
        icon: 'grid_view',
        category: 'Elementor',
        defaults: () => ({
            type: 'elementor_loop_grid',
            content: { source: 'cms_articles', count: 6 },
            style: { columns: 3, gap: 24 },
            advanced: defaultElementorAdvanced(),
        }),
    },
    elementor_menu: {
        label: 'Menu',
        icon: 'menu',
        category: 'Elementor',
        defaults: () => ({
            type: 'elementor_menu',
            content: { source: 'public_menu', layout: 'horizontal' },
            style: { align: 'center', gap: 24, textColor: '#1b1c1c' },
            advanced: defaultElementorAdvanced(),
        }),
    },
    home_hero: {
        label: 'Hero accueil',
        icon: 'view_carousel',
        category: 'Accueil',
        page: 'accueil',
        defaults: () => ({
            type: 'home_hero',
            content: {
                badgeIcon: 'health_and_safety',
                badgeText: 'CAMA Burkina Faso',
                secondaryLabel: 'Espace assuré',
                secondaryHref: '/espace-assure/connexion',
            },
            style: {},
        }),
    },
    home_pillars: {
        label: 'Piliers accueil',
        icon: 'hub',
        category: 'Accueil',
        page: 'accueil',
        defaults: () => ({
            type: 'home_pillars',
            content: {
                title: 'Nos Piliers',
                items: [
                    { icon: 'medical_services', title: 'Accès', text: 'Garantir à chaque service membre un accès immédiat à un vaste réseau de prestataires de santé agréés sur l’ensemble du territoire national.' },
                    { icon: 'diversity_3', title: 'Solidarité', text: 'Un système mutualisé où la force du collectif protège l’individu, assurant une prise en charge équitable pour tous les ayants droit.' },
                    { icon: 'visibility', title: 'Transparence', text: 'Une gestion rigoureuse et éthique des cotisations, avec des processus clairs pour les remboursements et la gestion des droits.' },
                ],
            },
            style: {},
        }),
    },
    home_key_figures: {
        label: 'Chiffres accueil',
        icon: 'monitoring',
        category: 'Accueil',
        page: 'accueil',
        defaults: () => ({
            type: 'home_key_figures',
            content: { eyebrow: 'En Chiffres', title: "La CAMA aujourd'hui" },
            style: {},
        }),
    },
    home_latest_articles: {
        label: 'Actualités accueil',
        icon: 'newspaper',
        category: 'Accueil',
        page: 'accueil',
        defaults: () => ({
            type: 'home_latest_articles',
            content: { eyebrow: 'Informations', title: 'Dernières Actualités', linkLabel: 'Voir tout le flux', linkHref: '/actualites' },
            style: {},
        }),
    },
    about_hero: {
        label: 'Hero à propos',
        icon: 'info',
        category: 'À propos',
        page: 'apropos',
        defaults: () => ({
            type: 'about_hero',
            content: { badge: 'Institution Nationale', title: 'Protéger ceux qui nous protègent', text: "Découvrez l'histoire, les missions et l'engagement de la Caisse d'Assurance Maladie des Armées au service du personnel de défense du Burkina Faso." },
            style: {},
        }),
    },
    about_director: {
        label: 'Mot du Directeur',
        icon: 'record_voice_over',
        category: 'À propos',
        page: 'apropos',
        defaults: () => ({
            type: 'about_director',
            content: {
                eyebrow: 'Gouvernance',
                title: 'Mot du Directeur Général',
                imageSrc: '/images/directeur.jpg',
                badge: 'En fonction depuis le 13 nov. 2025',
                name: 'Ph. Lt-Colonel Ousmane Sinaré',
                role: 'Directeur Général de la CAMA',
                quote: "Bienvenue sur l'espace d'information de la CAMA. Nous nous engageons chaque jour à moderniser nos services pour garantir une prise en charge rapide, efficace et humaine.",
                text: "Installé le 13 novembre 2025, le pharmacien lieutenant-colonel Ousmane Sinaré succède au médecin colonel-major Saïdou Yonaba à la tête de la CAMA.",
                linkLabel: 'Contacter la Direction Générale',
                linkHref: '/contact',
                stats: [{ icon: 'calendar_today', value: '2020', label: 'Création par décret', tone: 'primary' }, { icon: 'health_and_safety', value: '80%', label: 'Taux de couverture', tone: 'secondary' }],
            },
            style: {},
        }),
    },
    about_timeline: {
        label: 'Histoire à propos',
        icon: 'timeline',
        category: 'À propos',
        page: 'apropos',
        defaults: () => ({
            type: 'about_timeline',
            content: {
                eyebrow: 'Notre Histoire',
                title: 'Notre Évolution',
                text: "De la mutuelle traditionnelle à une caisse d'assurance moderne et performante.",
                items: [{ icon: 'history', date: 'Avant 2020', title: "L'ère MUFAN", text: "Originairement gérée sous forme de mutuelle, l'institution a posé les bases de la solidarité.", tone: 'primary' }, { icon: 'gavel', date: '16 Avril 2020', title: 'Création de la CAMA', text: 'Fondée par décret pour répondre aux exigences de la protection sociale moderne.', tone: 'secondary' }, { icon: 'rocket_launch', date: '13 Février 2025', title: 'Lancement Officiel', text: 'Lancement officiel de la CAMA à Ouagadougou.', tone: 'tertiary' }],
                todayBadge: "Aujourd'hui",
                todayTitle: 'La CAMA, une institution pivot',
                todayText: "Rattachée au Ministère de la Guerre et de la Défense patriotique, elle garantit l'accès fiable et équitable aux soins.",
            },
            style: {},
        }),
    },
    about_missions: {
        label: 'Missions à propos',
        icon: 'verified_user',
        category: 'À propos',
        page: 'apropos',
        defaults: () => ({
            type: 'about_missions',
            content: {
                title: 'Nos Missions Régaliennes',
                text: "Le cadre d'action de la CAMA est défini par une vision stratégique de protection globale.",
                linkLabel: 'Consulter les textes officiels',
                linkHref: '/ressources',
                items: [{ icon: 'medical_services', title: 'Gestion des Soins', items: ['Remboursement des frais médicaux', 'Prise en charge des hospitalisations', 'Conventionnement hospitalier'], tone: 'secondary' }, { icon: 'family_restroom', title: 'Protection de la Famille', items: ['Extension aux ayants-droit', 'Soutien aux veuves et orphelins', 'Programmes de prévention santé'], tone: 'primary' }, { icon: 'shield_person', title: 'Appui Opérationnel', items: ['Soutien sanitaire en mission', 'Évacuations sanitaires', 'Expertise médicale militaire'], tone: 'tertiary' }],
            },
            style: {},
        }),
    },
    about_cta: {
        label: 'CTA à propos',
        icon: 'support_agent',
        category: 'À propos',
        page: 'apropos',
        defaults: () => ({
            type: 'about_cta',
            content: {
                title: "Besoin d'aide pour vos démarches ?",
                text: "Consultez notre guide de l'assuré ou contactez notre permanence téléphonique.",
                primaryLabel: "Guide de l'assuré",
                primaryHref: '/ressources',
                secondaryLabel: 'Nous appeler',
                secondaryHref: 'tel:+22625300000',
            },
            style: {},
        }),
    },
    resources_hero: {
        label: 'Hero ressources',
        icon: 'folder_open',
        category: 'Ressources',
        page: 'ressources',
        defaults: () => ({
            type: 'resources_hero',
            content: { badgeIcon: 'folder_open', badge: 'Centre de ressources', title: 'Ressources & documents', text: 'Téléchargez les formulaires, guides, attestations et textes de référence de la CAMA, classés par catégorie.' },
            style: {},
        }),
    },
    resources_listing: {
        label: 'Liste ressources',
        icon: 'download',
        category: 'Ressources',
        page: 'ressources',
        defaults: () => ({
            type: 'resources_listing',
            content: { emptyText: 'Aucun document dans cette catégorie pour le moment.', downloadLabel: 'Télécharger' },
            style: {},
        }),
    },
    news_hero: {
        label: 'Hero actualités',
        icon: 'newspaper',
        category: 'Actualités',
        page: 'actualites',
        defaults: () => ({
            type: 'news_hero',
            content: { title: 'Actualités & Presse', text: "Suivez les dernières évolutions de la Caisse d'Assurance Maladie des Armées." },
            style: {},
        }),
    },
    news_filters: {
        label: 'Filtres actualités',
        icon: 'filter_list',
        category: 'Actualités',
        page: 'actualites',
        defaults: () => ({
            type: 'news_filters',
            content: { searchPlaceholder: 'Rechercher un article…' },
            style: {},
        }),
    },
    news_listing: {
        label: 'Liste actualités',
        icon: 'article',
        category: 'Actualités',
        page: 'actualites',
        defaults: () => ({
            type: 'news_listing',
            content: { readLabel: 'Lire la suite', cardReadLabel: "Lire l'article", emptyText: 'Aucun article ne correspond à votre recherche.' },
            style: {},
        }),
    },
    news_newsletter: {
        label: 'Newsletter actualités',
        icon: 'mail',
        category: 'Actualités',
        page: 'actualites',
        defaults: () => ({
            type: 'news_newsletter',
            content: { title: 'Restez informé', text: 'Recevez les dernières notes de service et actualités de la CAMA.', placeholder: 'votre@email.bf', buttonLabel: "S'abonner" },
            style: {},
        }),
    },
    contact_hero: {
        label: 'Hero contact',
        icon: 'contact_mail',
        category: 'Contact',
        page: 'contact',
        defaults: () => ({
            type: 'contact_hero',
            content: { eyebrow: 'Nous trouver & nous écrire', title: 'Contact & Cartographie', text: 'Envoyez-nous un message ou consultez nos antennes sur la carte.' },
            style: {},
        }),
    },
    contact_form: {
        label: 'Formulaire contact',
        icon: 'send',
        category: 'Contact',
        page: 'contact',
        defaults: () => ({
            type: 'contact_form',
            content: { title: 'Envoyez-nous un message', text: 'Demande administrative, réclamation ou information réponse sous 48h ouvrées.', buttonLabel: 'Envoyer le message', successLabel: 'Message envoyé' },
            style: {},
        }),
    },
    contact_map: {
        label: 'Carte contact',
        icon: 'map',
        category: 'Contact',
        page: 'contact',
        defaults: () => ({
            type: 'contact_map',
            content: { title: 'Cartographie : antennes, partenaires & centres de santé', text: 'Sélectionnez un point pour afficher la carte et les coordonnées.', searchPlaceholder: 'Rechercher un point…', directionsLabel: 'Itinéraire' },
            style: {},
        }),
    },
    legal_content: {
        label: 'Contenu légal',
        icon: 'gavel',
        category: 'Mentions légales',
        page: 'mention_legales',
        defaults: () => ({
            type: 'html',
            content: {
                html: '<section class="bg-primary-container text-white p-12 rounded-xl mb-8"><h1 class="font-display-lg text-display-lg mb-4">Informations Légales & Confidentialité</h1><p class="font-body-lg text-body-lg max-w-2xl opacity-90">Engagement de la CAMA pour la transparence, la sécurité et le respect de la vie privée.</p></section><article class="bg-white p-stack-lg rounded-xl border border-outline-variant"><h2 class="font-headline-lg text-headline-lg mb-4">Politique de Protection des Données</h2><p class="text-on-surface-variant">Conformité à la loi burkinabè et secret médical.</p></article><article class="bg-white p-stack-lg rounded-xl border border-outline-variant mt-4"><h2 class="font-headline-lg text-headline-lg mb-4">Droits des assurés</h2><p class="text-on-surface-variant">Accès, rectification et opposition via le DPO.</p></article>',
            },
            style: {},
        }),
    },
    accessibility_content: {
        label: 'Contenu accessibilité',
        icon: 'accessibility_new',
        category: 'Accessibilité',
        page: 'accessibilite',
        defaults: () => ({
            type: 'html',
            content: {
                html: '<section class="bg-primary-container text-white p-8 md:p-10 rounded-xl mb-8"><h1 class="font-headline-lg text-2xl md:text-headline-lg mb-3">Déclaration d’accessibilité</h1><p class="text-white/90 max-w-3xl">La CAMA s’engage à rendre son site internet accessible conformément aux référentiels WCAG 2.1 niveau AA.</p></section><article class="bg-white p-6 rounded-xl border border-outline-variant"><h2 class="font-bold text-lg mb-3">Signaler un problème d’accessibilité</h2><p class="text-sm text-on-surface-variant">Contactez accessibilite@cama.bf.</p></article>',
            },
            style: {},
        }),
    },
    services_hero: {
        label: 'Hero services',
        icon: 'medical_services',
        category: 'Services',
        page: 'services',
        defaults: () => ({
            type: 'services_hero',
            content: { badge: 'Prestations & Garanties', title: 'Une protection médicale complète pour nos forces armées', text: "Découvrez l'ensemble des actes couverts par la CAMA, de la consultation de routine aux interventions chirurgicales complexes.", primaryLabel: 'Voir le détail', primaryHref: '#coverage', secondaryLabel: 'Foire aux questions', secondaryHref: '#faq' },
            style: {},
        }),
    },
    services_stats: {
        label: 'Stats services',
        icon: 'monitoring',
        category: 'Services',
        page: 'services',
        defaults: () => ({
            type: 'services_stats',
            content: { items: [{ icon: 'health_and_safety', value: '80%', label: 'Couverture moyenne', tone: 'primary' }, { icon: 'payments', value: '5.5%', label: 'Taux de cotisation', tone: 'secondary' }, { icon: 'family_restroom', value: '6 mois', label: 'Couverture des ayants droit après décès', tone: 'tertiary' }, { icon: 'school', value: '27 ans', label: 'Âge limite enfant à charge (études)', tone: 'dark' }] },
            style: {},
        }),
    },
    services_coverage: {
        label: 'Garanties services',
        icon: 'health_and_safety',
        category: 'Services',
        page: 'services',
        defaults: () => ({
            type: 'services_coverage',
            content: { eyebrow: 'Garanties', title: 'Détail des prestations couvertes', items: [{ icon: 'medical_services', title: 'Consultations & Visites', text: 'Médecine générale, spécialités médicales et urgences militaires auprès des prestataires conventionnés.', rate: '80%', tone: 'primary' }, { icon: 'biotech', title: 'Biologie', text: 'Analyses de sang, urines et prélèvements biologiques dans les laboratoires agréés.', rate: '70 - 80%', tone: 'tertiary' }, { icon: 'radiology', title: 'Radiologie', text: 'Imagerie médicale, scanners, IRM et échographies prescrits par un médecin.', rate: '75%', tone: 'secondary' }, { icon: 'local_hospital', title: 'Actes Médico-Chirurgicaux', text: 'Hospitalisation, interventions chirurgicales et pharmacie hospitalière, en tiers-payant chez les établissements conventionnés.', rate: '90%', tone: 'dark' }] },
            style: {},
        }),
    },
    services_steps: {
        label: 'Démarche services',
        icon: 'route',
        category: 'Services',
        page: 'services',
        defaults: () => ({
            type: 'services_steps',
            content: { eyebrow: 'Démarche', title: 'Comment ça marche ?', items: [{ icon: 'badge', title: 'Présentez votre carte', text: "Carte d'assuré CAMA à présenter chez le prestataire conventionné.", tone: 'primary' }, { icon: 'stethoscope', title: 'Recevez les soins', text: 'Consultation, analyses ou hospitalisation selon votre besoin.', tone: 'secondary' }, { icon: 'description', title: 'Déposez le dossier', text: 'Feuille de soins et facture originale à votre guichet CAMA.', tone: 'tertiary' }, { icon: 'account_balance_wallet', title: 'Soyez remboursé', text: 'Remboursement par virement bancaire après traitement de votre dossier.', tone: 'dark' }] },
            style: {},
        }),
    },
    services_partners: {
        label: 'Partenaires services',
        icon: 'local_hospital',
        category: 'Services',
        page: 'services',
        defaults: () => ({
            type: 'services_partners',
            content: { eyebrow: 'Réseau de soins', title: 'Partenaires & centres de santé', text: 'Le réseau conventionné de la CAMA pour la prise en charge de ses assurés.' },
            style: {},
        }),
    },
    services_faq: {
        label: 'FAQ services',
        icon: 'quiz',
        category: 'Services',
        page: 'services',
        defaults: () => ({
            type: 'services_faq',
            content: { eyebrow: 'Questions fréquentes', title: 'Foire aux questions', text: 'Tout ce que vous devez savoir sur vos remboursements et vos démarches auprès de la CAMA.', cardTitle: 'Une autre question ?', cardText: 'Notre équipe administrative reste à votre disposition.', linkLabel: 'Nous contacter', linkHref: '/contact' },
            style: {},
        }),
    },
    services_cta: {
        label: 'CTA services',
        icon: 'campaign',
        category: 'Services',
        page: 'services',
        defaults: () => ({
            type: 'services_cta',
            content: { title: 'Une question sur votre prise en charge ?', text: 'Notre équipe administrative est disponible pour vous accompagner dans vos démarches de remboursement.', primaryLabel: 'Nous contacter', primaryHref: '/contact', secondaryLabel: 'Urgence : 112', secondaryHref: 'tel:112' },
            style: {},
        }),
    },
    html: {
        label: 'HTML',
        icon: 'code',
        category: 'Avancé',
        defaults: () => ({
            type: 'html',
            content: { html: '<p>Collez ou éditez votre HTML ici.</p>' },
            style: {},
        }),
    },
};

export const SYSTEM_PAGE_WIDGETS = {
    accueil: ['home_hero', 'home_pillars', 'home_key_figures', 'home_latest_articles'],
    apropos: ['about_hero', 'about_director', 'about_timeline', 'about_missions', 'about_cta'],
    services: ['services_hero', 'services_stats', 'services_coverage', 'services_steps', 'services_partners', 'services_faq', 'services_cta'],
    ressources: ['resources_hero', 'resources_listing'],
    actualites: ['news_hero', 'news_filters', 'news_listing', 'news_newsletter'],
    contact: ['contact_hero', 'contact_form', 'contact_map'],
    mention_legales: ['legal_content'],
    accessibilite: ['accessibility_content'],
};

// Presets d'appareils pour l'aperçu responsive (largeurs/hauteurs réelles).
export const DEVICE_PRESETS = {
    desktop: { label: 'Bureau', icon: 'desktop_windows', width: 1280, height: 800 },
    tablet: { label: 'Tablette', icon: 'tablet_mac', width: 768, height: 1024 },
    mobile: { label: 'Mobile', icon: 'smartphone', width: 390, height: 844 },
};

