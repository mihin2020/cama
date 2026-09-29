<script setup>
import CmsLayout from '@/Layouts/CmsLayout.vue';
import { useAdminPermissions } from '@/Composables/useAdminPermissions';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({
    stats: Object,
    activity: Array,
});

const { can } = useAdminPermissions();

const modules = [
    { key: 'actualites', route: 'admin.cms.actualites', icon: 'newspaper', title: 'Actualités', desc: 'Rédiger, publier, programmer et archiver les articles du site.', color: 'primary', permission: 'cms.articles' },
    { key: 'pages', route: 'admin.cms.pages', icon: 'description', title: 'Pages', desc: 'Créer, dupliquer, publier et organiser les pages du site (comme WordPress).', color: 'primary', permission: 'cms.pages' },
    { key: 'page-builder', route: 'admin.cms.page_builder', icon: 'dashboard_customize', title: 'Éditeur de pages', desc: 'Construction visuelle par blocs des pages institutionnelles (type Elementor).', color: 'secondary', permission: 'cms.pages' },
    { key: 'menus', route: 'admin.cms.menus', icon: 'menu', title: 'Menus du site', desc: 'Organiser les onglets du header : ajouter, réordonner, créer des sous-menus.', color: 'tertiary', permission: 'cms.menus' },
    { key: 'banniere', route: 'admin.cms.banniere', icon: 'view_carousel', title: "Bannière d'accueil", desc: 'Gérer les slides du carrousel principal de la page d\'accueil.', color: 'tertiary', permission: 'cms.banniere' },
    { key: 'chiffres-cles', route: 'admin.cms.chiffres_cles', icon: 'monitoring', title: 'Chiffres clés', desc: 'Mettre à jour les statistiques affichées sur la page d\'accueil.', color: 'primary', permission: 'cms.chiffres' },
    { key: 'faq', route: 'admin.cms.faq', icon: 'quiz', title: 'FAQ', desc: 'Gérer les questions fréquentes affichées sur le site public.', color: 'secondary', permission: 'cms.faq' },
    { key: 'ressources', route: 'admin.cms.ressources', icon: 'folder_open', title: 'Ressources', desc: 'Documents téléchargeables (formulaires, guides, attestations…) et catégories.', color: 'primary', permission: 'cms.ressources' },
    { key: 'partenaires', route: 'admin.cms.partenaires', icon: 'handshake', title: 'Partenaires & centres', desc: 'Partenaires et centres de santé affichés sur le site et la cartographie.', color: 'tertiary', permission: 'cms.partenaires' },
];

const visibleModules = computed(() => modules.filter((mod) => can(mod.permission)));
const canOpenPageBuilder = computed(() => can('cms.pages'));

const iconBg = (color) => {
    if (color === 'secondary') return 'bg-secondary/10 text-secondary';
    if (color === 'tertiary') return 'bg-tertiary/10 text-tertiary';
    return 'bg-primary/10 text-primary';
};
</script>

<template>
    <Head title="Studio de contenu" />

    <CmsLayout active-nav="dashboard" title="Studio de contenu" subtitle="Gestion indépendante du site institutionnel">
        <div class="bg-gradient-to-br from-primary to-primary-container rounded-xl p-6 text-white flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <p class="text-[11px] uppercase tracking-wider opacity-80 mb-1">Module indépendant</p>
                <h2 class="text-xl font-bold mb-1">Pilotez le contenu public du site CAMA</h2>
                <p class="text-sm opacity-90 max-w-xl">
                    Actualités, pages institutionnelles, bannière d'accueil, chiffres clés et FAQ — sans intervention technique, comme un éditeur visuel.
                </p>
            </div>
            <Link v-if="canOpenPageBuilder" class="bg-white text-primary px-4 py-2.5 rounded-lg text-xs font-bold whitespace-nowrap" :href="route('admin.cms.page_builder')">
                Ouvrir l'éditeur de pages
            </Link>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="assure-card p-4">
                <p class="text-[11px] text-on-surface-variant uppercase mb-1">Articles publiés</p>
                <p class="text-2xl font-bold">{{ stats.articles }}</p>
            </div>
            <div class="assure-card p-4">
                <p class="text-[11px] text-on-surface-variant uppercase mb-1">Pages gérées</p>
                <p class="text-2xl font-bold">{{ stats.pages }}</p>
            </div>
            <div class="assure-card p-4">
                <p class="text-[11px] text-on-surface-variant uppercase mb-1">Slides bannière</p>
                <p class="text-2xl font-bold">{{ stats.slides }}</p>
            </div>
            <div class="assure-card p-4">
                <p class="text-[11px] text-on-surface-variant uppercase mb-1">Questions FAQ</p>
                <p class="text-2xl font-bold">{{ stats.faq }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <Link
                v-for="mod in visibleModules"
                :key="mod.key"
                class="assure-card p-5 flex flex-col gap-3 hover:shadow-lg hover:-translate-y-1 transition"
                :href="route(mod.route)"
            >
                <div class="w-11 h-11 rounded-lg flex items-center justify-center" :class="iconBg(mod.color)">
                    <span class="material-symbols-outlined">{{ mod.icon }}</span>
                </div>
                <div>
                    <h3 class="font-semibold text-sm mb-1">{{ mod.title }}</h3>
                    <p class="text-xs text-on-surface-variant">{{ mod.desc }}</p>
                </div>
            </Link>
        </div>

        <div class="assure-card p-5">
            <h3 class="font-semibold text-sm mb-4">Activité récente du studio</h3>
            <div class="space-y-3">
                <div
                    v-for="entry in activity"
                    :key="entry.id"
                    class="flex items-start gap-3 pb-3 border-b border-outline-variant last:border-0 last:pb-0"
                >
                    <div class="w-8 h-8 rounded-full bg-surface-container-low flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-[16px] text-on-surface-variant">{{ entry.icon }}</span>
                    </div>
                    <div class="flex-1">
                        <p class="text-xs text-on-surface">{{ entry.text }}</p>
                        <p class="text-[10px] text-on-surface-variant mt-0.5">{{ entry.author_name }} · {{ entry.date }}</p>
                    </div>
                </div>
                <p v-if="!activity.length" class="text-xs text-on-surface-variant text-center py-4">Aucune activité récente.</p>
            </div>
        </div>
    </CmsLayout>
</template>
