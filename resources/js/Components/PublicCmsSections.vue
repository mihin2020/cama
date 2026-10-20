<script setup>
import CamaGoogleMap from '@/Components/CamaGoogleMap.vue';
import ElementorAccordion from '@/Components/PublicWidgets/ElementorAccordion.vue';
import ElementorCarousel from '@/Components/PublicWidgets/ElementorCarousel.vue';
import ElementorPosts from '@/Components/PublicWidgets/ElementorPosts.vue';
import ElementorTabs from '@/Components/PublicWidgets/ElementorTabs.vue';
import { pbPreview, postToBuilder } from '@/Components/PageBuilder/pageBuilderPreview';
import { legalAnchor, legalBlocks } from '@/Utils/legalText';
import CamaPagination from '@/Components/CamaPagination.vue';
import { useClientPagination } from '@/Composables/usePagination';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, defineAsyncComponent, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

// Chargé seulement dans l'iframe du page builder, jamais sur le site public.
const PageBuilderBridge = defineAsyncComponent(() => import('@/Components/PageBuilder/PageBuilderBridge.vue'));

const props = defineProps({
    sections: {
        type: Array,
        default: () => [],
    },
    systemData: {
        type: Object,
        default: () => ({}),
    },
});

const page = usePage();
// Rendu dans l'éditeur de pages (canevas ou aperçu) : les sections viennent de l'éditeur.
const builderPreview = computed(() => page.props.builderPreview ?? null);
const pbEditing = computed(() => builderPreview.value?.mode === 'edit');
const renderedSections = computed(() => (builderPreview.value && pbPreview.sections) ? pbPreview.sections : props.sections);

function pbAttr(node) {
    return builderPreview.value ? node?.uid : undefined;
}
const flashSuccess = computed(() => page.props.flash?.success ?? '');
const flashError = computed(() => page.props.flash?.error ?? '');
const fallbackSlide = {
    title: 'La santé de nos héros, notre priorité',
    subtitle: 'Plateforme institutionnelle de la Caisse d’Assurance Maladie des Armées — enrôlement des ayants droit et services numériques.',
    image_src: '/images/CAMA_8.jfif',
    link_url: '/inscription-assure',
    link_label: 'Commencer l’enrôlement',
};

const activeSlide = ref(0);
let slideTimer = null;

const homeSlides = computed(() => props.systemData.slides?.length ? props.systemData.slides : [fallbackSlide]);
const homeKeyFigures = computed(() => props.systemData.keyFigures ?? []);
const homeArticles = computed(() => props.systemData.latestArticles ?? props.systemData.articles ?? []);
const homeFeaturedArticle = computed(() => homeArticles.value.find(article => article.featured) ?? homeArticles.value[0] ?? null);
const homeSideArticles = computed(() => homeArticles.value.filter(article => !homeFeaturedArticle.value || article.id !== homeFeaturedArticle.value.id).slice(0, 2));
const serviceFaqs = computed(() => props.systemData.faqs ?? []);
const servicePartners = computed(() => props.systemData.partners ?? []);
// Prestataires (page Services) : recherche, ville, type et affichage progressif (plus de 200 fiches).
const PARTNER_PAGE = 12;
const partnerTypes = [
    { key: 'Tous', label: 'Tous', icon: 'apps' },
    { key: 'Centre de santé', label: 'Centres de santé', icon: 'local_hospital' },
    { key: 'Partenaire', label: 'Pharmacies & laboratoires', icon: 'medication' },
];
const partnerSearch = ref('');
const partnerCity = ref('');
const partnerType = ref('Tous');
const partnerLimit = ref(PARTNER_PAGE);
const partnerCities = computed(() => {
    const counts = new Map();
    servicePartners.value.forEach((partner) => {
        if (partner.city) counts.set(partner.city, (counts.get(partner.city) ?? 0) + 1);
    });
    return [...counts.entries()].sort((a, b) => a[0].localeCompare(b[0], 'fr')).map(([city, count]) => ({ city, count }));
});
const partnersMatchingSearch = computed(() => {
    const q = partnerSearch.value.trim().toLowerCase();
    return servicePartners.value.filter((partner) => (!partnerCity.value || partner.city === partnerCity.value)
        && (!q || `${partner.name} ${partner.city ?? ''} ${partner.address ?? ''}`.toLowerCase().includes(q)));
});
const filteredPartners = computed(() => partnersMatchingSearch.value
    .filter((partner) => partnerType.value === 'Tous' || partner.type === partnerType.value));
const visiblePartners = computed(() => filteredPartners.value.slice(0, partnerLimit.value));
function partnerTypeCount(key) {
    return key === 'Tous'
        ? partnersMatchingSearch.value.length
        : partnersMatchingSearch.value.filter((partner) => partner.type === key).length;
}
function partnerIcon(type) {
    return type === 'Centre de santé' ? 'local_hospital' : 'medication';
}
watch([partnerSearch, partnerCity, partnerType], () => {
    partnerLimit.value = PARTNER_PAGE;
});
const resourceCategories = computed(() => ['Toutes', ...(props.systemData.categories ?? [])]);
const resourceActiveCategory = ref('Toutes');
const resourceToast = ref('');
const filteredResources = computed(() => resourceActiveCategory.value === 'Toutes'
    ? (props.systemData.resources ?? [])
    : (props.systemData.resources ?? []).filter(resource => resource.category === resourceActiveCategory.value));
const newsActiveCategory = ref('Tout');
const newsSearchQuery = ref(props.systemData.initialSearch ?? '');
const newsFilters = computed(() => ['Tout', ...(props.systemData.categories ?? [])]);
const filteredNews = computed(() => {
    const q = newsSearchQuery.value.trim().toLowerCase();
    return (props.systemData.articles ?? []).filter((article) => {
        const matchesCategory = newsActiveCategory.value === 'Tout' || article.category === newsActiveCategory.value;
        const matchesSearch = !q || article.title.toLowerCase().includes(q) || (article.excerpt || '').toLowerCase().includes(q);
        return matchesCategory && matchesSearch;
    });
});
const featuredNews = computed(() => filteredNews.value.find(article => article.featured) ?? filteredNews.value[0] ?? null);
const listedNews = computed(() => filteredNews.value.filter(article => !featuredNews.value || article.id !== featuredNews.value.id));
// Actualités : 9 par page (3 rangées de 3) ; ressources : 12 par page. Filtre ou recherche → page 1.
const newsTop = ref(null);
const {
    pagination: newsPagination,
    paged: pagedNews,
    goToPage: setNewsPage,
} = useClientPagination(() => listedNews.value, { perPageOptions: [9], anchor: newsTop, resetOn: [newsActiveCategory, newsSearchQuery] });
const resourcesTop = ref(null);
const {
    pagination: resourcesPagination,
    paged: pagedResources,
    goToPage: setResourcesPage,
} = useClientPagination(() => filteredResources.value, { perPageOptions: [12], anchor: resourcesTop, resetOn: resourceActiveCategory });
const contactSearchQuery = ref('');
const contactActiveFilter = ref('all');
const contactActiveId = ref(null);
const contactSent = ref(false);
const contactError = ref('');
const newsletterSent = ref(false);
const contactApiKey = computed(() => page.props.app?.googleMapsApiKey ?? '');
const contactFilters = [
    { key: 'all', label: 'Tous', match: () => true },
    { key: 'cama', label: 'Antennes CAMA', match: type => type === 'siege' || type === 'antenne' },
    { key: 'centre', label: 'Centres de santé', match: type => type === 'centre' },
    { key: 'partenaire', label: 'Partenaires', match: type => type === 'partenaire' },
];
const filteredContactLocations = computed(() => {
    const filter = contactFilters.find(item => item.key === contactActiveFilter.value) ?? contactFilters[0];
    const q = contactSearchQuery.value.trim().toLowerCase();
    return (props.systemData.locations ?? []).filter((location) => {
        const matchesFilter = filter.match(location.type);
        const matchesSearch = !q || `${location.name} ${location.city ?? ''} ${location.address}`.toLowerCase().includes(q);
        return matchesFilter && matchesSearch;
    });
});
const activeContactLocation = computed(() => {
    const locations = props.systemData.locations ?? [];
    return locations.find(location => location.id === contactActiveId.value) ?? locations[0] ?? null;
});
const isLocated = location => location?.lat != null && location?.lon != null;
// Centre approximatif de chaque ville (moyenne des lieux localisés) : sert à recentrer la carte
// sur la ville d'une fiche sans coordonnées, qui reste listée et sélectionnable comme les autres.
const contactCityCenters = computed(() => {
    const sums = new Map();
    (props.systemData.locations ?? []).filter(isLocated).forEach((location) => {
        const entry = sums.get(location.city) ?? { lat: 0, lon: 0, n: 0 };
        entry.lat += Number(location.lat);
        entry.lon += Number(location.lon);
        entry.n += 1;
        sums.set(location.city, entry);
    });
    return new Map([...sums].map(([city, s]) => [city, { lat: s.lat / s.n, lon: s.lon / s.n }]));
});
const contactMapPoints = computed(() => filteredContactLocations.value.filter(isLocated).map(location => ({
    id: location.id,
    name: location.name,
    type: location.partnerType || (location.type === 'centre' ? 'Centre de santé' : location.type === 'partenaire' ? 'Partenaire' : location.type === 'siege' ? 'Siège CAMA' : 'Antenne CAMA'),
    city: location.city,
    description: location.address,
    imageSrc: location.imageSrc,
    lat: Number(location.lat),
    lon: Number(location.lon),
    mapsUrl: mapsUrl(location),
})));
const contactMapFocus = computed(() => {
    const location = activeContactLocation.value;
    if (!location) return null;
    if (isLocated(location)) return { lat: Number(location.lat), lon: Number(location.lon), zoom: 14, key: location.id };
    const city = contactCityCenters.value.get(location.city);
    return city ? { lat: city.lat, lon: city.lon, zoom: 13, key: location.id } : null;
});

const contactForm = useForm({
    full_name: '',
    email: '',
    phone: '',
    subject: 'Nouvel enrôlement',
    message: '',
    consent: false,
    source_page: 'contact',
});

const newsletterForm = useForm({
    email: '',
    name: '',
});


onMounted(() => {
    if (homeSlides.value.length > 1) {
        slideTimer = window.setInterval(() => {
            activeSlide.value = (activeSlide.value + 1) % homeSlides.value.length;
        }, 7000);
    }

    nextTick(setupReveal);
});

// Dans l'éditeur, les vraies sections arrivent après le premier rendu : on relance
// l'animation d'apparition pour qu'elle soit identique à celle du site publié.
watch(() => pbPreview.revision, (revision) => {
    if (revision === 1) nextTick(setupReveal);
});

let revealObserver = null;

function setupReveal() {
    revealObserver?.disconnect();
    revealObserver = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('opacity-100', 'translate-y-0');
                entry.target.classList.remove('opacity-0', 'translate-y-10');
            }
        });
    }, { threshold: 0.1, rootMargin: '0px 0px -50px 0px' });

    document.querySelectorAll('main section > div').forEach((el) => {
        el.classList.remove('opacity-100', 'translate-y-0');
        el.classList.add('transition-all', 'duration-1000', 'ease-out', 'opacity-0', 'translate-y-10');
        revealObserver.observe(el);
    });
}

onBeforeUnmount(() => {
    if (slideTimer) window.clearInterval(slideTimer);
    revealObserver?.disconnect();
});

const palette = {
    primary: 'rgb(var(--c-primary))',
    secondary: 'rgb(var(--c-secondary))',
    tertiary: 'rgb(var(--c-tertiary))',
    'surface-container-low': 'rgb(var(--c-surface-container-low))',
    'surface-container': 'rgb(var(--c-surface-container))',
    'surface-container-high': 'rgb(var(--c-surface-container-high))',
    'outline-variant': 'rgb(var(--c-outline-variant))',
    'on-surface': 'rgb(var(--c-on-surface))',
    'on-surface-variant': 'rgb(var(--c-on-surface-variant))',
    white: '#ffffff',
    transparent: 'transparent',
};

function colorValue(token) {
    return palette[token] ?? token ?? 'transparent';
}

function styleBox(style = {}) {
    const padding = style.padding ?? {};
    const margin = style.margin ?? {};
    const styles = {};
    if (style.hidden) {
        // Dans le canevas de l'éditeur, un élément masqué reste visible (estompé) pour rester sélectionnable.
        if (pbEditing.value) {
            styles.opacity = '0.45';
            styles.outline = '1px dashed rgb(var(--c-tertiary))';
        } else {
            styles.display = 'none';
        }
    }
    if (style.bgColor && style.bgColor !== 'transparent') styles.backgroundColor = colorValue(style.bgColor);
    if (style.bgImage) {
        styles.backgroundImage = `url('${style.bgImage}')`;
        styles.backgroundSize = 'cover';
        styles.backgroundPosition = 'center';
    }
    if (style.textColor) styles.color = colorValue(style.textColor);
    if (style.radius) styles.borderRadius = `${style.radius}px`;
    if (style.minHeight) styles.minHeight = `${style.minHeight}px`;
    if (padding.t || padding.r || padding.b || padding.l) styles.padding = `${padding.t || 0}px ${padding.r || 0}px ${padding.b || 0}px ${padding.l || 0}px`;
    if (margin.t || margin.r || margin.b || margin.l) styles.margin = `${margin.t || 0}px ${margin.r || 0}px ${margin.b || 0}px ${margin.l || 0}px`;
    return styles;
}

function cssLength(value, unit = 'px', fallback = '') {
    if (value === '' || value === null || value === undefined) return fallback;
    return `${value}${unit || 'px'}`;
}

function spacingCss(value = {}) {
    return `${value.t || 0}px ${value.r || 0}px ${value.b || 0}px ${value.l || 0}px`;
}

