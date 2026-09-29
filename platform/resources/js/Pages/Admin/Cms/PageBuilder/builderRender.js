// Moteur de rendu du Page Builder (génération HTML, styles, previews système).
// Extrait de Index.vue. Fabrique recevant un accès aux données système (getSystemData).
import { PALETTE, WIDGETS } from './builderConstants';

export function createBuilderRender(getSystemData = () => ({})) {
function colorValue(token) {
    return PALETTE[token] ?? token ?? 'transparent';
}

function styleBox(style = {}) {
    const padding = style.padding ?? {};
    const margin = style.margin ?? {};
    const styles = {};
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

function sectionStyle(section) {
    return styleBox(section.settings);
}

function columnStyle(column) {
    return {
        flexBasis: `${column.width}%`,
        maxWidth: `${column.width}%`,
        ...styleBox(column.settings),
    };
}

function alignClass(style = {}) {
    return style.align === 'center' ? 'text-center' : style.align === 'right' ? 'text-right' : 'text-left';
}

function animationClass(style = {}) {
    return style.animation && style.animation !== 'none' ? `cms-anim-${style.animation}` : '';
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

function faqMeta(category) {
    return {
        Prestations: { icon: 'receipt_long', className: 'text-primary bg-primary/10' },
        Remboursements: { icon: 'account_balance_wallet', className: 'text-on-background bg-surface-container-high' },
        Enrôlement: { icon: 'diversity_3', className: 'text-tertiary bg-tertiary/10' },
        'Compte assuré': { icon: 'manage_accounts', className: 'text-secondary bg-secondary/10' },
    }[category] || { icon: 'help', className: 'text-primary bg-primary/10' };
}

function renderWidgetHtml(widget) {
    if (widget.style?.hidden) return '<div class="text-xs text-tertiary border border-dashed border-tertiary/50 rounded-lg p-3">Widget masqué sur le site public</div>';

    const style = widget.style ?? {};
    const content = widget.content ?? {};
    const align = alignClass(style);
    const box = styleBox(style);

    if (widget.type === 'heading') {
        const tag = content.tag || 'h2';
        return `<${tag} class="${align} ${style.size || 'text-3xl'} ${style.weight || 'font-bold'} leading-tight" style="${styleAttr(box)}">${escapeHtml(content.text || '')}</${tag}>`;
    }
    if (widget.type === 'elementor_heading') {
        return renderElementorHeadingHtml(widget);
    }
    if (widget.type.startsWith('elementor_')) {
        return renderElementorWidgetHtml(widget);
    }
    if (widget.type === 'text') {
        return `<div class="${align} ${style.size || 'text-base'} leading-relaxed" style="${styleAttr(box)}">${content.html || ''}</div>`;
    }
    if (widget.type === 'button') {
        return `<div class="${align}" style="${styleAttr(box)}"><a href="${escapeAttr(content.href || '#')}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg font-bold text-sm" style="background:${colorValue(style.bgColor || 'primary')};color:${colorValue(style.textColor || 'white')}">${content.icon ? `<span class="material-symbols-outlined text-[18px]">${escapeHtml(content.icon)}</span>` : ''}${escapeHtml(content.text || 'Bouton')}</a></div>`;
    }
    if (widget.type === 'image') {
        return `<div class="${align}" style="${styleAttr(box)}">${content.src ? `<img src="${escapeAttr(content.src)}" alt="${escapeAttr(content.alt || '')}" class="w-full ${content.ratio || 'aspect-video'} object-cover" style="border-radius:${style.radius || 0}px"/>` : '<div class="aspect-video bg-surface-container-low flex items-center justify-center">Image</div>'}</div>`;
    }
    if (widget.type === 'iconbox') {
        return `<div class="${align}" style="${styleAttr(box)}"><div class="w-12 h-12 rounded-xl bg-primary/10 text-primary flex items-center justify-center ${style.align === 'center' ? 'mx-auto' : ''} mb-3"><span class="material-symbols-outlined">${escapeHtml(content.icon || 'shield')}</span></div><h4 class="font-bold mb-1">${escapeHtml(content.title || '')}</h4><p class="text-sm text-on-surface-variant">${escapeHtml(content.text || '')}</p></div>`;
    }
    if (widget.type === 'cards') {
        const cols = Number(content.columns || 3);
        return `<div class="grid grid-cols-1 md:grid-cols-${cols} gap-4" style="${styleAttr(box)}">${(content.items || []).map((item) => `<div class="bg-white border border-outline-variant rounded-xl p-5"><div class="w-11 h-11 rounded-lg bg-primary/10 text-primary flex items-center justify-center mb-3"><span class="material-symbols-outlined">${escapeHtml(item.icon || 'shield')}</span></div><h4 class="font-bold mb-1">${escapeHtml(item.title || '')}</h4><p class="text-sm text-on-surface-variant">${escapeHtml(item.text || '')}</p></div>`).join('')}</div>`;
    }
    if (widget.type === 'stats') {
        return `<div class="grid grid-cols-1 md:grid-cols-${Math.min((content.items || []).length || 3, 4)} gap-6 text-center" style="${styleAttr(box)}">${(content.items || []).map((item) => `<div><p class="text-3xl font-extrabold text-primary">${escapeHtml(item.value || '')}</p><p class="text-xs text-on-surface-variant uppercase tracking-wide mt-1">${escapeHtml(item.label || '')}</p></div>`).join('')}</div>`;
    }
    if (widget.type === 'accordion') {
        return `<div style="${styleAttr(box)}">${(content.items || []).map((item, index) => `<details class="border border-outline-variant rounded-lg ${index ? 'mt-2' : ''}" ${index === 0 ? 'open' : ''}><summary class="cursor-pointer px-4 py-3 font-semibold text-sm">${escapeHtml(item.question || '')}</summary><div class="px-4 pb-3 text-sm text-on-surface-variant">${escapeHtml(item.answer || '')}</div></details>`).join('')}</div>`;
    }
    if (widget.type === 'cta') {
        return `<div class="${align} rounded-xl p-8" style="${styleAttr(box)}"><h3 class="text-2xl font-bold mb-2">${escapeHtml(content.title || '')}</h3><p class="opacity-90 mb-5">${escapeHtml(content.text || '')}</p><a href="${escapeAttr(content.href || '#')}" class="inline-block bg-white text-primary px-6 py-3 rounded-lg font-bold text-sm">${escapeHtml(content.button || 'Action')}</a></div>`;
    }
    if (widget.type.startsWith('home_')) {
        return renderHomePreview(widget);
    }
    if (widget.type.startsWith('about_')) {
        return renderAboutPreview(widget);
    }
    if (widget.type.startsWith('resources_') || widget.type.startsWith('news_') || widget.type.startsWith('contact_')) {
        return renderPublicSystemPreview(widget);
    }
    if (widget.type.startsWith('services_')) {
        return renderServicesPreview(widget);
    }
    if (['services_hero', 'services_stats', 'services_coverage', 'services_steps', 'services_partners', 'services_faq', 'services_cta'].includes(widget.type)) {
        return `<div class="rounded-xl border border-primary/20 bg-primary/5 p-6 text-center"><span class="material-symbols-outlined text-primary text-3xl">${escapeHtml(WIDGETS[widget.type].icon)}</span><h3 class="mt-2 font-bold text-primary">${escapeHtml(WIDGETS[widget.type].label)}</h3><p class="mt-1 text-xs text-on-surface-variant">Bloc dynamique alimenté par le CMS. Utilisez « Site public » pour voir le rendu réel.</p></div>`;
    }
    if (widget.type === 'html') {
        return content.html || '';
    }
    return '';
}

function renderElementorHeadingHtml(widget) {
    const tag = widget.content?.tag || 'h1';
    const href = widget.content?.link?.url || '';
    const html = widget.content?.title || '';
    const style = styleAttr(elementorHeadingStyle(widget));
    const className = `elementor-heading-widget ${animationClass(widget.advanced)} ${widget.advanced?.cssClasses || ''}`.trim();
    const id = widget.advanced?.elementId ? ` id="${escapeAttr(widget.advanced.elementId)}"` : '';
    const title = `<${tag} class="elementor-heading-title" style="${style}">${html}</${tag}>`;
    const contentHtml = href ? `<a href="${escapeAttr(href)}"${widget.content?.link?.is_external ? ' target="_blank"' : ''}${widget.content?.link?.nofollow ? ' rel="nofollow"' : ''}>${title}</a>` : title;

    return `<div${id} class="${escapeAttr(className)}">${contentHtml}</div>`;
}

function renderElementorWidgetHtml(widget) {
    const content = widget.content ?? {};
    const style = widget.style ?? {};
    const boxStyle = styleAttr(styleBox(style));
    const align = style.align === 'center' ? 'text-center' : style.align === 'right' ? 'text-right' : 'text-left';

    if (widget.type === 'elementor_text_editor') {
        return `<div class="elementor-text-editor ${align}" style="${boxStyle};color:${style.textColor || 'inherit'};font-family:${style.fontFamily || 'inherit'};font-size:${cssLength(style.fontSize, 'px', 'inherit')};font-weight:${style.fontWeight || 'inherit'};line-height:${style.lineHeight || 'inherit'}">${content.html || ''}</div>`;
    }

    if (widget.type === 'elementor_button') {
        const icon = content.icon ? `<span class="material-symbols-outlined text-[18px]">${escapeHtml(content.icon)}</span>` : '';
        const label = `<span>${escapeHtml(content.text || '')}</span>`;
        return `<div class="${align}" style="${boxStyle}"><a class="inline-flex items-center gap-2" href="${escapeAttr(content.link?.url || '#')}" style="background:${style.bgColor || '#9e001f'};color:${style.textColor || '#fff'};border-radius:${style.borderRadius || 0}px;padding:${spacingCss(style.padding)};font-family:${style.fontFamily || 'Inter'};font-size:${cssLength(style.fontSize, 'px', '14px')};font-weight:${style.fontWeight || '700'}">${content.iconPosition === 'after' ? label + icon : icon + label}</a></div>`;
    }

    if (widget.type === 'elementor_image') {
        const image = `<img src="${escapeAttr(content.src || '')}" alt="${escapeAttr(content.alt || '')}" style="width:${style.width || 100}%;border-radius:${style.borderRadius || 0}px;display:inline-block"/>`;
        return `<figure class="${align}" style="${boxStyle}">${content.link?.url ? `<a href="${escapeAttr(content.link.url)}">${image}</a>` : image}${content.caption ? `<figcaption class="text-sm text-on-surface-variant mt-2">${escapeHtml(content.caption)}</figcaption>` : ''}</figure>`;
    }

    if (widget.type === 'elementor_video') {
        return `<div style="${boxStyle};border-radius:${style.borderRadius || 0}px;overflow:hidden"><div class="aspect-video bg-on-background text-white flex items-center justify-center"><span class="material-symbols-outlined text-5xl">play_circle</span><span class="ml-2 text-sm">${escapeHtml(content.url || 'Video')}</span></div></div>`;
    }

    if (widget.type === 'elementor_icon') {
        return `<div class="${align}" style="${boxStyle}"><span class="material-symbols-outlined" style="font-size:${style.size || 48}px;color:${style.textColor || '#9e001f'}">${escapeHtml(content.icon || 'star')}</span></div>`;
    }

    if (widget.type === 'elementor_icon_box') {
        return `<div class="${align}" style="${boxStyle};color:${style.textColor || 'inherit'}"><span class="material-symbols-outlined block mb-3" style="font-size:42px;color:${style.iconColor || '#9e001f'}">${escapeHtml(content.icon || 'star')}</span><h3 class="font-bold text-xl mb-2">${escapeHtml(content.title || '')}</h3><p class="text-on-surface-variant">${escapeHtml(content.text || '')}</p></div>`;
    }

    if (widget.type === 'elementor_image_box') {
        return `<div class="${align}" style="${boxStyle}"><img src="${escapeAttr(content.src || '')}" alt="" style="width:${style.imageWidth || 100}%;border-radius:${style.borderRadius || 0}px;display:inline-block" /><h3 class="font-bold text-xl mt-4 mb-2">${escapeHtml(content.title || '')}</h3><p class="text-on-surface-variant">${escapeHtml(content.text || '')}</p></div>`;
    }

    if (widget.type === 'elementor_accordion') {
        return `<div style="${boxStyle}">${(content.items || []).map((item, index) => `<details class="border border-outline-variant rounded-lg bg-white ${index ? 'mt-2' : ''}" ${index === 0 ? 'open' : ''}><summary class="cursor-pointer px-4 py-3 font-bold" style="color:${style.titleColor || '#1b1c1c'}">${escapeHtml(item.title || '')}</summary><div class="px-4 pb-3 text-sm" style="color:${style.textColor || '#5c403f'}">${escapeHtml(item.content || '')}</div></details>`).join('')}</div>`;
    }

    if (widget.type === 'elementor_tabs') {
        const items = content.items || [];
        return `<div style="${boxStyle}"><div class="flex flex-wrap gap-2 border-b border-outline-variant">${items.map((item, index) => `<span class="px-4 py-2 font-bold ${index === 0 ? 'text-primary border-b-2 border-primary' : ''}" style="color:${index === 0 ? '' : (style.titleColor || '#1b1c1c')}">${escapeHtml(item.title || '')}</span>`).join('')}</div><div class="pt-4 text-on-surface-variant">${escapeHtml(items[0]?.content || '')}</div></div>`;
    }

    if (widget.type === 'elementor_carousel') {
        return `<div class="flex overflow-x-auto gap-4" style="${boxStyle};gap:${style.gap || 24}px">${(content.items || []).map((item) => `<article class="min-w-[260px] bg-white border border-outline-variant rounded-xl overflow-hidden"><img src="${escapeAttr(item.image || '/images/CAMA_1.jfif')}" class="h-36 w-full object-cover"/><div class="p-4"><h3 class="font-bold">${escapeHtml(item.title || '')}</h3><p class="text-sm text-on-surface-variant">${escapeHtml(item.text || '')}</p></div></article>`).join('')}</div>`;
    }

    if (widget.type === 'elementor_container') {
        return `<div style="${boxStyle};display:flex;flex-direction:${content.direction || 'column'};gap:${content.gap || 16}px;border:1px dashed #e5bdbb;border-radius:${style.borderRadius || 0}px"><span class="text-xs text-on-surface-variant">Container Elementor (${escapeHtml(content.direction || 'column')})</span></div>`;
    }

    if (widget.type === 'elementor_form') {
        return `<form class="bg-white border border-outline-variant rounded-xl p-5 space-y-3" style="${boxStyle}"><h3 class="font-bold">${escapeHtml(content.title || '')}</h3>${(content.fields || []).map((field) => `<label class="block text-sm font-semibold">${escapeHtml(field.label || '')}<input class="mt-1 w-full rounded-lg border border-outline-variant px-3 py-2" type="${escapeAttr(field.type || 'text')}"/></label>`).join('')}<button class="bg-primary text-on-primary rounded-lg px-5 py-2 font-bold" type="button">${escapeHtml(content.button || 'Envoyer')}</button></form>`;
    }

    if (widget.type === 'elementor_posts' || widget.type === 'elementor_loop_grid') {
        const count = Number(content.count || 3);
        const columns = Math.min(Number(style.columns || 3), 4);
        const title = content.title || (widget.type === 'elementor_loop_grid' ? 'Loop Grid' : 'Articles récents');
        const articles = (getSystemData()?.articles ?? getSystemData()?.latestArticles ?? []).slice(0, count);
        const cards = articles.length
            ? articles.map((article) => {
                const img = escapeAttr(article.image_src || article.imageSrc || '/images/CAMA_8.jfif');
                const href = escapeAttr(article.href || (article.slug ? `/actualites/${article.slug}` : '/actualites'));
                return `<article class="bg-white border border-outline-variant rounded-xl overflow-hidden shadow-sm"><img src="${img}" class="h-36 w-full object-cover" alt=""/><div class="p-4"><span class="text-[10px] uppercase font-bold text-primary">${escapeHtml(article.category || 'Actualité')}</span><h4 class="font-bold mt-1">${escapeHtml(article.title || '')}</h4><p class="text-sm text-on-surface-variant mt-1 line-clamp-2">${escapeHtml(article.excerpt || '')}</p><a href="${href}" class="text-primary text-xs font-bold mt-2 inline-block">Lire la suite</a></div></article>`;
            }).join('')
            : Array.from({ length: count }).map((_, index) => `<article class="bg-white border border-outline-variant rounded-xl p-4 border-dashed"><span class="text-xs text-primary font-bold">CMS</span><h4 class="font-bold mt-1">Article ${index + 1}</h4><p class="text-sm text-on-surface-variant">Aucun article publié</p></article>`).join('');
        const titleHtml = widget.type === 'elementor_posts' ? `<h3 class="font-bold text-xl mb-4">${escapeHtml(title)}</h3>` : '';
        return `<div style="${boxStyle}">${titleHtml}<div class="grid grid-cols-1 md:grid-cols-${columns} gap-4">${cards}</div></div>`;
    }

    if (widget.type === 'elementor_menu') {
        return `<nav class="flex ${content.layout === 'vertical' ? 'flex-col' : 'flex-row flex-wrap'} justify-${style.align || 'center'}" style="${boxStyle};gap:${style.gap || 24}px;color:${style.textColor || '#1b1c1c'}"><span>Accueil</span><span>Services</span><span>Actualités</span><span>Contact</span></nav>`;
    }

    return `<div class="rounded-xl border border-outline-variant p-4 text-sm text-on-surface-variant">${escapeHtml(WIDGETS[widget.type]?.label || widget.type)}</div>`;
}

function renderHomePreview(widget) {
    const content = widget.content ?? {};

    if (widget.type === 'home_hero') {
        return `<section class="relative h-[480px] overflow-hidden bg-on-background"><div class="absolute inset-0 bg-cover bg-center" style="background-image:linear-gradient(90deg,rgba(0,0,0,.82),rgba(0,0,0,.38)),url('/images/CAMA_8.jfif')"></div><div class="relative z-10 h-full px-margin-desktop flex items-center"><div class="max-w-2xl text-white space-y-6"><span class="inline-flex items-center gap-2 bg-white/10 backdrop-blur-md border border-white/20 px-4 py-1.5 rounded-full font-label-md text-label-md uppercase tracking-wider"><span class="material-symbols-outlined text-[18px]">${escapeHtml(content.badgeIcon || 'health_and_safety')}</span>${escapeHtml(content.badgeText || 'CAMA Burkina Faso')}</span><h1 class="font-display-lg text-display-lg leading-tight">La santé de nos héros, notre priorité</h1><p class="font-body-lg text-body-lg text-white/90">Plateforme institutionnelle de la Caisse d’Assurance Maladie des Armées.</p><div class="flex flex-wrap gap-4"><span class="bg-primary text-on-primary px-7 py-3.5 rounded-lg font-bold">Commencer l’enrôlement</span><span class="bg-white/10 border border-white/25 px-7 py-3.5 rounded-lg font-bold">${escapeHtml(content.secondaryLabel || 'Espace assuré')}</span></div></div></div></section>`;
    }

    if (widget.type === 'home_pillars') {
        return `<section class="py-stack-lg bg-surface-container-lowest"><div class="max-w-container-max-width mx-auto px-margin-desktop"><div class="text-center mb-12"><span class="text-primary font-label-md uppercase tracking-widest">Nos Piliers</span><h2 class="font-headline-lg text-headline-lg mt-2">${escapeHtml(content.title || 'Nos Piliers')}</h2></div><div class="grid grid-cols-1 md:grid-cols-3 gap-gutter">${(content.items || []).map((item) => `<article class="bg-white rounded-xl border border-outline-variant p-8 shadow-sm"><div class="w-14 h-14 rounded-xl bg-primary/10 text-primary flex items-center justify-center mb-5"><span class="material-symbols-outlined text-3xl">${escapeHtml(item.icon || 'shield')}</span></div><h3 class="font-title-lg text-title-lg mb-3">${escapeHtml(item.title || '')}</h3><p class="text-on-surface-variant font-body-md">${escapeHtml(item.text || '')}</p></article>`).join('')}</div></div></section>`;
    }

    if (widget.type === 'home_key_figures') {
        return `<section class="py-stack-lg bg-surface-container"><div class="max-w-container-max-width mx-auto px-margin-desktop text-center"><span class="text-primary font-label-md uppercase tracking-widest">${escapeHtml(content.eyebrow || 'En Chiffres')}</span><h2 class="font-headline-lg text-headline-lg mt-2 mb-10">${escapeHtml(content.title || "La CAMA aujourd'hui")}</h2><div class="grid grid-cols-2 md:grid-cols-4 gap-4">${['80%|Couverture moyenne|health_and_safety','5.5%|Taux de cotisation|payments','6 mois|Ayants droit|family_restroom','27 ans|Enfant à charge|school'].map((raw, index) => { const [value, label, icon] = raw.split('|'); return `<div class="${serviceTone(index === 1 ? 'secondary' : index === 2 ? 'tertiary' : index === 3 ? 'dark' : 'primary')} rounded-xl p-6 shadow-sm"><span class="material-symbols-outlined text-3xl">${icon}</span><p class="text-3xl font-bold mt-2">${value}</p><p class="text-caption uppercase tracking-wide opacity-90">${label}</p></div>`; }).join('')}</div></div></section>`;
    }

    if (widget.type === 'home_latest_articles') {
        return `<section class="py-stack-lg bg-surface-container-low"><div class="max-w-container-max-width mx-auto px-margin-desktop"><div class="flex justify-between items-end mb-12 gap-4"><div><span class="text-primary font-label-md uppercase tracking-widest">${escapeHtml(content.eyebrow || 'Informations')}</span><h2 class="font-headline-lg text-headline-lg mt-2">${escapeHtml(content.title || 'Dernières Actualités')}</h2></div><span class="text-primary font-bold">${escapeHtml(content.linkLabel || 'Voir tout le flux')}</span></div><div class="grid grid-cols-1 md:grid-cols-3 gap-gutter">${[1, 2, 3].map((index) => `<article class="bg-white rounded-xl border border-outline-variant overflow-hidden shadow-sm"><img src="/images/CAMA_${index === 1 ? '1' : index === 2 ? '6' : '4'}.jfif" class="h-44 w-full object-cover"/><div class="p-5"><span class="text-xs uppercase text-primary font-bold">Actualité</span><h3 class="font-title-lg text-title-lg mt-2">Article CMS ${index}</h3><p class="text-sm text-on-surface-variant mt-2">Aperçu des dernières actualités publiées.</p></div></article>`).join('')}</div></div></section>`;
    }

    return '';
}

function renderAboutPreview(widget) {
    const content = widget.content ?? {};

    if (widget.type === 'about_hero') {
        return `<section class="relative h-[420px] overflow-hidden flex items-center"><div class="absolute inset-0 bg-cover bg-center" style="background-image:linear-gradient(rgba(27,28,28,.62),rgba(27,28,28,.38)),url('/images/CAMA_3.3.jfif')"></div><div class="w-full px-6 md:px-16 relative z-10"><div class="text-white space-y-6 max-w-2xl mr-auto"><span class="inline-block bg-black/40 backdrop-blur-md border border-white/25 px-4 py-1.5 rounded-full font-label-md text-label-md uppercase tracking-wider">${escapeHtml(content.badge || '')}</span><h1 class="font-display-lg text-display-lg leading-tight">${escapeHtml(content.title || '')}</h1><p class="font-body-lg text-body-lg text-white/90 max-w-lg">${escapeHtml(content.text || '')}</p></div></div></section>`;
    }

    if (widget.type === 'about_director') {
        return `<section class="py-stack-lg bg-surface-container-lowest"><div class="max-w-container-max-width mx-auto px-margin-desktop"><div class="mb-12"><span class="text-primary font-label-md uppercase tracking-widest">${escapeHtml(content.eyebrow || '')}</span><h2 class="font-headline-lg text-headline-lg mt-2">${escapeHtml(content.title || '')}</h2></div><div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center"><div class="lg:col-span-5"><div class="aspect-[4/5] rounded-xl overflow-hidden shadow-lg border border-outline-variant relative"><img src="${escapeAttr(content.imageSrc || '/images/directeur.jpg')}" class="w-full h-full object-cover"/><div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent"></div><div class="absolute bottom-0 left-0 right-0 p-5 text-white"><span class="inline-flex bg-secondary/90 px-3 py-1 rounded-full text-xs font-bold uppercase mb-2">${escapeHtml(content.badge || '')}</span><p class="font-title-lg text-title-lg font-bold">${escapeHtml(content.name || '')}</p><p class="text-white/80">${escapeHtml(content.role || '')}</p></div></div></div><div class="lg:col-span-7 space-y-6"><p class="text-on-surface-variant italic border-l-4 border-secondary pl-6">« ${escapeHtml(content.quote || '')} »</p><p class="text-on-surface-variant">${escapeHtml(content.text || '')}</p><div class="grid grid-cols-2 gap-4">${(content.stats || []).map((stat) => `<div class="flex items-center gap-3 p-4 bg-white rounded-xl border border-outline-variant"><div class="w-10 h-10 rounded-lg ${serviceTone(stat.tone)} flex items-center justify-center"><span class="material-symbols-outlined">${escapeHtml(stat.icon || '')}</span></div><div><p class="font-bold ${serviceTextTone(stat.tone)}">${escapeHtml(stat.value || '')}</p><p class="text-xs uppercase text-on-surface-variant">${escapeHtml(stat.label || '')}</p></div></div>`).join('')}</div><span class="inline-flex items-center gap-2 text-primary font-bold">${escapeHtml(content.linkLabel || '')} <span class="material-symbols-outlined">arrow_forward</span></span></div></div></div></section>`;
    }

    if (widget.type === 'about_timeline') {
        return `<section class="py-stack-lg bg-surface-container-low"><div class="max-w-container-max-width mx-auto px-4 md:px-margin-desktop"><div class="text-center mb-12"><span class="text-primary font-label-md uppercase tracking-widest">${escapeHtml(content.eyebrow || '')}</span><h2 class="font-headline-lg text-headline-lg mt-2">${escapeHtml(content.title || '')}</h2><p class="text-on-surface-variant mt-3">${escapeHtml(content.text || '')}</p></div><div class="grid grid-cols-1 md:grid-cols-3 gap-gutter">${(content.items || []).map((item) => `<article class="bg-white p-8 rounded-xl border border-outline-variant shadow-sm"><div class="w-14 h-14 rounded-full ${serviceTone(item.tone)} flex items-center justify-center mb-6"><span class="material-symbols-outlined">${escapeHtml(item.icon || '')}</span></div><span class="text-xs uppercase font-bold ${serviceTextTone(item.tone)}">${escapeHtml(item.date || '')}</span><h3 class="font-title-lg text-title-lg mt-2 mb-2">${escapeHtml(item.title || '')}</h3><p class="text-on-surface-variant">${escapeHtml(item.text || '')}</p></article>`).join('')}</div><div class="flex justify-center my-2"><span class="material-symbols-outlined text-outline-variant text-3xl">arrow_downward</span></div><div class="text-on-primary p-12 rounded-xl shadow-xl overflow-hidden relative bg-about-today bg-cover bg-center"><span class="inline-flex items-center gap-1.5 bg-white/15 px-3 py-1 rounded-full text-xs font-bold uppercase mb-4"><span class="material-symbols-outlined text-[14px]">today</span>${escapeHtml(content.todayBadge || '')}</span><h3 class="font-headline-lg text-headline-lg mb-3">${escapeHtml(content.todayTitle || '')}</h3><p class="font-body-lg opacity-90">${escapeHtml(content.todayText || '')}</p></div></div></section>`;
    }

    if (widget.type === 'about_missions') {
        return `<section class="py-stack-lg bg-surface-container"><div class="max-w-container-max-width mx-auto px-4 md:px-margin-desktop"><div class="flex flex-col md:flex-row justify-between items-end mb-12 gap-6"><div class="max-w-xl"><h2 class="font-headline-lg text-headline-lg mb-2">${escapeHtml(content.title || '')}</h2><p class="text-on-surface-variant">${escapeHtml(content.text || '')}</p></div><span class="text-primary font-bold">${escapeHtml(content.linkLabel || '')}</span></div><div class="grid grid-cols-1 lg:grid-cols-3 gap-stack-lg">${(content.items || []).map((item) => `<article class="p-8 bg-white border border-outline-variant rounded-lg shadow-sm"><div class="w-12 h-12 rounded-lg ${serviceSoftTone(item.tone)} flex items-center justify-center mb-6"><span class="material-symbols-outlined ${serviceTextTone(item.tone)}">${escapeHtml(item.icon || '')}</span></div><h4 class="font-title-lg text-title-lg mb-4">${escapeHtml(item.title || '')}</h4><ul class="space-y-3 text-on-surface-variant">${(item.items || []).map((line) => `<li class="flex gap-2"><span>•</span>${escapeHtml(line)}</li>`).join('')}</ul></article>`).join('')}</div></div></section>`;
    }

    if (widget.type === 'about_cta') {
        return `<section class="py-stack-lg px-4 md:px-margin-desktop mb-stack-lg"><div class="max-w-container-max-width mx-auto bg-surface-container p-stack-lg rounded-3xl flex flex-col md:flex-row items-center justify-between gap-stack-lg border border-outline-variant relative"><div class="relative z-10"><h3 class="font-headline-lg text-headline-lg mb-2">${escapeHtml(content.title || '')}</h3><p class="text-on-surface-variant">${escapeHtml(content.text || '')}</p></div><div class="flex gap-4 relative z-10"><span class="bg-on-background text-surface px-8 py-4 rounded-xl font-bold">${escapeHtml(content.primaryLabel || '')}</span><span class="bg-secondary text-on-secondary px-8 py-4 rounded-xl font-bold inline-flex items-center gap-2"><span class="material-symbols-outlined">call</span>${escapeHtml(content.secondaryLabel || '')}</span></div></div></section>`;
    }

    return '';
}

function renderPublicSystemPreview(widget) {
    const content = widget.content ?? {};

    if (widget.type === 'resources_hero') {
        return `<section class="relative py-20 text-white bg-cover bg-center" style="background-image:linear-gradient(rgba(27,28,28,.82),rgba(27,28,28,.7)),url('/images/CAMA_6.jfif')"><div class="max-w-container-max-width mx-auto px-margin-desktop text-center"><span class="inline-flex items-center gap-2 bg-white/10 border border-white/20 px-4 py-1.5 rounded-full uppercase text-sm font-bold"><span class="material-symbols-outlined text-[18px]">${escapeHtml(content.badgeIcon || 'folder_open')}</span>${escapeHtml(content.badge || '')}</span><h1 class="font-headline-lg text-headline-lg mt-6 mb-4">${escapeHtml(content.title || '')}</h1><p class="text-white/85 max-w-2xl mx-auto">${escapeHtml(content.text || '')}</p></div></section>`;
    }

    if (widget.type === 'resources_listing') {
        return `<section class="py-stack-lg bg-surface-container-low"><div class="max-w-container-max-width mx-auto px-margin-desktop"><div class="flex flex-wrap gap-2 mb-8 justify-center"><span class="px-4 py-2 rounded-full bg-primary text-on-primary text-sm font-semibold">Toutes</span><span class="px-4 py-2 rounded-full bg-white border border-outline-variant text-sm font-semibold">Formulaires</span><span class="px-4 py-2 rounded-full bg-white border border-outline-variant text-sm font-semibold">Guides</span></div><div class="grid grid-cols-1 md:grid-cols-3 gap-5">${[1, 2, 3].map(index => `<article class="bg-white rounded-xl border border-outline-variant p-5 shadow-sm"><div class="flex gap-3 mb-3"><span class="w-11 h-11 rounded-lg bg-primary/10 text-primary flex items-center justify-center"><span class="material-symbols-outlined">picture_as_pdf</span></span><div><span class="text-[11px] uppercase text-primary font-bold">CMS</span><h3 class="font-bold">Document ${index}</h3></div></div><p class="text-sm text-on-surface-variant mb-4">Aperçu d’une ressource publiée.</p><span class="inline-flex items-center gap-1.5 bg-primary text-on-primary px-3.5 py-2 rounded-lg text-xs font-bold"><span class="material-symbols-outlined text-[16px]">download</span>${escapeHtml(content.downloadLabel || 'Télécharger')}</span></article>`).join('')}</div></div></section>`;
    }

    if (widget.type === 'news_hero') {
        return `<section class="relative py-20 text-white bg-cover bg-center" style="background-image:linear-gradient(rgba(27,28,28,.82),rgba(27,28,28,.7)),url('/images/CAMA_3.3.jfif')"><div class="max-w-container-max-width mx-auto px-margin-desktop text-center"><h1 class="font-headline-lg text-headline-lg mb-4">${escapeHtml(content.title || '')}</h1><p class="text-white/80 max-w-2xl mx-auto">${escapeHtml(content.text || '')}</p></div></section>`;
    }

    if (widget.type === 'news_filters') {
        return `<section class="py-6 border-b border-outline-variant bg-surface"><div class="max-w-container-max-width mx-auto px-margin-desktop flex flex-col md:flex-row md:items-center justify-between gap-4"><div class="flex flex-wrap gap-2"><span class="px-4 py-2 rounded-full bg-primary text-on-primary text-sm font-bold">Tout</span><span class="px-4 py-2 rounded-full bg-surface-container-high text-sm">Communiqué</span><span class="px-4 py-2 rounded-full bg-surface-container-high text-sm">Santé</span></div><div class="relative w-full md:w-80"><span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant">search</span><input class="w-full pl-10 pr-4 py-2 bg-white border border-outline rounded-lg" placeholder="${escapeAttr(content.searchPlaceholder || '')}"/></div></div></section>`;
    }

    if (widget.type === 'news_listing') {
        return `<section class="py-12 bg-background"><div class="max-w-container-max-width mx-auto px-margin-desktop"><article class="max-w-3xl bg-white border border-outline-variant rounded-xl overflow-hidden shadow-sm mb-8"><img src="/images/CAMA_8.jfif" class="h-64 w-full object-cover"/><div class="p-6"><span class="bg-primary text-on-primary px-3 py-1 rounded text-xs font-bold uppercase">Actualité</span><h2 class="font-headline-md text-headline-md my-4">Article mis en avant</h2><p class="text-on-surface-variant mb-5">Aperçu dynamique des articles publiés depuis le CMS.</p><span class="text-primary font-bold">${escapeHtml(content.readLabel || 'Lire la suite')}</span></div></article><div class="grid grid-cols-1 md:grid-cols-3 gap-gutter">${[1, 2, 3].map(i => `<article class="bg-white border border-outline-variant rounded-xl p-5"><h3 class="font-bold">Article ${i}</h3><p class="text-sm text-on-surface-variant mt-2">Résumé de l’article.</p></article>`).join('')}</div></div></section>`;
    }

    if (widget.type === 'news_newsletter') {
        return `<section class="py-12 bg-surface-container-low border-t border-outline-variant"><div class="max-w-container-max-width mx-auto px-margin-desktop"><div class="bg-white p-8 rounded-2xl flex flex-col md:flex-row items-center justify-between gap-8 border border-outline-variant"><div><h2 class="font-headline-md text-headline-md mb-2">${escapeHtml(content.title || '')}</h2><p class="text-on-surface-variant">${escapeHtml(content.text || '')}</p></div><div class="flex gap-3"><input class="px-6 py-3 border border-outline rounded-lg" placeholder="${escapeAttr(content.placeholder || '')}"/><span class="bg-primary text-on-primary px-8 py-3 rounded-lg font-bold">${escapeHtml(content.buttonLabel || '')}</span></div></div></div></section>`;
    }

    if (widget.type === 'contact_hero') {
        return `<section class="relative py-20 text-white bg-cover bg-center" style="background-image:linear-gradient(rgba(27,28,28,.82),rgba(27,28,28,.7)),url('/images/CAMA_1.jfif')"><div class="max-w-container-max-width mx-auto px-margin-desktop text-center"><p class="text-white/70 text-xs font-bold uppercase tracking-widest mb-3">${escapeHtml(content.eyebrow || '')}</p><h1 class="font-headline-lg text-headline-lg mb-4">${escapeHtml(content.title || '')}</h1><p class="text-white/80 max-w-2xl mx-auto">${escapeHtml(content.text || '')}</p></div></section>`;
    }

    if (widget.type === 'contact_form') {
        return `<section class="py-stack-lg"><div class="max-w-2xl mx-auto bg-white p-8 rounded-xl border border-outline-variant shadow-sm"><h2 class="font-headline-md text-xl mb-1">${escapeHtml(content.title || '')}</h2><p class="text-on-surface-variant text-sm mb-6">${escapeHtml(content.text || '')}</p><div class="grid grid-cols-1 md:grid-cols-2 gap-5"><input class="md:col-span-2 px-4 py-3 border border-outline-variant rounded-lg" placeholder="Nom complet"/><input class="px-4 py-3 border border-outline-variant rounded-lg" placeholder="E-mail"/><input class="px-4 py-3 border border-outline-variant rounded-lg" placeholder="Téléphone"/><textarea class="md:col-span-2 px-4 py-3 border border-outline-variant rounded-lg" rows="4" placeholder="Message"></textarea><span class="bg-primary text-on-primary px-8 py-3 rounded-lg font-bold w-fit">${escapeHtml(content.buttonLabel || '')}</span></div></div></section>`;
    }

    if (widget.type === 'contact_map') {
        return `<section class="bg-surface-container-low border-t border-outline-variant py-stack-lg"><div class="max-w-container-max-width mx-auto px-margin-desktop"><h2 class="font-headline-md text-2xl mb-1">${escapeHtml(content.title || '')}</h2><p class="text-sm text-on-surface-variant mb-6">${escapeHtml(content.text || '')}</p><div class="grid grid-cols-1 lg:grid-cols-12 gap-5"><div class="lg:col-span-4 space-y-3"><input class="w-full px-4 py-3 border border-outline-variant rounded-lg" placeholder="${escapeAttr(content.searchPlaceholder || '')}"/><div class="bg-white p-4 rounded-xl border border-primary"><p class="font-bold">Siège CAMA Ouagadougou</p><p class="text-xs text-on-surface-variant">Ex-État-Major Général des Armées</p></div><div class="bg-white p-4 rounded-xl border border-outline-variant"><p class="font-bold">Antenne Bobo-Dioulasso</p></div></div><div class="lg:col-span-8 bg-white rounded-xl border border-outline-variant min-h-[380px] flex items-center justify-center text-on-surface-variant"><span class="material-symbols-outlined text-5xl mr-2">map</span> Google Maps</div></div></div></section>`;
    }

    return '';
}

function renderServicesPreview(widget) {
    const content = widget.content ?? {};

    if (widget.type === 'services_hero') {
        return `<section class="relative h-[420px] overflow-hidden flex items-center rounded-none"><div class="absolute inset-0 bg-cover bg-center" style="background-image:linear-gradient(rgba(27,28,28,.72),rgba(27,28,28,.5)),url('/images/CAMA_8.jfif')"></div><div class="w-full px-6 md:px-16 relative z-10"><div class="text-white space-y-6 max-w-2xl mr-auto"><span class="inline-block bg-black/40 backdrop-blur-md border border-white/25 text-white px-4 py-1.5 rounded-full font-label-md text-label-md uppercase tracking-wider shadow-sm">${escapeHtml(content.badge || '')}</span><h1 class="font-headline-lg text-headline-lg md:text-[40px] md:leading-[1.15] leading-tight">${escapeHtml(content.title || '')}</h1><p class="font-body-lg text-body-lg text-white/90 max-w-lg">${escapeHtml(content.text || '')}</p><div class="flex flex-wrap gap-4 pt-2"><a class="bg-primary-container text-on-primary-container px-8 py-3.5 rounded-lg font-bold font-label-md text-label-md flex items-center gap-2 shadow-lg">${escapeHtml(content.primaryLabel || 'Voir le détail')} <span class="material-symbols-outlined">arrow_downward</span></a><a class="bg-white/10 backdrop-blur-md border border-white/30 text-white px-8 py-3.5 rounded-lg font-bold font-label-md text-label-md">${escapeHtml(content.secondaryLabel || 'Foire aux questions')}</a></div></div></div></section>`;
    }

    if (widget.type === 'services_stats') {
        return `<section class="bg-surface-container-lowest py-stack-lg border-b border-outline-variant"><div class="px-margin-desktop max-w-container-max-width mx-auto grid grid-cols-2 md:grid-cols-4 gap-4">${(content.items || []).map((item) => `<div class="group ${serviceTone(item.tone)} rounded-xl p-6 flex flex-col gap-1 shadow-sm"><span class="material-symbols-outlined text-3xl opacity-90">${escapeHtml(item.icon || '')}</span><span class="text-3xl font-headline-lg font-bold">${escapeHtml(item.value || '')}</span><span class="text-caption uppercase tracking-wide opacity-90">${escapeHtml(item.label || '')}</span></div>`).join('')}</div></section>`;
    }

    if (widget.type === 'services_coverage') {
        return `<section class="py-stack-lg bg-surface-container"><div class="max-w-container-max-width mx-auto px-margin-desktop"><div class="flex items-end justify-between gap-6 mb-12"><div><span class="text-primary font-label-md uppercase tracking-widest">${escapeHtml(content.eyebrow || '')}</span><h2 class="font-headline-md text-headline-md mt-2">${escapeHtml(content.title || '')}</h2></div><div class="hidden md:flex gap-2 shrink-0"><button class="w-11 h-11 rounded-full border border-outline-variant bg-white flex items-center justify-center"><span class="material-symbols-outlined">chevron_left</span></button><button class="w-11 h-11 rounded-full border border-outline-variant bg-white flex items-center justify-center"><span class="material-symbols-outlined">chevron_right</span></button></div></div></div><div class="relative"><div class="services-scroll no-scrollbar flex gap-gutter overflow-x-auto snap-x snap-mandatory scroll-smooth px-margin-desktop pb-2">${(content.items || []).map((item) => `<div class="snap-start shrink-0 w-[280px] sm:w-[320px] flex flex-col bg-white p-8 rounded-2xl border border-outline-variant shadow-sm"><div class="w-14 h-14 ${serviceSoftTone(item.tone)} rounded-xl flex items-center justify-center mb-6"><span class="material-symbols-outlined text-3xl">${escapeHtml(item.icon || '')}</span></div><h3 class="font-title-lg text-title-lg mb-3">${escapeHtml(item.title || '')}</h3><p class="text-on-surface-variant text-body-md mb-6 flex-grow">${escapeHtml(item.text || '')}</p><span class="inline-block w-fit ${serviceSoftTone(item.tone)} font-bold px-3 py-1 rounded-full text-label-md">${escapeHtml(item.rate || '')}</span></div>`).join('')}<div class="shrink-0 w-px"></div></div><div class="hidden md:block absolute top-0 bottom-2 left-0 w-12 bg-gradient-to-r from-surface-container to-transparent pointer-events-none"></div><div class="hidden md:block absolute top-0 bottom-2 right-0 w-12 bg-gradient-to-l from-surface-container to-transparent pointer-events-none"></div></div></section>`;
    }

    if (widget.type === 'services_steps') {
        return `<section class="py-stack-lg bg-surface-container-low"><div class="px-margin-desktop max-w-container-max-width mx-auto"><div class="text-center mb-12"><span class="text-primary font-label-md uppercase tracking-widest">${escapeHtml(content.eyebrow || '')}</span><h2 class="font-headline-md text-headline-md mt-2">${escapeHtml(content.title || '')}</h2></div><div class="grid grid-cols-1 md:grid-cols-4 gap-gutter relative"><div class="hidden md:block absolute top-8 left-[12.5%] right-[12.5%] h-0.5 bg-outline-variant"></div>${(content.items || []).map((item, index) => `<div class="relative bg-white p-6 rounded-xl border border-outline-variant shadow-sm text-center"><div class="w-12 h-12 mx-auto mb-4 rounded-full ${serviceTone(item.tone)} flex items-center justify-center font-bold text-lg relative z-10">${index + 1}</div><span class="material-symbols-outlined ${serviceTextTone(item.tone)} text-3xl mb-2 block">${escapeHtml(item.icon || '')}</span><h4 class="font-title-lg text-title-lg mb-2">${escapeHtml(item.title || '')}</h4><p class="text-on-surface-variant font-body-md text-body-md">${escapeHtml(item.text || '')}</p></div>`).join('')}</div></div></section>`;
    }

    if (widget.type === 'services_partners') {
        const partners = getSystemData()?.partners ?? [];
        return `<section class="py-stack-lg"><div class="px-margin-desktop max-w-container-max-width mx-auto"><div class="text-center mb-12"><span class="text-primary font-label-md uppercase tracking-widest">${escapeHtml(content.eyebrow || '')}</span><h2 class="font-headline-md text-headline-md mt-2">${escapeHtml(content.title || '')}</h2><p class="text-on-surface-variant font-body-md mt-2 max-w-2xl mx-auto">${escapeHtml(content.text || '')}</p></div>${partners.length ? `<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">${partners.map((partner) => `<article class="bg-white rounded-xl border border-outline-variant overflow-hidden shadow-sm flex flex-col"><div class="h-36 bg-surface-container-low bg-cover bg-center" style="background-image:url('${escapeAttr(partner.imageSrc || '/images/CAMA_1.jfif')}')"></div><div class="p-4 flex flex-col flex-1"><span class="inline-flex items-center gap-1 self-start text-[11px] font-semibold px-2 py-0.5 rounded-full mb-2 ${partner.type === 'Centre de santé' ? 'bg-secondary/10 text-secondary' : 'bg-tertiary/10 text-tertiary'}"><span class="material-symbols-outlined text-[14px]">${partner.type === 'Centre de santé' ? 'local_hospital' : 'handshake'}</span> ${escapeHtml(partner.type || '')}</span><h3 class="font-title-lg text-base text-on-surface leading-snug">${escapeHtml(partner.name || '')}</h3><p class="text-xs text-on-surface-variant mt-0.5 flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">place</span> ${escapeHtml(partner.city || '')}</p><p class="text-sm text-on-surface-variant mt-2 flex-1">${escapeHtml(partner.description || '')}</p><div class="flex flex-wrap gap-2 mt-4"><span class="inline-flex items-center gap-1.5 bg-primary text-on-primary px-3 py-2 rounded-lg text-xs font-bold"><span class="material-symbols-outlined text-[16px]">map</span> Google Maps</span><span class="inline-flex items-center gap-1.5 border border-outline text-on-surface px-3 py-2 rounded-lg text-xs font-bold"><span class="material-symbols-outlined text-[16px]">location_on</span> Nos antennes</span></div></div></article>`).join('')}</div>` : '<p class="text-center text-on-surface-variant py-10">Aucun partenaire publié pour le moment.</p>'}</div></section>`;
    }

    if (widget.type === 'services_faq') {
        const faqs = getSystemData()?.faqs ?? [];
        return `<section class="py-stack-lg bg-surface-container-lowest"><div class="px-margin-desktop max-w-container-max-width mx-auto"><div class="grid grid-cols-1 lg:grid-cols-12 gap-stack-lg"><div class="lg:col-span-4"><div><span class="text-primary font-label-md uppercase tracking-widest">${escapeHtml(content.eyebrow || '')}</span><h2 class="font-headline-lg text-headline-lg mt-2 mb-4">${escapeHtml(content.title || '')}</h2><p class="text-on-surface-variant font-body-md leading-relaxed mb-8">${escapeHtml(content.text || '')}</p><div class="bg-white p-6 rounded-xl border border-outline-variant shadow-sm flex items-start gap-4"><div class="w-11 h-11 shrink-0 rounded-full bg-primary/10 text-primary flex items-center justify-center"><span class="material-symbols-outlined">support_agent</span></div><div><p class="font-title-lg text-title-lg mb-1">${escapeHtml(content.cardTitle || '')}</p><p class="text-on-surface-variant text-body-md text-sm mb-3">${escapeHtml(content.cardText || '')}</p><span class="text-primary font-bold text-sm inline-flex items-center gap-1">${escapeHtml(content.linkLabel || 'Nous contacter')} <span class="material-symbols-outlined text-[16px]">arrow_forward</span></span></div></div></div></div><div class="lg:col-span-8 space-y-4">${faqs.length ? faqs.map((faq, index) => { const meta = faqMeta(faq.category); return `<details class="group bg-white rounded-xl border border-outline-variant overflow-hidden hover:border-primary/30 transition-colors" ${index === 0 ? 'open' : ''}><summary class="flex justify-between items-center gap-4 p-6 cursor-pointer hover:bg-surface-container-low transition-colors"><span class="flex items-center gap-4"><span class="material-symbols-outlined ${meta.className} p-2 rounded-lg shrink-0">${meta.icon}</span><span class="font-title-lg text-title-lg">${escapeHtml(faq.question || '')}</span></span><span class="material-symbols-outlined text-on-surface-variant shrink-0">expand_more</span></summary><div class="px-6 pb-6 pl-[4.5rem] text-on-surface-variant font-body-md border-t border-outline-variant pt-4">${escapeHtml(faq.answer || '')}</div></details>`; }).join('') : '<p class="text-on-surface-variant text-sm text-center py-8">Aucune question publiée pour le moment.</p>'}</div></div></div></section>`;
    }

    if (widget.type === 'services_cta') {
        return `<section class="py-stack-lg bg-primary"><div class="px-margin-desktop max-w-container-max-width mx-auto flex flex-col md:flex-row items-center justify-between gap-stack-lg text-on-primary"><div class="max-w-xl text-center md:text-left"><h3 class="font-headline-lg text-headline-lg mb-2">${escapeHtml(content.title || '')}</h3><p class="font-body-md text-body-md opacity-90">${escapeHtml(content.text || '')}</p></div><div class="flex gap-4 w-full md:w-auto"><span class="bg-white text-primary px-8 py-4 rounded-xl font-bold flex-1 md:flex-none text-center">${escapeHtml(content.primaryLabel || 'Nous contacter')}</span><span class="bg-white/10 backdrop-blur-md border border-white/30 text-white px-8 py-4 rounded-xl font-bold flex-1 md:flex-none flex items-center justify-center gap-2"><span class="material-symbols-outlined">call</span> ${escapeHtml(content.secondaryLabel || 'Urgence : 112')}</span></div></div></section>`;
    }

    return '';
}

function sectionHtml(section) {
    if (section.settings?.hidden) return '';
    const boxed = section.settings?.contentWidth !== 'full';
    return `<section style="${styleAttr(sectionStyle(section))}"><div class="${boxed ? 'max-w-[1100px] mx-auto' : ''} flex flex-wrap gap-6">${section.columns.map((column) => column.settings?.hidden ? '' : `<div style="flex:0 0 ${column.width}%;max-width:${column.width}%;${styleAttr(styleBox(column.settings))}">${column.widgets.map(renderWidgetHtml).join('')}</div>`).join('')}</div></section>`;
}

function fullHtml(sourceSections = []) {
    const header = `<header class="sticky top-0 z-20 bg-white border-b border-outline-variant"><div class="max-w-[1200px] mx-auto px-8 h-16 flex items-center justify-between"><div class="flex items-center gap-3"><img src="/images/logo_cama.png" class="h-10 w-10 object-contain"/><strong>CAMA</strong></div><nav class="hidden md:flex gap-6 text-sm font-semibold"><span>Accueil</span><span>À propos</span><span>Services</span><span>Ressources</span><span>Actualités</span><span>Contact</span></nav></div></header>`;
    const footer = `<footer class="bg-on-background text-white px-8 py-10"><div class="max-w-[1200px] mx-auto text-sm opacity-80">© CAMA - Caisse d'Assurance Maladie des Armées</div></footer>`;
    return `<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"/><meta name="viewport" content="width=device-width, initial-scale=1"/><script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"><\/script><link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&family=Montserrat:wght@100..900&family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/><style>body{font-family:Inter}.material-symbols-outlined{font-variation-settings:'FILL' 0,'wght' 400,'GRAD' 0,'opsz' 24}</style></head><body class="bg-background text-on-background">${header}${sourceSections.map(sectionHtml).join('')}${footer}</body></html>`;
}

function styleAttr(styles) {
    return Object.entries(styles)
        .map(([key, value]) => `${key.replace(/[A-Z]/g, (m) => `-${m.toLowerCase()}`)}:${value}`)
        .join(';');
}

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
}

function escapeAttr(value) {
    return escapeHtml(value).replace(/'/g, '&#39;');
}

    return { colorValue, styleBox, cssLength, spacingCss, elementorHeadingStyle, sectionStyle, columnStyle, alignClass, animationClass, serviceTone, serviceSoftTone, serviceTextTone, faqMeta, renderWidgetHtml, renderElementorHeadingHtml, renderElementorWidgetHtml, renderHomePreview, renderAboutPreview, renderPublicSystemPreview, renderServicesPreview, sectionHtml, fullHtml, styleAttr, escapeHtml, escapeAttr };
}
