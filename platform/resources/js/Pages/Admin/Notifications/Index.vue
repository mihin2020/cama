<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, router } from '@inertiajs/vue3';

defineProps({
    notifications: Array,
    unreadCount: Number,
});

const ADMIN_NOTIF_ICONS = {
    soumission: { icon: 'upload_file', color: 'primary' },
    retard: { icon: 'schedule', color: 'error' },
    compte: { icon: 'person_add', color: 'tertiary' },
    export: { icon: 'file_download', color: 'secondary' },
    securite: { icon: 'security', color: 'error' },
};

function notifIconClass(type) {
    const color = ADMIN_NOTIF_ICONS[type]?.color ?? 'primary';
    const map = {
        secondary: 'bg-secondary/10 text-secondary',
        primary: 'bg-primary/10 text-primary',
        tertiary: 'bg-tertiary/10 text-tertiary',
        error: 'bg-error/10 text-error',
    };
    return map[color] ?? map.primary;
}

function openNotification(n) {
    router.post(route('admin.notifications.read', n.id));
}

function markAllRead() {
    router.post(route('admin.notifications.read-all'));
}
</script>

<template>
    <Head title="Notifications" />

    <AdminLayout active-nav="notifications" title="Notifications internes" subtitle="Soumissions, alertes et événements système" :unread-notifications="unreadCount">
        <div class="max-w-[900px] w-full mx-auto">
            <div class="flex justify-end mb-4">
                <button type="button" class="px-4 py-2 rounded-lg border border-outline text-on-surface text-xs font-bold flex items-center gap-1.5 hover:bg-surface-container-low" @click="markAllRead">
                    <span class="material-symbols-outlined text-[16px]">done_all</span>
                    <span class="hidden sm:inline">Tout marquer comme lu</span>
                </button>
            </div>
            <div class="bg-white rounded-xl border border-outline-variant divide-y divide-outline-variant overflow-hidden">
                <button
                    v-for="n in notifications"
                    :key="n.id"
                    type="button"
                    class="w-full text-left flex gap-3 px-5 py-4 hover:bg-surface-container-low transition-colors border-l-4"
                    :class="n.lu ? 'border-transparent' : 'border-primary bg-primary/5'"
                    @click="openNotification(n)"
                >
                    <div class="w-9 h-9 rounded-full flex items-center justify-center shrink-0" :class="notifIconClass(n.type)">
                        <span class="material-symbols-outlined text-[18px]">{{ ADMIN_NOTIF_ICONS[n.type]?.icon ?? 'notifications' }}</span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-2">
                            <p class="font-bold text-on-surface text-xs flex items-center gap-1">
                                {{ n.titre }}
                                <span v-if="!n.lu" class="inline-block w-1.5 h-1.5 rounded-full bg-primary" />
                            </p>
                            <p class="text-[11px] text-on-surface-variant shrink-0">{{ n.date }}</p>
                        </div>
                        <p class="text-xs text-on-surface-variant mt-0.5">{{ n.contenu }}</p>
                    </div>
                </button>
                <p v-if="!notifications.length" class="p-10 text-center text-on-surface-variant text-sm">Aucune notification.</p>
            </div>
        </div>
    </AdminLayout>
</template>
