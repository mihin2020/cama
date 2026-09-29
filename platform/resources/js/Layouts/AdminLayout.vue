<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useAdminPermissions } from '@/Composables/useAdminPermissions';
import { useShellSidebar } from '@/Composables/useShellSidebar';
import AdminHeaderShortcuts from '@/Components/AdminHeaderShortcuts.vue';

import { useAdminDossierBadge } from '@/Composables/useAdminDossierBadge';

const props = defineProps({
    title: { type: String, default: 'Tableau de bord' },
    subtitle: { type: String, default: 'Indicateurs et statistiques de la plateforme' },
    activeNav: { type: String, default: 'dashboard' },
    inscriptionsCount: { type: Number, default: 0 },
    unreadNotifications: { type: Number, default: 0 },
});

const page = usePage();
const admin = computed(() => page.props.auth.admin);
const inscriptionsCount = computed(() => page.props.app?.inscriptionsEnAttente ?? props.inscriptionsCount);
const unreadNotifications = computed(() => page.props.app?.adminUnreadNotifications ?? props.unreadNotifications);
const { badgeCount: dossiersBadge } = useAdminDossierBadge();
const { toggleSidebar, closeSidebar } = useShellSidebar();
const { can, canSee, canAccessCms } = useAdminPermissions();

const headerSubtitle = computed(() => {
    const role = admin.value?.role ?? 'gestionnaire';
    if (role === 'direction') {
        return 'Vue stratégique — indicateurs consolidés CAMA';
    }
    return props.subtitle;
});

const navClass = (key) =>
  props.activeNav === key
    ? 'sidebar-nav-link is-active flex items-center gap-3 px-3 py-2.5 font-label-md text-[13px]'
    : 'sidebar-nav-link flex items-center gap-3 px-3 py-2.5 font-label-md text-[13px]';

const logout = () => router.post(route('admin.logout'));
</script>

