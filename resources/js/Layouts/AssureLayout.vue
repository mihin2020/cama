<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useShellSidebar } from '@/Composables/useShellSidebar';
import { NOTIF_ICONS } from '@/Utils/camaStatus';

const props = defineProps({
    title: { type: String, default: 'Tableau de bord' },
    subtitle: { type: String, default: 'Bienvenue dans votre espace assuré' },
    activeNav: { type: String, default: 'dashboard' },
    unreadCount: { type: Number, default: 0 },
    previewNotifications: { type: Array, default: () => [] },
});

const page = usePage();
const assure = computed(() => page.props.auth.assure);
const unreadCount = computed(() => Number(page.props.app?.assureUnreadNotifications ?? props.unreadCount ?? 0));
const previewNotifications = computed(() => page.props.app?.assurePreviewNotifications ?? props.previewNotifications ?? []);
const { toggleSidebar, closeSidebar } = useShellSidebar();

const navClass = (key) =>
  props.activeNav === key
    ? 'sidebar-nav-link is-active flex items-center gap-3 px-3 py-2.5 font-label-md text-[13px]'
    : 'sidebar-nav-link flex items-center gap-3 px-3 py-2.5 font-label-md text-[13px]';

const logout = () => router.post(route('assure.logout'));

const notifMeta = (type) => NOTIF_ICONS[type] || { icon: 'notifications', color: 'primary' };

const notifIconClass = (type) => {
    const color = notifMeta(type).color;
    const map = {
        secondary: 'bg-secondary/10 text-secondary',
        primary: 'bg-primary/10 text-primary',
        tertiary: 'bg-tertiary/10 text-tertiary',
        error: 'bg-error/10 text-error',
    };
    return map[color] ?? map.primary;
};
</script>

