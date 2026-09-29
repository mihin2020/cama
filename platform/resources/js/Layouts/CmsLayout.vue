<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useAdminPermissions } from '@/Composables/useAdminPermissions';
import { useShellSidebar } from '@/Composables/useShellSidebar';
import AdminHeaderShortcuts from '@/Components/AdminHeaderShortcuts.vue';

const props = defineProps({
    title: { type: String, default: 'Studio de contenu' },
    subtitle: { type: String, default: 'Gestion indépendante du site institutionnel' },
    activeNav: { type: String, default: 'dashboard' },
});

const page = usePage();
const admin = computed(() => page.props.auth.admin);
const app = computed(() => page.props.app ?? {});
const { toggleSidebar, closeSidebar } = useShellSidebar();
const { can } = useAdminPermissions();

const navClass = (key) =>
    props.activeNav === key
        ? 'sidebar-nav-link is-active flex items-center gap-3 px-3 py-2.5 font-label-md text-[13px]'
        : 'sidebar-nav-link flex items-center gap-3 px-3 py-2.5 font-label-md text-[13px]';

const logout = () => router.post(route('admin.logout'));

const navItems = [
    { key: 'dashboard', label: "Vue d'ensemble CMS", icon: 'dashboard', href: 'admin.cms.dashboard', permission: 'cms.dashboard' },
    { key: 'actualites', label: 'Actualités', icon: 'newspaper', href: 'admin.cms.actualites', permission: 'cms.articles' },
    { key: 'pages', label: 'Pages', icon: 'description', href: 'admin.cms.pages', permission: 'cms.pages' },
    { key: 'page-builder', label: 'Éditeur de pages', icon: 'dashboard_customize', href: 'admin.cms.page_builder', permission: 'cms.pages' },
    { key: 'media', label: 'Médiathèque', icon: 'perm_media', href: 'admin.cms.media', permission: 'cms.media' },
    { key: 'menus', label: 'Menus du site', icon: 'menu', href: 'admin.cms.menus', permission: 'cms.menus' },
    { key: 'footer', label: 'Pied de page', icon: 'call_to_action', href: 'admin.cms.footer', permission: 'cms.footer' },
    { key: 'banniere', label: "Bannière d'accueil", icon: 'view_carousel', href: 'admin.cms.banniere', permission: 'cms.banniere' },
    { key: 'chiffres-cles', label: 'Chiffres clés', icon: 'monitoring', href: 'admin.cms.chiffres_cles', permission: 'cms.chiffres' },
    { key: 'faq', label: 'FAQ', icon: 'quiz', href: 'admin.cms.faq', permission: 'cms.faq' },
    { key: 'ressources', label: 'Ressources', icon: 'folder_open', href: 'admin.cms.ressources', permission: 'cms.ressources' },
    { key: 'partenaires', label: 'Partenaires & centres', icon: 'handshake', href: 'admin.cms.partenaires', permission: 'cms.partenaires' },
    { key: 'contacts', label: 'Messages contact', icon: 'contact_mail', href: 'admin.cms.contacts', permission: 'cms.contacts', badge: 'cmsNewContactCount' },
    { key: 'newsletter', label: 'Newsletter', icon: 'mark_email_read', href: 'admin.cms.newsletter', permission: 'cms.newsletter', badge: 'cmsActiveNewsletterCount' },
];

const visibleNavItems = computed(() => navItems.filter((item) => can(item.permission)));

const badgeValue = (item) => (item.badge ? Number(app.value?.[item.badge] ?? 0) : 0);
</script>

<template>
    <div class="fixed inset-0 bg-black/40 z-30 md:hidden" id="sidebar-overlay" @click="closeSidebar" />

    <aside class="h-screen w-64 fixed left-0 top-0 flex flex-col py-6 px-3 gap-1 z-40" id="sidebar">
        <Link class="flex items-center gap-3 px-2 mb-4" href="/">
            <img alt="Logo CAMA" class="h-10 w-10 object-contain" src="/images/logo_cama.png" />
            <span class="sidebar-brand-title text-title-lg font-headline-lg font-extrabold tracking-tight leading-tight">
                CAMA
                <span class="sidebar-brand-sub block text-[9px] font-body-md font-normal tracking-wide normal-case">Studio de contenu</span>
            </span>
        </Link>

        <nav class="flex flex-col gap-0.5 flex-1 overflow-y-auto no-scrollbar">
            <Link :class="navClass('back')" :href="route('admin.dashboard')">
                <span class="material-symbols-outlined text-[20px]">arrow_back</span> Retour au back-office
            </Link>
            <div class="h-px bg-white/10 my-2 mx-1" />
            <Link
                v-for="item in visibleNavItems"
                :key="item.key"
                :class="navClass(item.key)"
                :href="route(item.href)"
            >
                <span class="material-symbols-outlined text-[20px]">{{ item.icon }}</span>
                {{ item.label }}
                <span
                    v-if="badgeValue(item) > 0"
                    class="ml-auto bg-primary text-on-primary text-[10px] font-bold rounded-full min-w-[20px] h-5 px-1 flex items-center justify-center"
                >
                    {{ badgeValue(item) > 99 ? '99+' : badgeValue(item) }}
                </span>
            </Link>
        </nav>

        <button
            type="button"
            class="sidebar-logout sidebar-nav-link flex items-center gap-3 px-3 py-2.5 font-label-md text-[13px] mt-auto border-t pt-4 mx-1 w-full text-left"
            @click="logout"
        >
            <span class="material-symbols-outlined text-[20px]">logout</span> Déconnexion
        </button>
    </aside>

    <div class="ml-0 md:ml-64 min-h-screen flex flex-col" id="content-shell">
        <header class="sticky top-0 z-20 flex justify-between items-center px-4 md:px-8 h-16 w-full" id="app-header">
            <div class="flex items-center gap-3">
                <button
                    type="button"
                    class="header-menu-btn md:hidden w-9 h-9 flex items-center justify-center rounded-full"
                    id="sidebar-toggle"
                    @click="toggleSidebar"
                >
                    <span class="material-symbols-outlined">menu</span>
                </button>
                <div>
                    <h1 class="font-title-lg text-title-lg text-on-surface leading-tight">{{ title }}</h1>
                    <p class="text-caption text-on-surface-variant hidden sm:block">{{ subtitle }}</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <AdminHeaderShortcuts :active-nav="activeNav" />
                <Link class="flex items-center gap-3 pl-2 md:pl-4 md:border-l border-outline-variant" :href="route('admin.profil')">
                    <div class="w-9 h-9 rounded-full bg-primary/10 text-primary flex items-center justify-center font-bold text-sm shrink-0">
                        {{ admin?.initiales }}
                    </div>
                    <div class="hidden md:block text-left">
                        <p class="text-label-md font-label-md font-bold text-on-surface leading-tight">{{ admin?.displayName ?? admin?.fullName }}</p>
                        <p class="text-caption text-on-surface-variant leading-tight">{{ admin?.roleLabel }}</p>
                    </div>
                </Link>
            </div>
        </header>

        <main class="flex-1 p-4 md:p-8 space-y-6">
            <slot />
        </main>

        <footer class="px-4 md:px-8 py-6 text-caption text-on-surface-variant border-t border-outline-variant">
            © 2026 CAMA — Studio de contenu, module indépendant du back-office.
        </footer>
    </div>
</template>