<template>
    <div class="fixed inset-0 bg-black/40 z-30 md:hidden" id="sidebar-overlay" @click="closeSidebar" />

    <aside class="h-screen w-64 fixed left-0 top-0 flex flex-col py-6 px-3 gap-1 z-40" id="sidebar">
        <Link class="flex items-center gap-3 px-2 mb-4" href="/">
            <img alt="Logo CAMA" class="h-10 w-10 object-contain" src="/images/logo_cama.png" />
            <span class="sidebar-brand-title text-title-lg font-headline-lg font-extrabold tracking-tight leading-tight">
                CAMA
                <span class="sidebar-brand-sub block text-[9px] font-body-md font-normal tracking-wide normal-case">Back-office</span>
            </span>
        </Link>

        <nav class="flex flex-col gap-0.5 flex-1 overflow-y-auto no-scrollbar">
            <Link :class="navClass('dashboard')" :href="route('admin.dashboard')" data-roles="gestionnaire,superviseur,administrateur,direction">
                <span class="material-symbols-outlined text-[20px]">dashboard</span> Tableau de bord
            </Link>
            <Link
                v-show="can('dossiers.view')"
                :class="navClass('dossiers')"
                :href="route('admin.dossiers')"
            >
                <span class="material-symbols-outlined text-[20px]">folder_shared</span> Dossiers
                <span
                    v-if="dossiersBadge > 0"
                    class="ml-auto bg-primary text-on-primary text-[10px] font-bold rounded-full min-w-[20px] h-5 px-1 flex items-center justify-center"
                >{{ dossiersBadge > 9 ? '9+' : dossiersBadge }}</span>
            </Link>
            <Link v-show="can('assures.view')" :class="navClass('assures')" :href="route('admin.assures')">
                <span class="material-symbols-outlined text-[20px]">groups</span> Assurés
            </Link>
            <Link
                v-show="can('inscriptions.view')"
                :class="navClass('inscriptions')"
                :href="route('admin.inscriptions')"
            >
                <span class="material-symbols-outlined text-[20px]">how_to_reg</span> Inscriptions
                <span
                    v-if="inscriptionsCount > 0"
                    class="ml-auto bg-primary text-on-primary text-[10px] font-bold rounded-full min-w-[20px] h-5 px-1 flex items-center justify-center"
                >{{ inscriptionsCount > 9 ? '9+' : inscriptionsCount }}</span>
            </Link>
            <Link
                v-show="can('users.manage') && canSee('superviseur,administrateur')"
                :class="navClass('utilisateurs')"
                :href="route('admin.utilisateurs')"
            >
                <span class="material-symbols-outlined text-[20px]">manage_accounts</span> Comptes internes
            </Link>
            <Link v-show="can('exports.export')" :class="navClass('exports')" :href="route('admin.exports')">
                <span class="material-symbols-outlined text-[20px]">file_download</span> Exports
            </Link>
            <Link
                v-show="can('audit.view')"
                :class="navClass('audit')"
                :href="route('admin.audit')"
            >
                <span class="material-symbols-outlined text-[20px]">login</span> Connexions
            </Link>
            <Link
                v-show="can('settings.manage')"
                :class="navClass('parametres')"
                :href="route('admin.parametres')"
            >
                <span class="material-symbols-outlined text-[20px]">settings</span> Paramètres
            </Link>
            <div v-show="canAccessCms()" class="h-px bg-white/10 my-2 mx-1" />
            <Link
                v-show="canAccessCms()"
                :class="navClass('cms')"
                :href="route('admin.cms.dashboard')"
            >
                <span class="material-symbols-outlined text-[20px]">design_services</span> Studio de contenu
            </Link>
            <div class="h-px bg-white/10 my-2 mx-1" />
            <Link :class="navClass('notifications')" :href="route('admin.notifications')" data-roles="gestionnaire,superviseur,administrateur,direction">
                <span class="material-symbols-outlined text-[20px]">notifications</span> Notifications
                <span
                    v-if="unreadNotifications > 0"
                    class="ml-auto bg-primary text-on-primary text-[10px] font-bold rounded-full w-5 h-5 flex items-center justify-center"
                >{{ unreadNotifications > 9 ? '9+' : unreadNotifications }}</span>
            </Link>
            <Link :class="navClass('profil')" :href="route('admin.profil')" data-roles="gestionnaire,superviseur,administrateur,direction">
                <span class="material-symbols-outlined text-[20px]">account_circle</span> Mon profil
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
                    <p class="text-caption text-on-surface-variant hidden sm:block">{{ headerSubtitle }}</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <AdminHeaderShortcuts :active-nav="activeNav" />
                <div class="flex items-center gap-3 pl-2 md:pl-4 md:border-l border-outline-variant">
                    <div class="w-9 h-9 rounded-full bg-primary/10 text-primary flex items-center justify-center font-bold text-sm shrink-0" id="topbar-avatar">
                        {{ admin?.initiales }}
                    </div>
                    <div class="hidden md:block text-left">
                        <p class="text-label-md font-label-md font-bold text-on-surface leading-tight" id="topbar-name">{{ admin?.displayName ?? admin?.fullName }}</p>
                    </div>
                </div>
            </div>
        </header>

        <main class="flex-1 p-4 pb-24 md:p-8 md:pb-8 max-w-[1400px] w-full mx-auto">
            <slot />
        </main>

        <footer class="px-4 md:px-8 py-6 text-caption text-on-surface-variant border-t border-outline-variant">
            © 2026 CAMA — Back-office institutionnel. Accès restreint et tracé.
        </footer>
    </div>

    <nav class="md:hidden fixed bottom-0 left-0 w-full z-40 flex justify-around items-center h-16 bg-white border-t border-outline-variant shadow-lg">
        <Link class="flex flex-col items-center justify-center gap-0.5" :class="activeNav === 'dashboard' ? 'text-primary' : 'text-on-surface-variant'" :href="route('admin.dashboard')">
            <span class="material-symbols-outlined text-[22px]">dashboard</span>
            <span class="text-[10px] font-label-md">Accueil</span>
        </Link>
        <Link
            v-show="can('dossiers.view')"
            class="relative flex flex-col items-center justify-center gap-0.5"
            :class="activeNav === 'dossiers' ? 'text-primary' : 'text-on-surface-variant'"
            :href="route('admin.dossiers')"
        >
            <span class="material-symbols-outlined text-[22px]">folder_shared</span>
            <span
                v-if="dossiersBadge > 0"
                class="absolute top-0 right-2 bg-primary text-on-primary text-[9px] font-bold rounded-full min-w-[16px] h-4 px-1 flex items-center justify-center"
            >{{ dossiersBadge > 9 ? '9+' : dossiersBadge }}</span>
            <span class="text-[10px] font-label-md">Dossiers</span>
        </Link>
        <Link v-show="can('assures.view')" class="flex flex-col items-center justify-center gap-0.5" :class="activeNav === 'assures' ? 'text-primary' : 'text-on-surface-variant'" :href="route('admin.assures')">
            <span class="material-symbols-outlined text-[22px]">groups</span>
            <span class="text-[10px] font-label-md">Assurés</span>
        </Link>
        <Link class="flex flex-col items-center justify-center gap-0.5" :class="activeNav === 'notifications' ? 'text-primary' : 'text-on-surface-variant'" :href="route('admin.notifications')">
            <span class="material-symbols-outlined text-[22px]">notifications</span>
            <span class="text-[10px] font-label-md">Alertes</span>
        </Link>
        <Link class="flex flex-col items-center justify-center gap-0.5" :class="activeNav === 'profil' ? 'text-primary' : 'text-on-surface-variant'" :href="route('admin.profil')">
            <span class="material-symbols-outlined text-[22px]">person</span>
            <span class="text-[10px] font-label-md">Profil</span>
        </Link>
    </nav>
</template>
