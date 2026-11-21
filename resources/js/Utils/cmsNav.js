// Rubriques du studio de contenu (menu du CMS) et permission requise pour chacune.
// Partagé par le menu du studio et par le lien « Studio de contenu » du back-office, qui ouvre
// la première rubrique autorisée (un compte peut avoir accès aux pages sans la vue d'ensemble).
export const CMS_NAV_ITEMS = [
    { key: 'dashboard', label: "Vue d'ensemble CMS", icon: 'dashboard', href: 'admin.cms.dashboard', permission: 'cms.dashboard' },
    { key: 'actualites', label: 'Actualités', icon: 'newspaper', href: 'admin.cms.actualites', permission: 'cms.articles' },
    { key: 'pages', label: 'Pages', icon: 'description', href: 'admin.cms.pages', permission: 'cms.pages' },
    { key: 'page-builder', label: 'Éditeur de pages', icon: 'dashboard_customize', href: 'admin.cms.page_builder', permission: 'cms.pages' },
    { key: 'media', label: 'Médiathèque', icon: 'perm_media', href: 'admin.cms.media', permission: 'cms.media' },
    { key: 'menus', label: 'Menus du site', icon: 'menu', href: 'admin.cms.menus', permission: 'cms.menus' },
    { key: 'identity', label: 'Identité visuelle', icon: 'palette', href: 'admin.cms.identity', permission: 'cms.footer' },
    { key: 'footer', label: 'Pied de page', icon: 'call_to_action', href: 'admin.cms.footer', permission: 'cms.footer' },
    { key: 'legal', label: 'Mentions légales', icon: 'gavel', href: 'admin.cms.legal', permission: 'cms.legal' },
    { key: 'banniere', label: "Bannière d'accueil", icon: 'view_carousel', href: 'admin.cms.banniere', permission: 'cms.banniere' },
    { key: 'chiffres-cles', label: 'Chiffres clés', icon: 'monitoring', href: 'admin.cms.chiffres_cles', permission: 'cms.chiffres' },
    { key: 'faq', label: 'FAQ', icon: 'quiz', href: 'admin.cms.faq', permission: 'cms.faq' },
    { key: 'ressources', label: 'Ressources', icon: 'folder_open', href: 'admin.cms.ressources', permission: 'cms.ressources' },
    { key: 'partenaires', label: 'Partenaires & centres', icon: 'handshake', href: 'admin.cms.partenaires', permission: 'cms.partenaires' },
    { key: 'contacts', label: 'Messages contact', icon: 'contact_mail', href: 'admin.cms.contacts', permission: 'cms.contacts', badge: 'cmsNewContactCount' },
    { key: 'newsletter', label: 'Newsletter', icon: 'mark_email_read', href: 'admin.cms.newsletter', permission: 'cms.newsletter', badge: 'cmsActiveNewsletterCount' },
];

/** Route de la première rubrique du studio que le compte peut ouvrir (null si aucune). */
export function firstCmsRoute(can) {
    return CMS_NAV_ITEMS.find((item) => can(item.permission))?.href ?? null;
}