<template>
    <div class="fixed inset-0 bg-black/40 z-30 md:hidden" id="sidebar-overlay" @click="closeSidebar" />

    <aside class="h-screen w-64 fixed left-0 top-0 flex flex-col py-6 px-3 gap-1 z-40" id="sidebar">
        <Link class="flex items-center gap-3 px-2 mb-6" href="/">
            <img alt="Logo CAMA" class="h-10 w-10 object-contain" src="/images/logo_cama.png" />
            <span class="sidebar-brand-title text-title-lg font-headline-lg font-extrabold tracking-tight leading-tight">
                CAMA
                <span class="sidebar-brand-sub block text-[9px] font-body-md font-normal tracking-wide normal-case">Espace Assuré</span>
            </span>
        </Link>

        <nav class="flex flex-col gap-0.5 flex-1 overflow-y-auto no-scrollbar">
            <Link :class="navClass('dashboard')" :href="route('assure.dashboard')">
                <span class="material-symbols-outlined text-[20px]">dashboard</span> Tableau de bord
            </Link>
            <Link :class="navClass('famille')" :href="route('assure.membres')">
                <span class="material-symbols-outlined text-[20px]">group</span> Ma famille
            </Link>
            <Link :class="navClass('historique')" :href="route('assure.historique')">
                <span class="material-symbols-outlined text-[20px]">history</span> Historique
            </Link>
            <Link :class="navClass('notifications')" :href="route('assure.notifications')">
                <span class="material-symbols-outlined text-[20px]">notifications</span> Notifications
                <span
                    v-if="unreadCount > 0"
                    class="ml-auto bg-primary text-on-primary text-[10px] font-bold rounded-full w-5 h-5 flex items-center justify-center"
                    id="sidebar-unread-badge"
                >{{ unreadCount > 9 ? '9+' : unreadCount }}</span>
            </Link>
            <Link :class="navClass('profil')" :href="route('assure.profil')">
                <span class="material-symbols-outlined text-[20px]">manage_accounts</span> Mon profil
            </Link>
        </nav>

        <Link
            class="flex items-center justify-center gap-2 bg-primary text-on-primary px-4 py-2.5 rounded-lg font-bold font-label-md text-[13px] hover:opacity-90 active:scale-95 transition-all shadow-sm mb-2 mx-1"
            :href="route('assure.ajouter-membre')"
        >
            <span class="material-symbols-outlined text-[20px]">group_add</span> Mon dossier familial
        </Link>

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
            <div class="flex items-center gap-2 md:gap-4">
                <slot name="header-actions" />
                <div class="relative group">
                    <button type="button" class="relative w-10 h-10 rounded-full hover:bg-surface-container-high flex items-center justify-center transition-colors" :aria-label="unreadCount > 0 ? `${unreadCount} notification(s) non lue(s)` : 'Notifications'">
                        <span class="material-symbols-outlined text-on-surface-variant">notifications</span>
                        <span
                            v-if="unreadCount > 0"
                            class="absolute -top-0.5 -right-0.5 bg-primary text-on-primary text-[9px] font-bold rounded-full min-w-[18px] h-[18px] px-1 flex items-center justify-center border-2 border-surface-container-lowest"
                        >{{ unreadCount > 9 ? '9+' : unreadCount }}</span>
                    </button>
                    <div class="absolute right-0 top-full mt-2 w-80 bg-white rounded-lg border border-outline-variant shadow-lg overflow-hidden opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50">
                        <div class="p-3 border-b border-outline-variant font-bold text-sm flex items-center justify-between gap-2">
                            <span>Notifications récentes</span>
                            <span v-if="unreadCount > 0" class="text-[10px] font-bold text-primary bg-primary/10 px-2 py-0.5 rounded-full">{{ unreadCount }} non lue(s)</span>
                        </div>
                        <div id="topbar-notif-preview" class="divide-y divide-outline-variant max-h-72 overflow-y-auto">
                            <template v-if="previewNotifications.length">
                                <Link
                                    v-for="n in previewNotifications.slice(0, 5)"
                                    :key="n.id"
                                    :href="n.lien || route('assure.notifications')"
                                    class="flex gap-2.5 px-4 py-3 hover:bg-surface-container-low transition-colors"
                                    :class="n.lu ? '' : 'bg-primary/5'"
                                >
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center shrink-0" :class="notifIconClass(n.type)">
                                        <span class="material-symbols-outlined text-[16px]">{{ notifMeta(n.type).icon }}</span>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-xs font-bold text-on-surface leading-snug">{{ n.titre }}</p>
                                        <p class="text-sm text-on-surface-variant leading-snug line-clamp-2 mt-0.5">{{ n.contenu }}</p>
                                        <p class="text-xs text-on-surface-variant mt-1">{{ n.date }}</p>
                                    </div>
                                </Link>
                            </template>
                            <p v-else class="px-4 py-6 text-center text-xs text-on-surface-variant italic">Aucune notification récente.</p>
                        </div>
                        <Link class="block text-center text-primary font-bold text-sm py-3 hover:bg-surface-container-low transition-colors" :href="route('assure.notifications')">
                            Voir toutes les notifications
                        </Link>
                    </div>
                </div>
                <Link class="flex items-center gap-3 pl-2 md:pl-4 md:border-l border-outline-variant" :href="route('assure.profil')">
                    <div class="w-9 h-9 rounded-full bg-primary/10 text-primary flex items-center justify-center font-bold text-sm shrink-0" id="topbar-avatar">
                        {{ assure?.initiales }}
                    </div>
                    <div class="hidden md:block text-left">
                        <p class="text-label-md font-label-md font-bold text-on-surface leading-tight" id="topbar-name">{{ assure?.fullName }}</p>
                        <p class="text-caption text-on-surface-variant leading-tight" id="topbar-matricule">Matricule {{ assure?.matricule }}</p>
                    </div>
                </Link>
            </div>
        </header>

        <main class="flex-1 p-4 pb-24 md:p-8 md:pb-8 max-w-[1400px] w-full mx-auto">
            <slot />
        </main>

        <footer class="px-4 md:px-8 py-6 text-caption text-on-surface-variant border-t border-outline-variant">
            © 2026 CAMA — Caisse d'Assurance Maladie des Armées. Tous droits réservés.
        </footer>
    </div>

    <nav class="md:hidden fixed bottom-0 left-0 w-full z-40 flex justify-around items-center h-16 bg-white border-t border-outline-variant shadow-lg">
        <Link class="flex flex-col items-center justify-center gap-0.5" :class="activeNav === 'dashboard' ? 'text-primary' : 'text-on-surface-variant'" :href="route('assure.dashboard')">
            <span class="material-symbols-outlined text-[22px]">dashboard</span>
            <span class="text-[10px] font-label-md">Accueil</span>
        </Link>
        <Link class="flex flex-col items-center justify-center gap-0.5" :class="activeNav === 'famille' ? 'text-primary' : 'text-on-surface-variant'" :href="route('assure.membres')">
            <span class="material-symbols-outlined text-[22px]">group</span>
            <span class="text-[10px] font-label-md">Famille</span>
        </Link>
        <Link class="flex flex-col items-center justify-center -mt-7" :href="route('assure.ajouter-membre')">
            <span class="w-[52px] h-[52px] rounded-full bg-primary text-on-primary flex items-center justify-center shadow-lg border-4 border-surface-container-low">
                <span class="material-symbols-outlined">add</span>
            </span>
        </Link>
        <Link class="flex flex-col items-center justify-center gap-0.5" :class="activeNav === 'historique' ? 'text-primary' : 'text-on-surface-variant'" :href="route('assure.historique')">
            <span class="material-symbols-outlined text-[22px]">history</span>
            <span class="text-[10px] font-label-md">Historique</span>
        </Link>
        <Link class="flex flex-col items-center justify-center gap-0.5" :class="activeNav === 'profil' ? 'text-primary' : 'text-on-surface-variant'" :href="route('assure.profil')">
            <span class="material-symbols-outlined text-[22px]">person</span>
            <span class="text-[10px] font-label-md">Profil</span>
        </Link>
    </nav>
</template>