function elementorHeadingStyle(widget) {
    const style = widget.style ?? {};
    return {
        '--eh-color': style.titleColor || style.textColor || '#0A083B',
        '--eh-font-family': `'${style.fontFamily || 'Poppins'}', sans-serif`,
        '--eh-font-size': cssLength(style.fontSize, 'px', '49px'),
        '--eh-font-size-tablet': cssLength(style.fontSizeTablet, 'px', cssLength(style.fontSize, 'px', '49px')),
        '--eh-font-size-mobile': cssLength(style.fontSizeMobile, 'px', cssLength(style.fontSizeTablet, 'px', '45px')),
        '--eh-font-weight': style.fontWeight || '700',
        '--eh-line-height': cssLength(style.lineHeight, 'em', '1.1em'),
        '--eh-letter-spacing': cssLength(style.letterSpacing, 'px', '-0.5px'),
        '--eh-align': style.align || 'inherit',
        '--eh-align-tablet': style.alignTablet || style.align || 'inherit',
        '--eh-align-mobile': style.alignMobile || style.alignTablet || style.align || 'center',
        '--eh-padding': spacingCss(style.padding),
        '--eh-padding-tablet': spacingCss(style.paddingTablet ?? style.padding),
        '--eh-padding-mobile': spacingCss(style.paddingMobile ?? style.paddingTablet ?? style.padding),
        '--eh-margin': spacingCss(style.margin),
        '--eh-margin-tablet': spacingCss(style.marginTablet ?? style.margin),
        '--eh-margin-mobile': spacingCss(style.marginMobile ?? style.marginTablet ?? style.margin),
        '--eh-text-transform': style.textTransform || 'none',
        '--eh-font-style': style.fontStyle || 'normal',
        '--eh-text-decoration': style.textDecoration || 'none',
        '--eh-blend-mode': style.blendMode || 'normal',
        '--eh-text-stroke': style.textStrokeWidth ? `${style.textStrokeWidth}px ${style.textStrokeColor || '#000'}` : '0 transparent',
        '--eh-text-shadow': style.textShadowType ? `${style.textShadow?.horizontal || 0}px ${style.textShadow?.vertical || 0}px ${style.textShadow?.blur || 10}px ${style.textShadow?.color || 'rgba(0,0,0,0.3)'}` : 'none',
    };
}

function resolvePostsArticles(widget) {
    const pool = props.systemData.articles ?? props.systemData.latestArticles ?? [];
    const count = Number(widget.content?.count ?? 3);
    return pool.slice(0, Math.max(1, count));
}

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
}

function escapeAttr(value) {
    return escapeHtml(value).replace(/'/g, '&#39;');
}

function renderElementorWidgetHtml(widget) {
    const content = widget.content ?? {};
    const style = widget.style ?? {};
    const inlineStyle = Object.entries(styleBox(style)).map(([key, value]) => `${key.replace(/[A-Z]/g, (m) => `-${m.toLowerCase()}`)}:${value}`).join(';');
    const align = style.align === 'center' ? 'text-center' : style.align === 'right' ? 'text-right' : 'text-left';

    if (widget.type === 'elementor_heading') {
        const tag = content.tag || 'h1';
        const styles = Object.entries(elementorHeadingStyle(widget)).map(([key, value]) => `${key}:${value}`).join(';');
        const title = `<${tag} class="elementor-heading-title" style="${styles}">${content.title || ''}</${tag}>`;
        return content.link?.url ? `<a href="${escapeAttr(content.link.url)}"${content.link?.is_external ? ' target="_blank"' : ''}${content.link?.nofollow ? ' rel="nofollow"' : ''}>${title}</a>` : title;
    }

    if (widget.type === 'elementor_text_editor') {
        return `<div class="${align}" style="${inlineStyle};color:${style.textColor || 'inherit'};font-family:${style.fontFamily || 'inherit'};font-size:${cssLength(style.fontSize, 'px', 'inherit')};font-weight:${style.fontWeight || 'inherit'};line-height:${style.lineHeight || 'inherit'}">${content.html || ''}</div>`;
    }

    if (widget.type === 'elementor_button') {
        const icon = content.icon ? `<span class="material-symbols-outlined text-[18px]">${escapeHtml(content.icon)}</span>` : '';
        const label = `<span>${escapeHtml(content.text || '')}</span>`;
        return `<div class="${align}" style="${inlineStyle}"><a class="inline-flex items-center gap-2" href="${escapeAttr(content.link?.url || '#')}" style="background:${style.bgColor || 'rgb(var(--c-primary))'};color:${style.textColor || '#fff'};border-radius:${style.borderRadius || 0}px;padding:${spacingCss(style.padding)};font-family:${style.fontFamily || 'Inter'};font-size:${cssLength(style.fontSize, 'px', '14px')};font-weight:${style.fontWeight || '700'}">${content.iconPosition === 'after' ? label + icon : icon + label}</a></div>`;
    }

    if (widget.type === 'elementor_image') {
        const image = `<img src="${escapeAttr(content.src || '')}" alt="${escapeAttr(content.alt || '')}" style="width:${style.width || 100}%;border-radius:${style.borderRadius || 0}px;display:inline-block"/>`;
        return `<figure class="${align}" style="${inlineStyle}">${content.link?.url ? `<a href="${escapeAttr(content.link.url)}">${image}</a>` : image}${content.caption ? `<figcaption class="text-sm text-on-surface-variant mt-2">${escapeHtml(content.caption)}</figcaption>` : ''}</figure>`;
    }

    if (widget.type === 'elementor_video') {
        return `<div style="${inlineStyle};border-radius:${style.borderRadius || 0}px;overflow:hidden"><div class="aspect-video bg-on-background text-white flex items-center justify-center"><span class="material-symbols-outlined text-5xl">play_circle</span><span class="ml-2 text-sm">${escapeHtml(content.url || 'Video')}</span></div></div>`;
    }

    if (widget.type === 'elementor_icon') {
        return `<div class="${align}" style="${inlineStyle}"><span class="material-symbols-outlined" style="font-size:${style.size || 48}px;color:${style.textColor || 'rgb(var(--c-primary))'}">${escapeHtml(content.icon || 'star')}</span></div>`;
    }

    if (widget.type === 'elementor_icon_box') {
        return `<div class="${align}" style="${inlineStyle};color:${style.textColor || 'inherit'}"><span class="material-symbols-outlined block mb-3" style="font-size:42px;color:${style.iconColor || 'rgb(var(--c-primary))'}">${escapeHtml(content.icon || 'star')}</span><h3 class="font-bold text-xl mb-2">${escapeHtml(content.title || '')}</h3><p class="text-on-surface-variant">${escapeHtml(content.text || '')}</p></div>`;
    }

    if (widget.type === 'elementor_image_box') {
        return `<div class="${align}" style="${inlineStyle}"><img src="${escapeAttr(content.src || '')}" alt="" style="width:${style.imageWidth || 100}%;border-radius:${style.borderRadius || 0}px;display:inline-block" /><h3 class="font-bold text-xl mt-4 mb-2">${escapeHtml(content.title || '')}</h3><p class="text-on-surface-variant">${escapeHtml(content.text || '')}</p></div>`;
    }

    if (widget.type === 'elementor_container') {
        return `<div style="${inlineStyle};display:flex;flex-direction:${content.direction || 'column'};gap:${content.gap || 16}px;border:1px dashed rgb(var(--c-outline-variant));border-radius:${style.borderRadius || 0}px"><span class="text-xs text-on-surface-variant">Container Elementor</span></div>`;
    }

    if (widget.type === 'elementor_form') {
        return `<form class="bg-white border border-outline-variant rounded-xl p-5 space-y-3" style="${inlineStyle}"><h3 class="font-bold">${escapeHtml(content.title || '')}</h3>${(content.fields || []).map((field) => `<label class="block text-sm font-semibold">${escapeHtml(field.label || '')}<input class="mt-1 w-full rounded-lg border border-outline-variant px-3 py-2" type="${escapeAttr(field.type || 'text')}"/></label>`).join('')}<button class="bg-primary text-on-primary rounded-lg px-5 py-2 font-bold" type="button">${escapeHtml(content.button || 'Envoyer')}</button></form>`;
    }

    if (widget.type === 'elementor_menu') {
        return `<nav class="flex ${content.layout === 'vertical' ? 'flex-col' : 'flex-row flex-wrap'}" style="${inlineStyle};justify-content:${style.align || 'center'};gap:${style.gap || 24}px;color:${style.textColor || 'rgb(var(--c-on-surface))'}"><span>Accueil</span><span>Services</span><span>Actualités</span><span>Contact</span></nav>`;
    }

    return '';
}

function sectionStyle(section) {
    return styleBox(section.settings);
}

function columnStyle(column) {
    const width = Number(column.width ?? 100);
    return {
        '--column-width': `${width}%`,
        // Sert à retrancher la gouttière (cf. .cms-page-column) : sans cela, deux
        // colonnes à 50 % + l'espacement dépassent 100 % et passent l'une sous l'autre.
        '--column-ratio': String(width / 100),
        ...styleBox(column.settings),
    };
}

function widgetClasses(widget) {
    const advanced = widget.advanced ?? {};
    return [
        // Les widgets « Elementor » rangent leur animation dans l'onglet Avancé.
        animationClass(widget.style) || animationClass(advanced),
        advanced.hideDesktop ? 'cms-hide-desktop' : '',
        advanced.hideTablet ? 'cms-hide-tablet' : '',
        advanced.hideMobile ? 'cms-hide-mobile' : '',
    ];
}

function alignClass(style = {}) {
    if (style.align === 'center') return 'text-center';
    if (style.align === 'right') return 'text-right';
    return 'text-left';
}

function animationClass(style = {}) {
    return style.animation && style.animation !== 'none' ? `cms-anim-${style.animation}` : '';
}

// Nombre de colonnes appliqué à partir de la tablette (cf. .cms-grid) : un style
// « grid-template-columns » en ligne imposait 3-4 colonnes même sur téléphone.
function gridColumns(count) {
    return { '--cms-grid-cols': String(Math.min(Number(count) || 3, 4)) };
}

function resourceIcon(format) {
    return {
        PDF: 'picture_as_pdf',
        DOC: 'description',
        DOCX: 'description',
        XLS: 'table',
        XLSX: 'table',
        IMG: 'image',
        PNG: 'image',
        JPG: 'image',
    }[String(format || '').toUpperCase()] || 'description';
}

function showResourceToast(event, resource) {
    if (resource.url && resource.url !== '#') return;
    event.preventDefault();
    resourceToast.value = 'Document de démonstration — le fichier sera disponible en production.';
    window.setTimeout(() => {
        resourceToast.value = '';
    }, 3200);
}

function selectNewsCategory(category) {
    newsActiveCategory.value = category;
}

function contactFilterCount(filter) {
    return (props.systemData.locations ?? []).filter(location => filter.match(location.type)).length;
}

function contactIcon(type) {
    return {
        siege: 'account_balance',
        antenne: 'place',
        centre: 'local_hospital',
        partenaire: 'handshake',
    }[type] || 'place';
}

function contactStyle(type) {
    return {
        siege: 'bg-primary/10 text-primary',
        antenne: 'bg-surface-container-low text-on-surface-variant',
        centre: 'bg-secondary/10 text-secondary',
        partenaire: 'bg-tertiary/10 text-tertiary',
    }[type] || 'bg-surface-container-low text-on-surface-variant';
}

function mapsUrl(location) {
    if (location?.mapsUrl) return location.mapsUrl;
    const query = isLocated(location) ? `${location.lat},${location.lon}` : `${location?.name}, ${location?.city ?? ''}, Burkina Faso`;
    return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(query)}`;
}

function submitContact() {
    contactError.value = '';
    contactForm.post(route('public.contact.submit'), {
        preserveScroll: true,
        onSuccess: () => {
            contactSent.value = true;
            contactForm.reset();
            contactForm.subject = 'Nouvel enrôlement';
            contactForm.source_page = 'contact';
            window.setTimeout(() => {
                contactSent.value = false;
            }, 3000);
        },
        onError: () => {
            contactError.value = 'Veuillez corriger les champs obligatoires.';
        },
    });
}

function submitNewsletter() {
    newsletterForm.post(route('public.newsletter.subscribe'), {
        preserveScroll: true,
        onSuccess: () => {
            newsletterSent.value = true;
            newsletterForm.reset();
            window.setTimeout(() => {
                newsletterSent.value = false;
            }, 3000);
        },
    });
}

function restartSlideTimer() {
    if (slideTimer) window.clearInterval(slideTimer);
    if (homeSlides.value.length > 1) {
        slideTimer = window.setInterval(() => {
            activeSlide.value = (activeSlide.value + 1) % homeSlides.value.length;
        }, 7000);
    }
}

function setSlide(index) {
    activeSlide.value = (index + homeSlides.value.length) % homeSlides.value.length;
    restartSlideTimer();
}

function nextSlide() {
    setSlide(activeSlide.value + 1);
}

function prevSlide() {
    setSlide(activeSlide.value - 1);
}

function systemWidget(section) {
    const columns = section.columns ?? [];
    if (columns.length !== 1) return null;

    const widgets = columns[0].widgets ?? [];
    if (widgets.length !== 1) return null;

    const types = [
        'home_hero',
        'home_pillars',
        'home_key_figures',
        'home_latest_articles',
        'services_hero',
        'services_stats',
        'services_coverage',
        'services_steps',
        'services_partners',
        'services_faq',
        'services_cta',
        'about_hero',
        'about_director',
        'about_timeline',
        'about_missions',
        'about_cta',
        'resources_hero',
        'resources_listing',
        'news_hero',
        'news_filters',
        'news_listing',
        'news_newsletter',
        'contact_hero',
        'contact_form',
        'contact_map',
        'legal_page',
    ];

    return types.includes(widgets[0].type) ? widgets[0] : null;
}

// Mentions légales : textes du page builder, bloc « Coordonnées » issu du module « Pied de page ».
const siteFooter = computed(() => page.props.publicSite?.footer ?? {});

// Frise « Notre Évolution » : autant de colonnes que de jalons (3 max), trait de
// liaison d'un centre de jalon à l'autre. Classes écrites en entier pour Tailwind.
const TIMELINE_GRID = { 1: 'md:grid-cols-1', 2: 'md:grid-cols-2', 3: 'md:grid-cols-3' };

function timelineColumns(section) {
    const count = systemWidget(section)?.content?.items?.length ?? 0;
    return Math.min(Math.max(count, 1), 3);
}

function timelineLineStyle(section) {
    const inset = `${(100 / (2 * timelineColumns(section))).toFixed(2)}%`;
    return { left: inset, right: inset };
}

function homePillars(widget) {
    return widget?.content?.items?.length ? widget.content.items : (props.systemData.pillars ?? []);
}

function figureClass(index) {
    if (index === 0) return 'bg-primary text-on-primary';
    if (index === 1) return 'bg-secondary text-on-secondary';
    if (index === 2) return 'bg-tertiary-container text-on-tertiary-container';
    return 'bg-on-background text-surface';
}

function serviceTone(tone) {
    return {
        primary: 'bg-primary text-on-primary',
        secondary: 'bg-secondary text-on-secondary',
        tertiary: 'bg-tertiary-container text-on-tertiary-container',
        dark: 'bg-on-background text-surface',
    }[tone] || 'bg-primary text-on-primary';
}

function serviceSoftTone(tone) {
    return {
        primary: 'bg-primary/10 text-primary',
        secondary: 'bg-secondary/10 text-secondary',
        tertiary: 'bg-tertiary/10 text-tertiary',
        dark: 'bg-on-background/10 text-on-background',
    }[tone] || 'bg-primary/10 text-primary';
}

function serviceTextTone(tone) {
    return {
        primary: 'text-primary',
        secondary: 'text-secondary',
        tertiary: 'text-tertiary',
        dark: 'text-on-background',
    }[tone] || 'text-primary';
}

function scrollServicesCoverage(event, direction) {
    const section = event.currentTarget.closest('section');
    const track = section?.querySelector('.services-scroll');
    const card = track?.querySelector('.snap-start');
    const amount = card ? card.offsetWidth + 24 : 320;
    track?.scrollBy({ left: direction * amount, behavior: 'smooth' });
}

function faqMeta(category) {
    return {
        Prestations: { icon: 'receipt_long', class: 'text-primary bg-primary/10' },
        Remboursements: { icon: 'account_balance_wallet', class: 'text-on-background bg-surface-container-high' },
        Enrôlement: { icon: 'diversity_3', class: 'text-tertiary bg-tertiary/10' },
        'Compte assuré': { icon: 'manage_accounts', class: 'text-secondary bg-secondary/10' },
    }[category] || { icon: 'help', class: 'text-primary bg-primary/10' };
}
</script>

<template>
    <template v-for="(section, sectionIndex) in renderedSections" :key="section.uid ?? sectionIndex">
        <section v-if="systemWidget(section)?.type === 'home_hero'" :data-pb-section="pbAttr(section)" :data-pb-widget="pbAttr(systemWidget(section))" id="hero-carousel" class="relative h-[480px] md:h-[560px] overflow-hidden bg-on-background" :class="animationClass(section.settings)" :style="sectionStyle(section)">
            <article
                v-for="(slide, index) in homeSlides"
                :key="slide.id ?? index"
                class="hero-slide absolute inset-0"
                :class="{ 'is-active opacity-100 z-10': index === activeSlide, 'opacity-0 z-0': index !== activeSlide }"
            >
                <div class="hero-slide-bg absolute inset-0 bg-cover bg-center" :style="{ backgroundImage: `url('${slide.image_src || fallbackSlide.image_src}')` }" />
                <div class="absolute inset-0 bg-gradient-to-r from-black/80 via-black/45 to-black/20" />
                <div class="relative z-10 h-full max-w-container-max-width mx-auto px-4 md:px-margin-desktop flex items-center">
                    <div class="max-w-2xl text-white space-y-6">
                        <span class="inline-flex items-center gap-2 bg-white/10 backdrop-blur-md border border-white/20 px-4 py-1.5 rounded-full font-label-md text-label-md uppercase tracking-wider">
                            <span class="material-symbols-outlined text-[18px]">{{ systemWidget(section).content?.badgeIcon || 'health_and_safety' }}</span>
                            {{ systemWidget(section).content?.badgeText || 'CAMA Burkina Faso' }}
                        </span>
                        <h1 class="font-display-lg text-4xl md:text-display-lg leading-tight">{{ slide.title }}</h1>
                        <p class="font-body-lg text-body-lg text-white/90 max-w-xl">{{ slide.subtitle }}</p>
                        <div class="flex flex-wrap gap-3">
                            <Link :href="slide.link_url || systemWidget(section).content?.primaryHref || fallbackSlide.link_url" class="inline-flex items-center gap-2 bg-primary-container text-on-primary px-6 py-3.5 rounded-lg font-bold hover:bg-primary transition-all shadow-lg">
                                {{ slide.link_label || systemWidget(section).content?.primaryLabel || fallbackSlide.link_label }}
                                <span class="material-symbols-outlined">arrow_forward</span>
                            </Link>
                            <Link v-if="systemWidget(section).content?.secondaryLabel !== ''" :href="systemWidget(section).content?.secondaryHref || '/espace-assure/connexion'" class="inline-flex items-center gap-2 bg-white/10 backdrop-blur-md border border-white/30 text-white px-6 py-3.5 rounded-lg font-bold hover:bg-white/20 transition-all">
                                {{ systemWidget(section).content?.secondaryLabel || 'Espace assuré' }}
                            </Link>
                        </div>
                    </div>
                </div>
            </article>

            <template v-if="homeSlides.length > 1">
                <button
                    type="button"
                    class="absolute left-3 md:left-6 top-1/2 -translate-y-1/2 z-20 w-11 h-11 rounded-full flex items-center justify-center bg-white/10 backdrop-blur-md border border-white/30 text-white hover:bg-white/20 transition-all active:scale-95"
                    aria-label="Slide précédent"
                    @click="prevSlide"
                >
                    <span class="material-symbols-outlined">chevron_left</span>
                </button>
                <button
                    type="button"
                    class="absolute right-3 md:right-6 top-1/2 -translate-y-1/2 z-20 w-11 h-11 rounded-full flex items-center justify-center bg-white/10 backdrop-blur-md border border-white/30 text-white hover:bg-white/20 transition-all active:scale-95"
                    aria-label="Slide suivant"
                    @click="nextSlide"
                >
                    <span class="material-symbols-outlined">chevron_right</span>
                </button>

                <div class="absolute bottom-6 left-0 right-0 z-20">
                    <div class="max-w-container-max-width mx-auto px-4 md:px-margin-desktop flex items-center gap-3">
                        <div class="flex gap-2">
                            <button
                                v-for="(slide, index) in homeSlides"
                                :key="`dot-${slide.id ?? index}`"
                                type="button"
                                class="hero-dot h-1.5 rounded-full overflow-hidden bg-white/30 transition-all"
                                :class="index === activeSlide ? 'w-16' : 'w-8'"
                                :aria-label="`Afficher le slide ${index + 1}`"
                                @click="setSlide(index)"
                            >
                                <span class="hero-progress-fill block h-full bg-white" :class="{ 'is-running': index === activeSlide }" />
                            </button>
                        </div>
                        <span class="text-white/80 text-xs font-semibold tabular-nums bg-white/10 backdrop-blur-md border border-white/20 px-2.5 py-1 rounded-full">
                            {{ activeSlide + 1 }} / {{ homeSlides.length }}
                        </span>
                    </div>
                </div>
            </template>
        </section>

        <section v-else-if="systemWidget(section)?.type === 'home_pillars'" :data-pb-section="pbAttr(section)" :data-pb-widget="pbAttr(systemWidget(section))" class="py-stack-lg relative overflow-hidden bg-surface-container-lowest" :class="animationClass(section.settings)" :style="sectionStyle(section)">
            <div class="shield-pattern absolute inset-0" />
            <div class="max-w-container-max-width mx-auto px-4 md:px-margin-desktop relative z-10">
                <div class="text-center mb-16 space-y-2">
                    <h2 class="text-primary font-headline-lg text-headline-lg">{{ systemWidget(section).content?.title || 'Nos Piliers' }}</h2>
                    <div class="h-1 w-20 bg-tertiary-container mx-auto rounded-full" />
                </div>
                <div class="grid md:grid-cols-3 gap-8">
                    <div
                        v-for="(pillar, index) in homePillars(systemWidget(section))"
                        :key="pillar.title"
                        class="group bg-white p-10 rounded-xl border border-outline-variant shadow-sm hover:shadow-lg hover:-translate-y-1 transition-all duration-300"
                        :class="[
                            index === 0 ? 'hover:bg-primary hover:border-primary' : '',
                            index === 1 ? 'hover:bg-secondary hover:border-secondary' : '',
                            index === 2 ? 'hover:bg-tertiary hover:border-tertiary' : '',
                        ]"
                    >
                        <div
                            class="w-16 h-16 text-white group-hover:bg-white rounded-xl flex items-center justify-center mb-6 transition-colors duration-300"
                            :class="[
                                index === 0 ? 'bg-primary group-hover:text-primary' : '',
                                index === 1 ? 'bg-secondary group-hover:text-secondary' : '',
                                index === 2 ? 'bg-tertiary group-hover:text-tertiary' : '',
                            ]"
                        >
                            <span class="material-symbols-outlined text-[32px]">{{ pillar.icon }}</span>
                        </div>
                        <h3 class="font-headline-md text-headline-md mb-4 group-hover:text-white transition-colors duration-300">{{ pillar.title }}</h3>
                        <p class="text-on-surface-variant font-body-md leading-relaxed group-hover:text-white/90 transition-colors duration-300">{{ pillar.text }}</p>
                    </div>
                </div>
            </div>
        </section>

        <section v-else-if="systemWidget(section)?.type === 'home_key_figures'" :data-pb-section="pbAttr(section)" :data-pb-widget="pbAttr(systemWidget(section))" id="key-figures" class="py-stack-lg bg-surface-container" :class="animationClass(section.settings)" :style="sectionStyle(section)">
            <div class="max-w-container-max-width mx-auto px-4 md:px-margin-desktop">
                <div class="text-center mb-12 space-y-2">
                    <span class="text-primary font-label-md uppercase tracking-widest">{{ systemWidget(section).content?.eyebrow || 'En Chiffres' }}</span>
                    <h2 class="font-headline-lg text-headline-lg">{{ systemWidget(section).content?.title || "La CAMA aujourd'hui" }}</h2>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <article
                        v-for="(figure, index) in homeKeyFigures"
                        :key="figure.id ?? index"
                        class="group rounded-xl p-6 flex flex-col gap-1 shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all duration-300"
                        :class="figureClass(index)"
                    >
                        <span class="material-symbols-outlined text-3xl opacity-90 group-hover:scale-110 transition-transform duration-300">{{ figure.icon }}</span>
                        <span class="text-3xl font-headline-lg font-bold">{{ figure.value }}{{ figure.suffix }}</span>
                        <span class="text-caption uppercase tracking-wide opacity-90">{{ figure.label }}</span>
                    </article>
                </div>
            </div>
        </section>

        <section v-else-if="systemWidget(section)?.type === 'home_latest_articles' && homeFeaturedArticle" :data-pb-section="pbAttr(section)" :data-pb-widget="pbAttr(systemWidget(section))" class="py-stack-lg bg-surface-container-low" :class="animationClass(section.settings)" :style="sectionStyle(section)">
            <div class="max-w-container-max-width mx-auto px-4 md:px-margin-desktop">
                <div class="flex justify-between items-end mb-12 gap-4">
                    <div class="space-y-2">
                        <span class="text-primary font-label-md uppercase tracking-widest">{{ systemWidget(section).content?.eyebrow || 'Informations' }}</span>
                        <h2 class="font-headline-lg text-headline-lg">{{ systemWidget(section).content?.title || 'Dernières Actualités' }}</h2>
                    </div>
                    <Link class="text-primary font-bold flex items-center gap-2 hover:underline group" :href="systemWidget(section).content?.linkHref || '/actualites'">
                        {{ systemWidget(section).content?.linkLabel || 'Voir tout le flux' }} <span class="material-symbols-outlined group-hover:translate-x-1 transition-transform">arrow_forward</span>
                    </Link>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-stack-lg items-stretch">
                    <article class="lg:col-span-2 group relative rounded-xl overflow-hidden border border-outline-variant shadow-sm hover:shadow-2xl transition-all duration-300">
                        <div class="relative h-72 lg:h-full min-h-[420px] overflow-hidden">
                            <img :alt="homeFeaturedArticle.title" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700" :src="homeFeaturedArticle.image_src || '/images/CAMA_8.jfif'" />
                            <div class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/30 to-transparent" />
                            <span class="absolute top-5 left-5 bg-primary text-on-primary px-3 py-1 rounded text-xs font-bold uppercase tracking-wider shadow-sm">À la une</span>
                            <div class="absolute bottom-0 left-0 right-0 p-6 lg:p-8 text-white">
                                <span class="text-sm font-label-md text-white/80 mb-2 block">{{ homeFeaturedArticle.published_at }}</span>
                                <h3 class="font-headline-md text-headline-md mb-3 max-w-xl">{{ homeFeaturedArticle.title }}</h3>
                                <p class="text-white/85 font-body-md mb-5 max-w-xl line-clamp-2 hidden md:block">{{ homeFeaturedArticle.excerpt }}</p>
                                <Link class="inline-flex items-center gap-2 font-bold text-white group/btn" :href="homeFeaturedArticle.href">
                                    Lire la suite <span class="material-symbols-outlined text-[18px] group-hover/btn:translate-x-1 transition-transform">arrow_forward</span>
                                </Link>
                            </div>
                        </div>
                    </article>
                    <div class="flex flex-col gap-stack-lg">
                        <article v-for="article in homeSideArticles" :key="article.id" class="group flex-1 flex gap-4 bg-white rounded-xl border border-outline-variant p-4 hover:shadow-lg hover:border-primary/30 transition-all duration-300">
                            <div class="w-24 h-24 shrink-0 rounded-lg overflow-hidden">
                                <img :alt="article.title" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500" :src="article.image_src || '/images/CAMA_6.jfif'" />
                            </div>
                            <div class="flex flex-col min-w-0">
                                <span class="text-xs font-label-md text-tertiary uppercase tracking-wide mb-1">{{ article.published_at }}</span>
                                <h3 class="font-title-lg text-title-lg leading-snug mb-2 line-clamp-2 group-hover:text-primary transition-colors">{{ article.title }}</h3>
                                <p class="text-on-surface-variant text-sm font-body-md mb-3 line-clamp-2">{{ article.excerpt }}</p>
                                <Link class="mt-auto text-primary text-sm font-bold flex items-center gap-1 group/btn" :href="article.href">
                                    Lire la suite <span class="material-symbols-outlined text-[16px] group-hover/btn:translate-x-1 transition-transform">chevron_right</span>
                                </Link>
                            </div>
                        </article>
                    </div>
                </div>
            </div>
        </section>

        <section v-else-if="systemWidget(section)?.type === 'services_hero'" :data-pb-section="pbAttr(section)" :data-pb-widget="pbAttr(systemWidget(section))" class="relative h-[420px] overflow-hidden flex items-center" :class="animationClass(section.settings)" :style="sectionStyle(section)">
            <div class="absolute inset-0 bg-cover bg-center bg-services-hero" />
            <div class="w-full px-6 md:px-16 relative z-10">
                <div class="text-white space-y-6 max-w-2xl mr-auto">
                    <span class="inline-block bg-black/40 backdrop-blur-md border border-white/25 text-white px-4 py-1.5 rounded-full font-label-md text-label-md uppercase tracking-wider shadow-sm">{{ systemWidget(section).content?.badge }}</span>
                    <h1 class="font-headline-lg text-headline-lg md:text-[40px] md:leading-[1.15] leading-tight">{{ systemWidget(section).content?.title }}</h1>
                    <p class="font-body-lg text-body-lg text-white/90 max-w-lg">{{ systemWidget(section).content?.text }}</p>
                    <div class="flex flex-wrap gap-4 pt-2">
                        <a class="bg-primary-container text-on-primary-container px-8 py-3.5 rounded-lg font-bold font-label-md text-label-md flex items-center gap-2 hover:bg-primary transition-all shadow-lg active:scale-95" :href="systemWidget(section).content?.primaryHref || '#coverage'">
                            {{ systemWidget(section).content?.primaryLabel || 'Voir le détail' }} <span class="material-symbols-outlined">arrow_downward</span>
                        </a>
                        <a class="bg-white/10 backdrop-blur-md border border-white/30 text-white px-8 py-3.5 rounded-lg font-bold font-label-md text-label-md hover:bg-white/20 transition-all active:scale-95" :href="systemWidget(section).content?.secondaryHref || '#faq'">
                            {{ systemWidget(section).content?.secondaryLabel || 'Foire aux questions' }}
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <section v-else-if="systemWidget(section)?.type === 'services_stats'" :data-pb-section="pbAttr(section)" :data-pb-widget="pbAttr(systemWidget(section))" class="bg-surface-container-lowest py-stack-lg border-b border-outline-variant" :class="animationClass(section.settings)" :style="sectionStyle(section)">
            <div class="px-4 md:px-margin-desktop max-w-container-max-width mx-auto grid grid-cols-2 md:grid-cols-4 gap-4">
                <div v-for="item in systemWidget(section).content?.items || []" :key="`${item.value}-${item.label}`" class="group rounded-xl p-6 flex flex-col gap-1 shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all duration-300" :class="serviceTone(item.tone)">
                    <span class="material-symbols-outlined text-3xl opacity-90 group-hover:scale-110 transition-transform duration-300">{{ item.icon }}</span>
                    <span class="text-3xl font-headline-lg font-bold">{{ item.value }}</span>
                    <span class="text-caption uppercase tracking-wide opacity-90">{{ item.label }}</span>
                </div>
            </div>
        </section>

        <section v-else-if="systemWidget(section)?.type === 'services_coverage'" :data-pb-section="pbAttr(section)" :data-pb-widget="pbAttr(systemWidget(section))" id="coverage" class="py-stack-lg bg-surface-container" :class="animationClass(section.settings)" :style="sectionStyle(section)">
            <div class="max-w-container-max-width mx-auto px-4 md:px-margin-desktop">
                <div class="flex items-end justify-between gap-6 mb-12">
                    <div>
                        <span class="text-primary font-label-md uppercase tracking-widest">{{ systemWidget(section).content?.eyebrow }}</span>
                        <h2 class="font-headline-md text-headline-md mt-2">{{ systemWidget(section).content?.title }}</h2>
                    </div>
                    <div class="hidden md:flex gap-2 shrink-0">
                        <button aria-label="Précédent" class="services-prev w-11 h-11 rounded-full border border-outline-variant bg-white hover:bg-surface-container-low flex items-center justify-center transition-colors" type="button" @click="scrollServicesCoverage($event, -1)">
                            <span class="material-symbols-outlined">chevron_left</span>
                        </button>
                        <button aria-label="Suivant" class="services-next w-11 h-11 rounded-full border border-outline-variant bg-white hover:bg-surface-container-low flex items-center justify-center transition-colors" type="button" @click="scrollServicesCoverage($event, 1)">
                            <span class="material-symbols-outlined">chevron_right</span>
                        </button>
                    </div>
                </div>
            </div>
            <div class="relative">
                <div class="services-scroll no-scrollbar flex gap-gutter overflow-x-auto snap-x snap-mandatory scroll-smooth px-4 md:px-margin-desktop pb-2">
                    <div v-for="item in systemWidget(section).content?.items || []" :key="item.title" class="snap-start shrink-0 w-[280px] sm:w-[320px] flex flex-col bg-white p-8 rounded-2xl border border-outline-variant shadow-sm hover:shadow-lg hover:-translate-y-1 transition-all duration-300">
                        <div class="w-14 h-14 rounded-xl flex items-center justify-center mb-6" :class="serviceSoftTone(item.tone)">
                            <span class="material-symbols-outlined text-3xl">{{ item.icon }}</span>
                        </div>
                        <h3 class="font-title-lg text-title-lg mb-3">{{ item.title }}</h3>
                        <p class="text-on-surface-variant text-body-md mb-6 flex-grow">{{ item.text }}</p>
                        <span class="inline-block w-fit font-bold px-3 py-1 rounded-full text-label-md" :class="serviceSoftTone(item.tone)">{{ item.rate }}</span>
                    </div>
                    <div class="shrink-0 w-px" />
                </div>
                <div class="hidden md:block absolute top-0 bottom-2 left-0 w-12 bg-gradient-to-r from-surface-container to-transparent pointer-events-none" />
                <div class="hidden md:block absolute top-0 bottom-2 right-0 w-12 bg-gradient-to-l from-surface-container to-transparent pointer-events-none" />
            </div>
        </section>

        <section v-else-if="systemWidget(section)?.type === 'services_steps'" :data-pb-section="pbAttr(section)" :data-pb-widget="pbAttr(systemWidget(section))" class="py-stack-lg bg-surface-container-low" :class="animationClass(section.settings)" :style="sectionStyle(section)">
            <div class="px-4 md:px-margin-desktop max-w-container-max-width mx-auto">
                <div class="text-center mb-12">
                    <span class="text-primary font-label-md uppercase tracking-widest">{{ systemWidget(section).content?.eyebrow }}</span>
                    <h2 class="font-headline-md text-headline-md mt-2">{{ systemWidget(section).content?.title }}</h2>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-gutter relative">
                    <div class="hidden md:block absolute top-8 left-[12.5%] right-[12.5%] h-0.5 bg-outline-variant" />
                    <div v-for="(item, index) in systemWidget(section).content?.items || []" :key="item.title" class="relative bg-white p-6 rounded-xl border border-outline-variant shadow-sm text-center">
                        <div class="w-12 h-12 mx-auto mb-4 rounded-full flex items-center justify-center font-bold text-lg relative z-10" :class="serviceTone(item.tone)">{{ index + 1 }}</div>
                        <span class="material-symbols-outlined text-3xl mb-2 block" :class="serviceTextTone(item.tone)">{{ item.icon }}</span>
                        <h4 class="font-title-lg text-title-lg mb-2">{{ item.title }}</h4>
                        <p class="text-on-surface-variant font-body-md text-body-md">{{ item.text }}</p>
                    </div>
                </div>
            </div>
        </section>

        <section v-else-if="systemWidget(section)?.type === 'services_partners'" :data-pb-section="pbAttr(section)" :data-pb-widget="pbAttr(systemWidget(section))" id="section-partenaires" class="py-stack-lg" :class="animationClass(section.settings)" :style="sectionStyle(section)">
            <div class="px-4 md:px-margin-desktop max-w-container-max-width mx-auto">
                <div class="text-center mb-12">
                    <span class="text-primary font-label-md uppercase tracking-widest">{{ systemWidget(section).content?.eyebrow }}</span>
                    <h2 class="font-headline-md text-headline-md mt-2">{{ systemWidget(section).content?.title }}</h2>
                    <p class="text-on-surface-variant font-body-md mt-2 max-w-2xl mx-auto">{{ systemWidget(section).content?.text }}</p>
                </div>
                <template v-if="servicePartners.length">
                    <div class="flex flex-col md:flex-row gap-3 mb-4">
                        <div class="relative flex-1">
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant text-[20px]">search</span>
                            <input v-model="partnerSearch" class="w-full pl-10 pr-3 py-2.5 text-sm border border-outline-variant rounded-lg bg-white focus:ring-2 focus:ring-primary focus:border-primary outline-none" placeholder="Rechercher une pharmacie, un hôpital, un laboratoire…" type="search" aria-label="Rechercher un prestataire" />
                        </div>
                        <select v-model="partnerCity" class="md:w-64 px-3 py-2.5 text-sm border border-outline-variant rounded-lg bg-white focus:ring-2 focus:ring-primary outline-none" aria-label="Filtrer par ville">
                            <option value="">Toutes les villes ({{ partnerCities.length }})</option>
                            <option v-for="item in partnerCities" :key="item.city" :value="item.city">{{ item.city }} ({{ item.count }})</option>
                        </select>
                    </div>
                    <div class="flex flex-wrap gap-2 mb-6" role="tablist" aria-label="Type de prestataire">
                        <button
                            v-for="item in partnerTypes"
                            :key="item.key"
                            type="button"
                            role="tab"
                            :aria-selected="partnerType === item.key"
                            class="inline-flex items-center gap-1.5 pl-3 pr-1.5 py-1.5 rounded-full text-xs font-semibold border transition-colors"
                            :class="partnerType === item.key ? 'bg-primary text-on-primary border-primary' : 'bg-white text-on-surface-variant border-outline-variant hover:border-primary'"
                            @click="partnerType = item.key"
                        >
                            <span class="material-symbols-outlined text-[16px]">{{ item.icon }}</span>
                            {{ item.label }}
                            <span class="min-w-[24px] px-1.5 py-0.5 rounded-full text-[11px] font-bold tabular-nums text-center" :class="partnerType === item.key ? 'bg-white/25' : 'bg-surface-container-high text-on-surface'">{{ partnerTypeCount(item.key) }}</span>
                        </button>
                    </div>

                    <div v-if="visiblePartners.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <article v-for="partner in visiblePartners" :key="partner.id" class="bg-white rounded-xl border border-outline-variant p-4 shadow-sm flex gap-3 hover:border-primary/40 transition-colors">
                            <img v-if="partner.imageSrc" :src="partner.imageSrc" :alt="partner.name" class="w-12 h-12 rounded-lg object-cover shrink-0" loading="lazy" />
                            <div v-else class="w-12 h-12 rounded-lg flex items-center justify-center shrink-0" :class="partner.type === 'Centre de santé' ? 'bg-secondary/10 text-secondary' : 'bg-tertiary/10 text-tertiary'">
                                <span class="material-symbols-outlined">{{ partnerIcon(partner.type) }}</span>
                            </div>
                            <div class="min-w-0 flex-1 flex flex-col">
                                <h3 class="font-title-lg text-sm font-bold text-on-surface leading-snug">{{ partner.name }}</h3>
                                <p class="text-xs text-on-surface-variant mt-1 flex items-start gap-1">
                                    <span class="material-symbols-outlined text-[14px] mt-px">place</span>
                                    <span>{{ partner.city }}<template v-if="partner.address"> · {{ partner.address }}</template></span>
                                </p>
                                <a v-if="partner.mapsUrl" :href="partner.mapsUrl" target="_blank" rel="noopener" class="mt-3 self-start inline-flex items-center gap-1 text-xs font-bold text-primary hover:underline">
                                    <span class="material-symbols-outlined text-[16px]">directions</span> Itinéraire
                                </a>
                            </div>
                        </article>
                    </div>
                    <p v-else class="text-center text-on-surface-variant py-10">Aucun prestataire ne correspond à votre recherche.</p>

                    <div class="flex flex-col sm:flex-row items-center justify-between gap-3 mt-6">
                        <p class="text-xs text-on-surface-variant">
                            {{ visiblePartners.length }} sur {{ filteredPartners.length }} prestataire{{ filteredPartners.length > 1 ? 's' : '' }}
                        </p>
                        <div class="flex gap-2">
                            <button v-if="visiblePartners.length < filteredPartners.length" type="button" class="inline-flex items-center gap-1.5 border border-outline text-on-surface px-4 py-2 rounded-lg text-xs font-bold hover:bg-surface-container-low transition-all" @click="partnerLimit += 24">
                                <span class="material-symbols-outlined text-[16px]">expand_more</span> Afficher plus ({{ filteredPartners.length - visiblePartners.length }} restants)
                            </button>
                            <Link href="/contact#section-carte" class="inline-flex items-center gap-1.5 bg-primary text-on-primary px-4 py-2 rounded-lg text-xs font-bold hover:opacity-90 transition-all shadow-sm">
                                <span class="material-symbols-outlined text-[16px]">map</span> Voir sur la carte
                            </Link>
                        </div>
                    </div>
                </template>
                <p v-else class="text-center text-on-surface-variant py-10">Aucun partenaire publié pour le moment.</p>
            </div>
        </section>

        <section v-else-if="systemWidget(section)?.type === 'services_faq'" :data-pb-section="pbAttr(section)" :data-pb-widget="pbAttr(systemWidget(section))" id="faq" class="py-stack-lg bg-surface-container-lowest" :class="animationClass(section.settings)" :style="sectionStyle(section)">
            <div class="px-4 md:px-margin-desktop max-w-container-max-width mx-auto">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-stack-lg">
                    <div class="lg:col-span-4">
                        <div class="lg:sticky lg:top-28">
                            <span class="text-primary font-label-md uppercase tracking-widest">{{ systemWidget(section).content?.eyebrow }}</span>
                            <h2 class="font-headline-lg text-headline-lg mt-2 mb-4">{{ systemWidget(section).content?.title }}</h2>
                            <p class="text-on-surface-variant font-body-md leading-relaxed mb-8">{{ systemWidget(section).content?.text }}</p>
                            <div class="bg-white p-6 rounded-xl border border-outline-variant shadow-sm flex items-start gap-4">
                                <div class="w-11 h-11 shrink-0 rounded-full bg-primary/10 text-primary flex items-center justify-center"><span class="material-symbols-outlined">support_agent</span></div>
                                <div>
                                    <p class="font-title-lg text-title-lg mb-1">{{ systemWidget(section).content?.cardTitle }}</p>
                                    <p class="text-on-surface-variant text-body-md text-sm mb-3">{{ systemWidget(section).content?.cardText }}</p>
                                    <Link class="text-primary font-bold text-sm inline-flex items-center gap-1 hover:underline" :href="systemWidget(section).content?.linkHref || '/contact'">
                                        {{ systemWidget(section).content?.linkLabel || 'Nous contacter' }} <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="lg:col-span-8 space-y-4">
                        <details v-for="(faq, index) in serviceFaqs" :key="faq.id" class="group bg-white rounded-xl border border-outline-variant overflow-hidden hover:border-primary/30 transition-colors" :open="index === 0">
                            <summary class="flex justify-between items-center gap-4 p-6 cursor-pointer hover:bg-surface-container-low transition-colors">
                                <span class="flex items-center gap-4">
                                    <span class="material-symbols-outlined p-2 rounded-lg shrink-0" :class="faqMeta(faq.category).class">{{ faqMeta(faq.category).icon }}</span>
                                    <span class="font-title-lg text-title-lg">{{ faq.question }}</span>
                                </span>
                                <span class="material-symbols-outlined text-on-surface-variant group-open:rotate-180 transition-transform shrink-0">expand_more</span>
                            </summary>
                            <div class="px-6 pb-6 pl-[4.5rem] text-on-surface-variant font-body-md border-t border-outline-variant pt-4">{{ faq.answer }}</div>
                        </details>
                        <p v-if="!serviceFaqs.length" class="text-on-surface-variant text-sm text-center py-8">Aucune question publiée pour le moment.</p>
                    </div>
                </div>
            </div>
        </section>

        <section v-else-if="systemWidget(section)?.type === 'services_cta'" :data-pb-section="pbAttr(section)" :data-pb-widget="pbAttr(systemWidget(section))" class="py-stack-lg bg-primary" :class="animationClass(section.settings)" :style="sectionStyle(section)">
            <div class="px-4 md:px-margin-desktop max-w-container-max-width mx-auto flex flex-col md:flex-row items-center justify-between gap-stack-lg text-on-primary">
                <div class="max-w-xl text-center md:text-left">
                    <h3 class="font-headline-lg text-headline-lg mb-2">{{ systemWidget(section).content?.title }}</h3>
                    <p class="font-body-md text-body-md opacity-90">{{ systemWidget(section).content?.text }}</p>
                </div>
                <div class="flex gap-4 w-full md:w-auto">
                    <Link class="bg-white text-primary px-8 py-4 rounded-xl font-bold flex-1 md:flex-none text-center hover:bg-surface-variant transition-colors" :href="systemWidget(section).content?.primaryHref || '/contact'">{{ systemWidget(section).content?.primaryLabel || 'Nous contacter' }}</Link>
                    <a class="bg-white/10 backdrop-blur-md border border-white/30 text-white px-8 py-4 rounded-xl font-bold flex-1 md:flex-none flex items-center justify-center gap-2 hover:bg-white/20 transition-colors" :href="systemWidget(section).content?.secondaryHref || 'tel:112'">
                        <span class="material-symbols-outlined">call</span> {{ systemWidget(section).content?.secondaryLabel || 'Urgence : 112' }}
                    </a>
                </div>
            </div>
        </section>

        <section v-else-if="systemWidget(section)?.type === 'about_hero'" :data-pb-section="pbAttr(section)" :data-pb-widget="pbAttr(systemWidget(section))" class="relative h-[420px] overflow-hidden flex items-center" :class="animationClass(section.settings)" :style="sectionStyle(section)">
            <div class="absolute inset-0 bg-cover bg-center bg-about-hero" />
            <div class="w-full px-6 md:px-16 relative z-10">
                <div class="text-white space-y-6 max-w-2xl mr-auto">
                    <span class="inline-block bg-black/40 backdrop-blur-md border border-white/25 text-white px-4 py-1.5 rounded-full font-label-md text-label-md uppercase tracking-wider shadow-sm">{{ systemWidget(section).content?.badge }}</span>
                    <h1 class="font-display-lg text-display-lg leading-tight">{{ systemWidget(section).content?.title }}</h1>
                    <p class="font-body-lg text-body-lg text-white/90 max-w-lg">{{ systemWidget(section).content?.text }}</p>
                </div>
            </div>
        </section>

        <section v-else-if="systemWidget(section)?.type === 'about_director'" :data-pb-section="pbAttr(section)" :data-pb-widget="pbAttr(systemWidget(section))" class="py-stack-lg bg-surface-container-lowest relative overflow-hidden" :class="animationClass(section.settings)" :style="sectionStyle(section)">
            <div class="absolute -left-24 top-1/2 -translate-y-1/2 w-72 h-72 bg-primary/5 rounded-full blur-3xl pointer-events-none" />
            <div class="max-w-container-max-width mx-auto px-4 md:px-margin-desktop relative z-10">
                <div class="text-center lg:text-left mb-12">
                    <span class="text-primary font-label-md uppercase tracking-widest">{{ systemWidget(section).content?.eyebrow }}</span>
                    <h2 class="font-headline-lg text-headline-lg mt-2">{{ systemWidget(section).content?.title }}</h2>
                </div>
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
                    <div class="lg:col-span-5">
                        <div class="relative max-w-sm mx-auto lg:max-w-none">
                            <div class="absolute -inset-3 bg-primary/10 rounded-2xl -z-10 hidden sm:block" />
                            <div class="aspect-[4/5] bg-surface-container-high rounded-xl overflow-hidden shadow-lg border border-outline-variant relative">
                                <img alt="Directeur Général de la CAMA" class="w-full h-full object-cover" :src="systemWidget(section).content?.imageSrc || '/images/directeur.jpg'" />
                                <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent" />
                                <div class="absolute bottom-0 left-0 right-0 p-5 text-white">
                                    <span class="inline-flex items-center gap-1.5 bg-secondary/90 text-on-secondary px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wide mb-2">
                                        <span class="material-symbols-outlined text-[14px]">verified</span>
                                        {{ systemWidget(section).content?.badge }}
                                    </span>
                                    <p class="font-title-lg text-title-lg font-bold leading-snug">{{ systemWidget(section).content?.name }}</p>
                                    <p class="font-caption text-caption text-white/80">{{ systemWidget(section).content?.role }}</p>
                                </div>
                            </div>
                            <div class="absolute -top-5 -right-5 w-14 h-14 bg-primary text-on-primary rounded-xl shadow-lg flex items-center justify-center">
                                <span class="material-symbols-outlined text-[28px]">format_quote</span>
                            </div>
                        </div>
                    </div>
                    <div class="lg:col-span-7 space-y-stack-md">
                        <p class="font-body-lg text-body-lg text-on-surface-variant leading-relaxed italic border-l-4 border-secondary pl-6">« {{ systemWidget(section).content?.quote }} »</p>
                        <p class="font-body-md text-body-md text-on-surface-variant">{{ systemWidget(section).content?.text }}</p>
                        <div class="pt-stack-md grid grid-cols-2 gap-4 max-w-md">
                            <div v-for="(stat, statIndex) in systemWidget(section).content?.stats || []" :key="statIndex" class="flex items-center gap-3 p-4 rounded-xl border transition-colors" :class="stat.tone === 'secondary' ? 'bg-secondary/5 border-secondary/10 hover:bg-secondary/10' : 'bg-primary/5 border-primary/10 hover:bg-primary/10'">
                                <div class="w-10 h-10 rounded-lg flex items-center justify-center shrink-0" :class="serviceTone(stat.tone)">
                                    <span class="material-symbols-outlined text-[20px]">{{ stat.icon }}</span>
                                </div>
                                <div class="flex flex-col leading-tight">
                                    <span class="font-headline-md text-headline-md" :class="serviceTextTone(stat.tone)">{{ stat.value }}</span>
                                    <span class="font-caption text-caption uppercase text-outline">{{ stat.label }}</span>
                                </div>
                            </div>
                        </div>
                        <Link class="inline-flex items-center gap-2 text-primary font-bold hover:underline group pt-2" :href="systemWidget(section).content?.linkHref || '/contact'">
                            {{ systemWidget(section).content?.linkLabel }}
                            <span class="material-symbols-outlined group-hover:translate-x-1 transition-transform">arrow_forward</span>
                        </Link>
                    </div>
                </div>
            </div>
        </section>

        <section v-else-if="systemWidget(section)?.type === 'about_timeline'" :data-pb-section="pbAttr(section)" :data-pb-widget="pbAttr(systemWidget(section))" class="py-stack-lg bg-surface-container-low" :class="animationClass(section.settings)" :style="sectionStyle(section)">
            <div class="max-w-container-max-width mx-auto px-4 md:px-margin-desktop">
                <div class="text-center mb-16 space-y-2">
                    <span class="text-primary font-label-md uppercase tracking-widest">{{ systemWidget(section).content?.eyebrow }}</span>
                    <h2 class="font-headline-lg text-headline-lg text-on-surface mt-2">{{ systemWidget(section).content?.title }}</h2>
                    <p class="font-body-md text-body-md text-on-surface-variant max-w-2xl mx-auto mt-3">{{ systemWidget(section).content?.text }}</p>
                </div>
                <div class="relative mb-4">
                    <div v-if="timelineColumns(section) > 1" class="hidden md:block absolute top-7 h-0.5 bg-outline-variant" :style="timelineLineStyle(section)" />
                    <div class="grid grid-cols-1 gap-gutter" :class="TIMELINE_GRID[timelineColumns(section)]">
                        <div v-for="(item, itemIndex) in systemWidget(section).content?.items || []" :key="itemIndex" class="group relative bg-white p-8 rounded-xl border border-outline-variant shadow-sm hover:shadow-lg hover:-translate-y-1 transition-all duration-300" :class="item.tone === 'secondary' ? 'hover:bg-secondary hover:border-secondary' : item.tone === 'tertiary' ? 'hover:bg-tertiary hover:border-tertiary' : 'hover:bg-primary hover:border-primary'">
                            <div class="w-14 h-14 rounded-full group-hover:bg-white flex items-center justify-center mb-6 transition-colors duration-300" :class="serviceTone(item.tone)">
                                <span class="material-symbols-outlined">{{ item.icon }}</span>
                            </div>
                            <span class="inline-block text-xs font-label-md uppercase tracking-wide font-bold mb-2 transition-colors duration-300 group-hover:text-white/80" :class="serviceTextTone(item.tone)">{{ item.date }}</span>
                            <h3 class="font-title-lg text-title-lg mb-2 text-on-surface group-hover:text-white transition-colors duration-300">{{ item.title }}</h3>
                            <p class="font-body-md text-body-md text-on-surface-variant group-hover:text-white/90 transition-colors duration-300">{{ item.text }}</p>
                        </div>
                    </div>
                </div>
                <div class="flex justify-center my-2">
                    <span class="material-symbols-outlined text-outline-variant text-3xl">arrow_downward</span>
                </div>
                <div class="text-on-primary p-12 rounded-xl shadow-xl overflow-hidden relative group bg-cover bg-center bg-about-today">
                    <div class="relative z-10 max-w-2xl">
                        <span class="inline-flex items-center gap-1.5 bg-white/15 backdrop-blur-sm px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wide mb-4"><span class="material-symbols-outlined text-[14px]">today</span>{{ systemWidget(section).content?.todayBadge }}</span>
                        <h3 class="font-headline-lg text-headline-lg mb-4">{{ systemWidget(section).content?.todayTitle }}</h3>
                        <p class="font-body-lg text-body-lg opacity-90">{{ systemWidget(section).content?.todayText }}</p>
                    </div>
                    <span class="absolute right-8 bottom-8 material-symbols-outlined text-8xl opacity-10 group-hover:scale-110 transition-transform duration-500">verified_user</span>
                </div>
            </div>
        </section>

        <section v-else-if="systemWidget(section)?.type === 'about_missions'" :data-pb-section="pbAttr(section)" :data-pb-widget="pbAttr(systemWidget(section))" class="py-stack-lg bg-surface-container" :class="animationClass(section.settings)" :style="sectionStyle(section)">
            <div class="max-w-container-max-width mx-auto px-4 md:px-margin-desktop">
                <div class="flex flex-col md:flex-row justify-between items-end mb-12 gap-6">
                    <div class="max-w-xl">
                        <h2 class="font-headline-lg text-headline-lg text-on-surface mb-2">{{ systemWidget(section).content?.title }}</h2>
                        <p class="font-body-md text-body-md text-on-surface-variant">{{ systemWidget(section).content?.text }}</p>
                    </div>
                    <Link class="flex items-center gap-2 text-primary font-bold group" :href="systemWidget(section).content?.linkHref || '/ressources'">
                        {{ systemWidget(section).content?.linkLabel }}
                        <span class="material-symbols-outlined group-hover:translate-x-1 transition-transform">arrow_forward</span>
                    </Link>
                </div>
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-stack-lg">
                    <div v-for="(item, itemIndex) in systemWidget(section).content?.items || []" :key="itemIndex" class="group p-8 bg-white border border-outline-variant rounded-lg shadow-sm hover:shadow-lg hover:-translate-y-1 transition-all duration-300" :class="item.tone === 'secondary' ? 'hover:bg-secondary hover:border-secondary' : item.tone === 'tertiary' ? 'hover:bg-tertiary hover:border-tertiary' : 'hover:bg-primary hover:border-primary'">
                        <div class="w-12 h-12 rounded-lg group-hover:bg-white flex items-center justify-center mb-6 transition-colors duration-300" :class="serviceSoftTone(item.tone)">
                            <span class="material-symbols-outlined" :class="serviceTextTone(item.tone)">{{ item.icon }}</span>
                        </div>
                        <h4 class="font-title-lg text-title-lg mb-4 group-hover:text-white transition-colors duration-300">{{ item.title }}</h4>
                        <ul class="space-y-3 font-body-md text-body-md text-on-surface-variant group-hover:text-white/90 transition-colors duration-300">
                            <li v-for="(line, lineIndex) in item.items || []" :key="lineIndex" class="flex gap-2"><span class="group-hover:text-white">●</span> {{ line }}</li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        <section v-else-if="systemWidget(section)?.type === 'about_cta'" :data-pb-section="pbAttr(section)" :data-pb-widget="pbAttr(systemWidget(section))" class="py-stack-lg px-4 md:px-margin-desktop mb-stack-lg" :class="animationClass(section.settings)" :style="sectionStyle(section)">
            <div class="max-w-container-max-width mx-auto bg-surface-container p-stack-lg rounded-3xl flex flex-col md:flex-row items-center justify-between gap-stack-lg border border-outline-variant overflow-hidden relative">
                <div class="absolute -left-10 -bottom-10 w-40 h-40 bg-secondary/5 rounded-full blur-3xl pointer-events-none" />
                <div class="relative z-10 text-center md:text-left">
                    <h3 class="font-headline-lg text-headline-lg mb-2">{{ systemWidget(section).content?.title }}</h3>
                    <p class="font-body-md text-body-md text-on-surface-variant">{{ systemWidget(section).content?.text }}</p>
                </div>
                <div class="flex gap-4 relative z-10 w-full md:w-auto">
                    <Link class="bg-on-background text-surface px-8 py-4 rounded-xl font-bold flex-1 md:flex-none text-center hover:opacity-90 transition-opacity" :href="systemWidget(section).content?.primaryHref || '/ressources'">
                        {{ systemWidget(section).content?.primaryLabel || "Guide de l'assuré" }}
                    </Link>
                    <a class="bg-secondary text-on-secondary px-8 py-4 rounded-xl font-bold flex-1 md:flex-none flex items-center justify-center gap-2 hover:opacity-90 transition-opacity" :href="systemWidget(section).content?.secondaryHref || 'tel:+22625308103'">
                        <span class="material-symbols-outlined">call</span>
                        {{ systemWidget(section).content?.secondaryLabel || 'Nous appeler' }}
                    </a>
                </div>
            </div>
        </section>

        <section v-else-if="systemWidget(section)?.type === 'resources_hero'" :data-pb-section="pbAttr(section)" :data-pb-widget="pbAttr(systemWidget(section))" class="relative py-16 md:py-24 overflow-hidden text-white bg-cover bg-center bg-resources-hero" :class="animationClass(section.settings)" :style="sectionStyle(section)">
            <div class="relative z-10 max-w-container-max-width mx-auto px-4 md:px-margin-desktop text-center">
                <span class="inline-flex items-center gap-2 bg-white/10 backdrop-blur-md border border-white/20 px-4 py-1.5 rounded-full font-label-md text-label-md uppercase tracking-wider mb-6">
                    <span class="material-symbols-outlined text-[18px]">{{ systemWidget(section).content?.badgeIcon || 'folder_open' }}</span> {{ systemWidget(section).content?.badge }}
                </span>
                <h1 class="font-headline-lg text-headline-lg mb-4">{{ systemWidget(section).content?.title }}</h1>
                <p class="font-body-lg text-body-lg text-white/85 max-w-2xl mx-auto">{{ systemWidget(section).content?.text }}</p>
            </div>
        </section>

        <section v-else-if="systemWidget(section)?.type === 'resources_listing'" :data-pb-section="pbAttr(section)" :data-pb-widget="pbAttr(systemWidget(section))" class="py-stack-lg bg-surface-container-low" :class="animationClass(section.settings)" :style="sectionStyle(section)">
            <div class="max-w-container-max-width mx-auto px-4 md:px-margin-desktop">
                <div class="flex flex-wrap gap-2 mb-8 justify-center">
                    <button
                        v-for="category in resourceCategories"
                        :key="category"
                        type="button"
                        class="px-4 py-2 rounded-full text-sm font-semibold border transition-colors"
                        :class="resourceActiveCategory === category ? 'bg-primary text-on-primary border-primary' : 'bg-white text-on-surface-variant border-outline-variant hover:border-primary'"
                        @click="resourceActiveCategory = category"
                    >
                        {{ category }}
                    </button>
                </div>

                <div ref="resourcesTop" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5 scroll-mt-28">
                    <article v-for="resource in pagedResources" :key="resource.id" class="bg-white rounded-xl border border-outline-variant p-5 flex flex-col shadow-sm hover:shadow-md transition-shadow">
                        <div class="flex items-start gap-3 mb-3">
                            <span class="w-11 h-11 rounded-lg bg-primary/10 text-primary flex items-center justify-center shrink-0"><span class="material-symbols-outlined">{{ resourceIcon(resource.format) }}</span></span>
                            <div class="min-w-0">
                                <span class="inline-block text-[11px] font-semibold uppercase tracking-wide text-primary mb-0.5">{{ resource.category }}</span>
                                <h3 class="font-title-lg text-base leading-snug text-on-surface">{{ resource.title }}</h3>
                            </div>
                        </div>
                        <p class="text-sm text-on-surface-variant flex-1 mb-4">{{ resource.description }}</p>
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-xs text-on-surface-variant flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-[15px]">{{ resourceIcon(resource.format) }}</span>
                                {{ resource.format || 'PDF' }}<span v-if="resource.size"> · {{ resource.size }}</span>
                            </span>
                            <a
                                class="inline-flex items-center gap-1.5 bg-primary text-on-primary px-3.5 py-2 rounded-lg text-xs font-bold hover:opacity-90 transition-all"
                                :href="resource.url || '#'"
                                :download="resource.url && resource.url !== '#' ? true : null"
                                target="_blank"
                                rel="noopener"
                                @click="showResourceToast($event, resource)"
                            >
                                <span class="material-symbols-outlined text-[16px]">download</span> {{ systemWidget(section).content?.downloadLabel || 'Télécharger' }}
                            </a>
                        </div>
                    </article>
                </div>

                <p v-if="!filteredResources.length" class="text-center text-on-surface-variant py-16">{{ systemWidget(section).content?.emptyText }}</p>
                <CamaPagination v-if="resourcesPagination.lastPage > 1" class="mt-6" v-bind="resourcesPagination" label="document(s)" @change="setResourcesPage" />
            </div>
            <div class="fixed bottom-6 right-6 z-[70]" :class="{ hidden: !resourceToast }">
                <div class="bg-on-background text-white px-5 py-3 rounded-lg shadow-xl flex items-center gap-2 text-sm font-semibold">
                    <span class="material-symbols-outlined">info</span> {{ resourceToast }}
                </div>
            </div>
        </section>

        <section v-else-if="systemWidget(section)?.type === 'news_hero'" :data-pb-section="pbAttr(section)" :data-pb-widget="pbAttr(systemWidget(section))" class="relative py-20 md:py-24 text-white overflow-hidden bg-cover bg-center bg-news-hero" :class="animationClass(section.settings)" :style="sectionStyle(section)">
            <div class="relative max-w-container-max-width mx-auto px-4 md:px-margin-desktop text-center">
                <h1 class="font-headline-lg text-headline-lg mb-4">{{ systemWidget(section).content?.title }}</h1>
                <p class="font-body-lg text-body-lg max-w-2xl mx-auto opacity-80">{{ systemWidget(section).content?.text }}</p>
            </div>
        </section>

        <section v-else-if="systemWidget(section)?.type === 'news_filters'" :data-pb-section="pbAttr(section)" :data-pb-widget="pbAttr(systemWidget(section))" class="py-6 border-b border-outline-variant bg-surface" :class="animationClass(section.settings)" :style="sectionStyle(section)">
            <div class="max-w-container-max-width mx-auto px-4 md:px-margin-desktop">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="category in newsFilters"
                            :key="category"
                            type="button"
                            class="px-4 py-2 rounded-full font-label-md text-label-md transition-colors"
                            :class="category === newsActiveCategory ? 'bg-primary text-on-primary' : 'bg-surface-container-high text-on-surface-variant hover:bg-outline-variant'"
                            @click="selectNewsCategory(category)"
                        >
                            {{ category }}
                        </button>
                    </div>
                    <div class="relative w-full md:w-80">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant">search</span>
                        <input v-model="newsSearchQuery" class="w-full pl-10 pr-4 py-2 bg-white border border-outline rounded-lg focus:ring-2 focus:ring-primary focus:border-primary outline-none font-body-md text-body-md" :placeholder="systemWidget(section).content?.searchPlaceholder" type="search" />
                    </div>
                </div>
            </div>
        </section>

        <section v-else-if="systemWidget(section)?.type === 'news_listing'" :data-pb-section="pbAttr(section)" :data-pb-widget="pbAttr(systemWidget(section))" class="py-12 md:py-16 bg-background" :class="animationClass(section.settings)" :style="sectionStyle(section)">
            <div class="max-w-container-max-width mx-auto px-4 md:px-margin-desktop">
                <div v-if="featuredNews" class="grid grid-cols-1 lg:grid-cols-12 gap-gutter mb-12">
                    <article class="lg:col-span-8 group relative overflow-hidden rounded-xl bg-white border border-outline-variant shadow-sm hover:shadow-md transition-shadow">
                        <div class="aspect-video overflow-hidden">
                            <img :alt="featuredNews.title" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105" :src="featuredNews.imageSrc" />
                        </div>
                        <div class="p-6 md:p-8">
                            <div class="flex items-center gap-3 mb-4">
                                <span class="bg-primary text-on-primary px-3 py-1 rounded text-caption font-bold uppercase tracking-wider">{{ featuredNews.category }}</span>
                                <span class="text-on-surface-variant font-label-md text-label-md">{{ featuredNews.date }}</span>
                            </div>
                            <h2 class="font-headline-md text-headline-md mb-4 text-on-surface group-hover:text-primary transition-colors">{{ featuredNews.title }}</h2>
                            <p class="font-body-md text-body-md text-on-surface-variant mb-6 line-clamp-3">{{ featuredNews.excerpt }}</p>
                            <Link class="inline-flex items-center gap-2 text-primary font-bold hover:underline" :href="featuredNews.href">
                                {{ systemWidget(section).content?.readLabel || 'Lire la suite' }} <span class="material-symbols-outlined">arrow_forward</span>
                            </Link>
                        </div>
                    </article>
                </div>

                <div ref="newsTop" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-gutter scroll-mt-28">
                    <article v-for="article in pagedNews" :key="article.id" class="bg-white border border-outline-variant rounded-xl overflow-hidden shadow-sm hover:shadow-md transition-shadow">
                        <div class="h-48 overflow-hidden"><img :alt="article.title" class="w-full h-full object-cover" :src="article.imageSrc" /></div>
                        <div class="p-6">
                            <span class="text-on-surface-variant font-label-md text-label-md mb-2 block">{{ article.date }}</span>
                            <h3 class="font-title-lg text-title-lg mb-3 text-on-surface">{{ article.title }}</h3>
                            <p class="font-body-md text-body-md text-on-surface-variant mb-6 line-clamp-2">{{ article.excerpt }}</p>
                            <Link class="text-primary font-bold flex items-center gap-2 group" :href="article.href">
                                {{ systemWidget(section).content?.cardReadLabel || "Lire l'article" }} <span class="material-symbols-outlined group-hover:translate-x-1 transition-transform">arrow_forward</span>
                            </Link>
                        </div>
                    </article>
                    <p v-if="!featuredNews" class="md:col-span-2 lg:col-span-3 text-center text-on-surface-variant py-12">{{ systemWidget(section).content?.emptyText }}</p>
                </div>

                <CamaPagination v-if="newsPagination.lastPage > 1" class="mt-8" v-bind="newsPagination" label="article(s)" @change="setNewsPage" />
            </div>
        </section>

        <section v-else-if="systemWidget(section)?.type === 'news_newsletter'" :data-pb-section="pbAttr(section)" :data-pb-widget="pbAttr(systemWidget(section))" class="py-12 bg-surface-container-low border-t border-outline-variant" :class="animationClass(section.settings)" :style="sectionStyle(section)">
            <div class="max-w-container-max-width mx-auto px-4 md:px-margin-desktop">
                <div class="bg-white p-8 md:p-10 rounded-2xl flex flex-col md:flex-row items-center justify-between gap-8 shadow-sm border border-outline-variant">
                    <div class="max-w-md text-center md:text-left">
                        <h2 class="font-headline-md text-headline-md mb-2">{{ systemWidget(section).content?.title }}</h2>
                        <p class="font-body-md text-body-md text-on-surface-variant">{{ systemWidget(section).content?.text }}</p>
                    </div>
                    <form class="flex flex-col sm:flex-row gap-3 w-full md:w-auto" @submit.prevent="submitNewsletter">
                        <label class="sr-only" for="newsletter-name">Prénom ou nom</label>
                        <input id="newsletter-name" v-model="newsletterForm.name" class="px-4 py-3 border border-outline rounded-lg outline-none focus:ring-2 focus:ring-primary w-full sm:w-40" placeholder="Votre nom" type="text" />
                        <label class="sr-only" for="newsletter-email">Email newsletter</label>
                        <input id="newsletter-email" v-model="newsletterForm.email" class="px-6 py-3 border border-outline rounded-lg outline-none focus:ring-2 focus:ring-primary w-full sm:w-64" :placeholder="systemWidget(section).content?.placeholder" type="email" required />
                        <button class="bg-primary text-on-primary px-8 py-3 rounded-lg font-bold hover:opacity-90 transition-opacity disabled:opacity-60" :disabled="newsletterForm.processing" type="submit">{{ newsletterSent ? 'Inscrit' : (systemWidget(section).content?.buttonLabel || 'S’abonner') }}</button>
                    </form>
                </div>
                <p v-if="newsletterForm.errors.email" class="text-xs text-error mt-2">{{ newsletterForm.errors.email }}</p>
                <p v-if="flashSuccess" class="text-xs text-secondary mt-2 font-semibold">{{ flashSuccess }}</p>
                <p v-if="flashError" class="text-xs text-error mt-2 font-semibold">{{ flashError }}</p>
            </div>
        </section>

        <section v-else-if="systemWidget(section)?.type === 'contact_hero'" :data-pb-section="pbAttr(section)" :data-pb-widget="pbAttr(systemWidget(section))" class="relative py-20 md:py-24 text-white overflow-hidden bg-cover bg-center bg-contact-hero" :class="animationClass(section.settings)" :style="sectionStyle(section)">
            <div class="relative max-w-container-max-width mx-auto px-4 md:px-margin-desktop text-center">
                <p class="text-white/70 text-xs font-semibold uppercase tracking-widest mb-3">{{ systemWidget(section).content?.eyebrow }}</p>
                <h1 class="font-headline-lg text-headline-lg mb-4">{{ systemWidget(section).content?.title }}</h1>
                <p class="font-body-lg text-body-lg max-w-2xl mx-auto opacity-80">{{ systemWidget(section).content?.text }}</p>
            </div>
        </section>

        <section v-else-if="systemWidget(section)?.type === 'contact_form'" :data-pb-section="pbAttr(section)" :data-pb-widget="pbAttr(systemWidget(section))" id="section-contact" class="contact-section py-stack-lg" :class="animationClass(section.settings)" :style="sectionStyle(section)">
            <div class="max-w-container-max-width mx-auto px-4 md:px-margin-desktop">
                <div class="max-w-2xl mx-auto">
                    <div class="bg-white p-6 md:p-8 rounded-xl border border-outline-variant shadow-sm">
                        <h2 class="font-headline-md text-xl text-on-surface mb-1">{{ systemWidget(section).content?.title }}</h2>
                        <p class="text-on-surface-variant text-sm mb-6">{{ systemWidget(section).content?.text }}</p>
                        <form class="grid grid-cols-1 md:grid-cols-2 gap-5" @submit.prevent="submitContact">
                            <div class="md:col-span-2 space-y-1.5"><label class="text-sm font-medium text-on-surface-variant" for="contact-full-name">Nom complet</label><input id="contact-full-name" v-model="contactForm.full_name" class="w-full px-4 py-3 text-sm border border-outline-variant rounded-lg focus:ring-2 focus:ring-primary focus:border-primary outline-none" placeholder="Ex : Jean-Baptiste Sawadogo" type="text" required /></div>
                            <div class="space-y-1.5"><label class="text-sm font-medium text-on-surface-variant" for="contact-email">E-mail</label><input id="contact-email" v-model="contactForm.email" class="w-full px-4 py-3 text-sm border border-outline-variant rounded-lg focus:ring-2 focus:ring-primary outline-none" placeholder="vous@exemple.bf" type="email" /></div>
                            <div class="space-y-1.5"><label class="text-sm font-medium text-on-surface-variant" for="contact-phone">Téléphone</label><input id="contact-phone" v-model="contactForm.phone" class="w-full px-4 py-3 text-sm border border-outline-variant rounded-lg focus:ring-2 focus:ring-primary outline-none" placeholder="+226 …" type="tel" /></div>
                            <div class="md:col-span-2 space-y-1.5"><label class="text-sm font-medium text-on-surface-variant" for="contact-subject">Objet</label><select id="contact-subject" v-model="contactForm.subject" class="w-full px-4 py-3 text-sm border border-outline-variant rounded-lg focus:ring-2 focus:ring-primary outline-none bg-white"><option>Nouvel enrôlement</option><option>Demande de remboursement</option><option>Problème technique portail</option><option>Mise à jour ayant-droit</option><option>Autre</option></select></div>
                            <div class="md:col-span-2 space-y-1.5"><label class="text-sm font-medium text-on-surface-variant" for="contact-message">Message</label><textarea id="contact-message" v-model="contactForm.message" class="w-full px-4 py-3 text-sm border border-outline-variant rounded-lg focus:ring-2 focus:ring-primary outline-none resize-none" placeholder="Décrivez votre demande…" rows="5" required /></div>
                            <div class="md:col-span-2 flex items-start gap-3"><input id="contact-consent" v-model="contactForm.consent" class="w-4 h-4 mt-1 text-primary border-outline-variant rounded focus:ring-primary" type="checkbox" required /><label class="text-caption text-on-surface-variant" for="contact-consent">Je consens au traitement de mes données conformément à la <a class="text-primary underline" href="/mention_legales#rgpd">politique de confidentialité</a>.</label></div>
                            <div v-if="contactError || contactForm.errors.full_name || contactForm.errors.message || contactForm.errors.consent" class="md:col-span-2 text-xs text-error">
                                {{ contactError || contactForm.errors.full_name || contactForm.errors.message || contactForm.errors.consent }}
                            </div>
                            <div class="md:col-span-2">
                                <button class="w-full md:w-auto text-white px-8 py-3.5 rounded-lg font-bold text-sm hover:brightness-110 active:scale-[0.98] transition-all shadow-md flex items-center justify-center gap-2 disabled:opacity-60" :class="contactSent ? 'bg-secondary' : 'bg-primary'" :disabled="contactForm.processing" type="submit">
                                    <span class="material-symbols-outlined text-[20px]">{{ contactSent ? 'check_circle' : 'send' }}</span>
                                    {{ contactSent ? systemWidget(section).content?.successLabel : systemWidget(section).content?.buttonLabel }}
                                </button>
                            </div>
                            <div v-if="flashSuccess" class="md:col-span-2 text-xs text-secondary font-semibold">{{ flashSuccess }}</div>
                            <div v-if="flashError" class="md:col-span-2 text-xs text-error font-semibold">{{ flashError }}</div>
                        </form>
                    </div>
                </div>
            </div>
        </section>

        <section v-else-if="systemWidget(section)?.type === 'contact_map'" :data-pb-section="pbAttr(section)" :data-pb-widget="pbAttr(systemWidget(section))" id="section-carte" class="contact-section bg-surface-container-low border-t border-outline-variant py-stack-lg" :class="animationClass(section.settings)" :style="sectionStyle(section)">
            <div class="max-w-container-max-width mx-auto px-4 md:px-margin-desktop">
                <div class="mb-6">
                    <h2 class="font-headline-md text-xl md:text-2xl text-on-surface mb-1">{{ systemWidget(section).content?.title }}</h2>
                    <p class="text-sm text-on-surface-variant max-w-2xl">{{ systemWidget(section).content?.text }}</p>
                    <div class="flex flex-wrap gap-2 mt-4">
                        <span class="inline-flex items-center gap-1.5 bg-white border border-outline-variant rounded-full px-3 py-1.5 text-xs font-semibold text-on-surface shadow-sm"><span class="material-symbols-outlined text-[16px] text-primary">account_balance</span> Siège / antenne CAMA</span>
                        <span class="inline-flex items-center gap-1.5 bg-white border border-outline-variant rounded-full px-3 py-1.5 text-xs font-semibold text-on-surface shadow-sm"><span class="material-symbols-outlined text-[16px] text-secondary">local_hospital</span> Centre de santé</span>
                        <span class="inline-flex items-center gap-1.5 bg-white border border-outline-variant rounded-full px-3 py-1.5 text-xs font-semibold text-on-surface shadow-sm"><span class="material-symbols-outlined text-[16px] text-tertiary">handshake</span> Partenaire</span>
                    </div>
                </div>
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
                    <div class="lg:col-span-4 flex flex-col gap-3">
                        <div class="relative"><span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant text-[18px]">search</span><input v-model="contactSearchQuery" class="w-full pl-9 pr-3 py-2.5 text-sm border border-outline-variant rounded-lg bg-white focus:ring-2 focus:ring-primary focus:border-primary outline-none" :placeholder="systemWidget(section).content?.searchPlaceholder" type="text" /></div>
                        <div class="flex flex-wrap gap-1.5">
                            <button v-for="filter in contactFilters" :key="filter.key" type="button" class="px-2.5 py-1 rounded-full text-[11px] font-semibold border transition-colors" :class="contactActiveFilter === filter.key ? 'bg-primary text-on-primary border-primary' : 'bg-white text-on-surface-variant border-outline-variant hover:border-primary'" @click="contactActiveFilter = filter.key">
                                {{ filter.label }} <span class="opacity-70">{{ contactFilterCount(filter) }}</span>
                            </button>
                        </div>
                        <div class="flex flex-col gap-2 overflow-y-auto loc-scroll pr-1 max-h-[60vh] lg:max-h-none lg:h-[392px]" role="listbox" aria-label="Points CAMA">
                            <button v-for="location in filteredContactLocations" :key="location.id" type="button" role="option" class="location-card w-full text-left p-3.5 rounded-xl border border-outline-variant bg-white" :class="{ 'is-active': location.id === (contactActiveId || activeContactLocation?.id) }" :aria-selected="location.id === (contactActiveId || activeContactLocation?.id)" @click="contactActiveId = location.id">
                                <div class="flex gap-3 items-center">
                                    <div class="location-icon w-9 h-9 rounded-lg flex items-center justify-center shrink-0" :class="contactStyle(location.type)"><span class="material-symbols-outlined text-[20px]">{{ contactIcon(location.type) }}</span></div>
                                    <div class="min-w-0"><p class="text-sm font-bold text-on-surface truncate">{{ location.name }}</p><p class="text-xs text-on-surface-variant mt-0.5 line-clamp-1">{{ location.address }}</p></div>
                                </div>
                            </button>
                            <div v-if="!filteredContactLocations.length" class="text-center text-on-surface-variant text-sm py-10">Aucun point ne correspond.</div>
                        </div>
                        <p class="text-xs text-on-surface-variant">{{ filteredContactLocations.length }} point{{ filteredContactLocations.length > 1 ? 's' : '' }} affiché{{ filteredContactLocations.length > 1 ? 's' : '' }}</p>
                    </div>
                    <div class="lg:col-span-8">
                        <div class="bg-white rounded-xl border border-outline-variant overflow-hidden shadow-sm flex flex-col min-h-[380px] lg:min-h-[440px]">
                            <div class="px-4 py-3 border-b border-outline-variant flex flex-wrap items-center justify-between gap-2">
                                <div><p class="text-sm font-semibold text-on-surface">{{ activeContactLocation?.name }}</p><p class="text-xs text-on-surface-variant mt-0.5">{{ activeContactLocation?.address }}<template v-if="activeContactLocation?.hours"><br />{{ activeContactLocation.hours }}</template><template v-if="activeContactLocation?.phone"> · {{ activeContactLocation.phone }}</template><template v-if="activeContactLocation?.email"> · {{ activeContactLocation.email }}</template></p></div>
                                <a v-if="activeContactLocation" class="text-xs font-bold text-primary flex items-center gap-1 hover:underline shrink-0" :href="mapsUrl(activeContactLocation)" rel="noopener" target="_blank"><span class="material-symbols-outlined text-[16px]">directions</span> {{ systemWidget(section).content?.directionsLabel || 'Itinéraire' }}</a>
                            </div>
                            <div class="relative flex-1 min-h-[320px] bg-[#ebe6dc]">
                                <CamaGoogleMap :api-key="contactApiKey" :points="contactMapPoints" :focus-point="contactMapFocus" height="440px" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section v-else-if="systemWidget(section)?.type === 'legal_page'" class="py-stack-lg" :class="animationClass(section.settings)" :style="sectionStyle(section)" :data-pb-section="pbAttr(section)" :data-pb-widget="pbAttr(systemWidget(section))">
            <div class="max-w-container-max-width mx-auto px-4 md:px-margin-desktop">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-gutter">
                    <aside class="hidden lg:block lg:col-span-3">
                        <nav class="sticky top-28 space-y-1 p-4 bg-surface-container-low rounded-xl border border-outline-variant" aria-label="Sommaire des mentions légales">
                            <p class="font-headline-md text-lg font-bold text-primary mb-3 px-2">Sommaire</p>
                            <a
                                v-for="(article, articleIndex) in systemWidget(section).content?.articles || []"
                                :key="articleIndex"
                                class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-on-surface-variant hover:bg-surface-container-high hover:text-primary transition-colors text-sm font-semibold"
                                :href="`#${legalAnchor(article, articleIndex)}`"
                            >
                                <span class="material-symbols-outlined text-[20px]">{{ article.icon || 'article' }}</span> {{ article.title }}
                            </a>
                            <a v-if="siteFooter.address || siteFooter.phones?.length || siteFooter.emails?.length" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-on-surface-variant hover:bg-surface-container-high hover:text-primary transition-colors text-sm font-semibold" href="#coordonnees">
                                <span class="material-symbols-outlined text-[20px]">contact_mail</span> Coordonnées
                            </a>
                        </nav>
                    </aside>

                    <div class="lg:col-span-9 min-w-0 space-y-6">
                        <header class="relative overflow-hidden rounded-xl bg-primary-container text-white p-8 md:p-12">
                            <div class="relative z-10">
                                <h1 class="font-headline-lg text-3xl md:text-headline-lg leading-tight mb-4">{{ systemWidget(section).content?.title }}</h1>
                                <p v-if="systemWidget(section).content?.intro" class="font-body-lg md:text-body-lg max-w-2xl opacity-90">{{ systemWidget(section).content.intro }}</p>
                            </div>
                            <span class="material-symbols-outlined pointer-events-none absolute -right-10 -bottom-16 text-[240px] opacity-10" aria-hidden="true">shield</span>
                        </header>

                        <article
                            v-for="(article, articleIndex) in systemWidget(section).content?.articles || []"
                            :id="legalAnchor(article, articleIndex)"
                            :key="articleIndex"
                            class="scroll-mt-28 bg-white p-6 md:p-8 rounded-xl border border-outline-variant"
                        >
                            <h2 class="font-headline-md text-xl md:text-2xl font-bold text-on-surface mb-4 flex items-center gap-2">
                                <span class="material-symbols-outlined text-primary">{{ article.icon || 'article' }}</span> {{ article.title }}
                            </h2>
                            <template v-for="(block, blockIndex) in legalBlocks(article.body)" :key="blockIndex">
                                <p v-if="block.type === 'p'" class="text-on-surface-variant leading-relaxed mb-4 last:mb-0">{{ block.text }}</p>
                                <ul v-else class="list-disc ml-6 space-y-2 text-on-surface-variant mb-4 last:mb-0">
                                    <li v-for="(item, itemIndex) in block.items" :key="itemIndex">{{ item }}</li>
                                </ul>
                            </template>
                        </article>

                        <article v-if="siteFooter.address || siteFooter.phones?.length || siteFooter.emails?.length" id="coordonnees" class="scroll-mt-28 bg-white p-6 md:p-8 rounded-xl border border-outline-variant">
                            <h2 class="font-headline-md text-xl md:text-2xl font-bold text-on-surface mb-5 flex items-center gap-2">
                                <span class="material-symbols-outlined text-primary">contact_mail</span> Coordonnées
                            </h2>
                            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-5 text-sm">
                                <div v-if="siteFooter.address">
                                    <dt class="text-xs uppercase tracking-wide text-on-surface-variant mb-1">Adresse</dt>
                                    <dd class="text-on-surface">{{ siteFooter.address }}</dd>
                                </div>
                                <div v-if="siteFooter.hours">
                                    <dt class="text-xs uppercase tracking-wide text-on-surface-variant mb-1">Horaires</dt>
                                    <dd class="text-on-surface">{{ siteFooter.hours }}</dd>
                                </div>
                                <div v-if="siteFooter.phones?.length">
                                    <dt class="text-xs uppercase tracking-wide text-on-surface-variant mb-1">Téléphone</dt>
                                    <dd v-for="phone in siteFooter.phones" :key="phone"><a class="text-primary font-semibold hover:underline" :href="`tel:${phone.replace(/\s+/g, '')}`">{{ phone }}</a></dd>
                                </div>
                                <div v-if="siteFooter.emails?.length">
                                    <dt class="text-xs uppercase tracking-wide text-on-surface-variant mb-1">E-mail</dt>
                                    <dd v-for="email in siteFooter.emails" :key="email" class="break-all"><a class="text-primary font-semibold hover:underline" :href="`mailto:${email}`">{{ email }}</a></dd>
                                </div>
                            </dl>
                        </article>
                    </div>
                </div>
            </div>
        </section>

        <section
            v-else
            class="cms-page-section"
            :data-pb-section="pbAttr(section)"
            :class="animationClass(section.settings)"
            :style="sectionStyle(section)"
        >
            <div class="max-w-container-max-width mx-auto px-4 md:px-margin-desktop">
                <div class="cms-page-row flex flex-wrap gap-6">
                    <div
                        v-for="(column, columnIndex) in section.columns"
                        :key="column.uid ?? columnIndex"
                        class="cms-page-column"
                        :class="animationClass(column.settings)"
                        :style="columnStyle(column)"
                        :data-pb-column="pbAttr(column)"
                    >
                        <div v-if="pbEditing && !column.widgets?.length" class="rounded-lg border-2 border-dashed border-outline-variant py-8 text-center text-[11px] text-on-surface-variant">
                            Glissez un élément ici
                        </div>
                        <div
                            v-for="(widget, widgetIndex) in column.widgets"
                            :key="widget.uid ?? widgetIndex"
                            class="cms-page-widget"
                            :class="widgetClasses(widget)"
                            :style="styleBox(widget.style)"
                            :data-pb-widget="pbAttr(widget)"
                        >
                            <component
                                :is="widget.content?.tag || 'h2'"
                                v-if="widget.type === 'heading'"
                                :class="[alignClass(widget.style), widget.style?.size || 'text-3xl', widget.style?.weight || 'font-bold', 'leading-tight']"
                                :style="styleBox(widget.style)"
                            >
                                {{ widget.content?.text }}
                            </component>

                            <ElementorAccordion
                                v-else-if="widget.type === 'elementor_accordion'"
                                :items="widget.content?.items ?? []"
                                :title-color="widget.style?.titleColor || 'rgb(var(--c-on-surface))'"
                                :text-color="widget.style?.textColor || 'rgb(var(--c-on-surface-variant))'"
                            />

                            <ElementorTabs
                                v-else-if="widget.type === 'elementor_tabs'"
                                :items="widget.content?.items ?? []"
                                :title-color="widget.style?.titleColor || 'rgb(var(--c-on-surface))'"
                                :text-color="widget.style?.textColor || 'rgb(var(--c-on-surface-variant))'"
                            />

                            <ElementorCarousel
                                v-else-if="widget.type === 'elementor_carousel'"
                                :items="widget.content?.items ?? []"
                                :gap="widget.style?.gap ?? 24"
                                :slides-per-view="widget.style?.slidesPerView ?? 1"
                            />

                            <ElementorPosts
                                v-else-if="widget.type === 'elementor_posts' || widget.type === 'elementor_loop_grid'"
                                :title="widget.content?.title || ''"
                                :articles="resolvePostsArticles(widget)"
                                :columns="widget.style?.columns ?? 3"
                                :variant="widget.type === 'elementor_loop_grid' ? 'loop_grid' : 'posts'"
                            />

                            <div
                                v-else-if="widget.type?.startsWith('elementor_')"
                                v-html="renderElementorWidgetHtml(widget)"
                            />

                            <div
                                v-else-if="widget.type === 'text'"
                                :class="[alignClass(widget.style), widget.style?.size || 'text-base', 'leading-relaxed']"
                                :style="styleBox(widget.style)"
                                v-html="widget.content?.html"
                            />

                            <div v-else-if="widget.type === 'button'" :class="alignClass(widget.style)" :style="styleBox(widget.style)">
                                <a :href="widget.content?.href || '#'" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg font-bold text-sm" :style="{ backgroundColor: colorValue(widget.style?.bgColor || 'primary'), color: colorValue(widget.style?.textColor || 'white') }">
                                    <span v-if="widget.content?.icon" class="material-symbols-outlined text-[18px]">{{ widget.content.icon }}</span>
                                    {{ widget.content?.text || 'Bouton' }}
                                </a>
                            </div>

                            <div v-else-if="widget.type === 'image'" :class="alignClass(widget.style)" :style="styleBox(widget.style)">
                                <img v-if="widget.content?.src" :src="widget.content.src" :alt="widget.content?.alt || ''" class="w-full object-cover" :class="widget.content?.ratio || 'aspect-video'" :style="{ borderRadius: `${widget.style?.radius || 0}px` }" />
                                <div v-else class="aspect-video bg-surface-container-low flex items-center justify-center">Image</div>
                            </div>

                            <div v-else-if="widget.type === 'iconbox'" :class="alignClass(widget.style)" :style="styleBox(widget.style)">
                                <div class="w-12 h-12 rounded-xl bg-primary/10 text-primary flex items-center justify-center mb-3" :class="{ 'mx-auto': widget.style?.align === 'center' }">
                                    <span class="material-symbols-outlined">{{ widget.content?.icon || 'shield' }}</span>
                                </div>
                                <h4 class="font-bold mb-1">{{ widget.content?.title }}</h4>
                                <p class="text-sm text-on-surface-variant">{{ widget.content?.text }}</p>
                            </div>

                            <div v-else-if="widget.type === 'cards'" class="cms-grid gap-4" :style="{ ...gridColumns(widget.content?.columns), ...styleBox(widget.style) }">
                                <div v-for="(item, itemIndex) in widget.content?.items || []" :key="itemIndex" class="bg-white border border-outline-variant rounded-xl p-5">
                                    <div class="w-11 h-11 rounded-lg bg-primary/10 text-primary flex items-center justify-center mb-3">
                                        <span class="material-symbols-outlined">{{ item.icon || 'shield' }}</span>
                                    </div>
                                    <h4 class="font-bold mb-1">{{ item.title }}</h4>
                                    <p class="text-sm text-on-surface-variant">{{ item.text }}</p>
                                </div>
                            </div>

                            <div v-else-if="widget.type === 'stats'" class="cms-grid gap-6 text-center" :style="{ ...gridColumns((widget.content?.items || []).length || 3), ...styleBox(widget.style) }">
                                <div v-for="(item, itemIndex) in widget.content?.items || []" :key="itemIndex">
                                    <p class="text-3xl font-extrabold text-primary">{{ item.value }}</p>
                                    <p class="text-xs text-on-surface-variant uppercase tracking-wide mt-1">{{ item.label }}</p>
                                </div>
                            </div>

                            <div v-else-if="widget.type === 'accordion'" :style="styleBox(widget.style)">
                                <details v-for="(item, itemIndex) in widget.content?.items || []" :key="itemIndex" class="border border-outline-variant rounded-lg" :class="{ 'mt-2': itemIndex }" :open="itemIndex === 0">
                                    <summary class="cursor-pointer px-4 py-3 font-semibold text-sm">{{ item.question }}</summary>
                                    <div class="px-4 pb-3 text-sm text-on-surface-variant">{{ item.answer }}</div>
                                </details>
                            </div>

                            <div v-else-if="widget.type === 'cta'" :class="[alignClass(widget.style), 'rounded-xl p-8']" :style="styleBox(widget.style)">
                                <h3 class="text-2xl font-bold mb-2">{{ widget.content?.title }}</h3>
                                <p class="opacity-90 mb-5">{{ widget.content?.text }}</p>
                                <a :href="widget.content?.href || '#'" class="inline-block bg-white text-primary px-6 py-3 rounded-lg font-bold text-sm">{{ widget.content?.button || 'Action' }}</a>
                            </div>

                            <div v-else-if="widget.type === 'html'" v-html="widget.content?.html" />
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </template>

    <div v-if="pbEditing" data-pb-ui class="max-w-container-max-width mx-auto px-4 md:px-margin-desktop py-6 flex flex-col sm:flex-row gap-2">
        <button class="flex-1 border-2 border-dashed border-outline-variant rounded-xl py-3 text-sm font-bold text-on-surface-variant hover:border-primary hover:text-primary flex items-center justify-center gap-2" type="button" @click="postToBuilder('addSection')">
            <span class="material-symbols-outlined">add</span>
            Section vide
        </button>
        <button class="flex-1 border-2 border-primary/30 bg-primary/5 rounded-xl py-3 text-sm font-bold text-primary hover:bg-primary/10 flex items-center justify-center gap-2" type="button" @click="postToBuilder('openLibrary')">
            <span class="material-symbols-outlined">dashboard</span>
            Bibliothèque
        </button>
    </div>

    <PageBuilderBridge v-if="builderPreview" />
</template>
