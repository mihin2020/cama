<script setup>
import AssureLayout from '@/Layouts/AssureLayout.vue';
import { NOTIF_ICONS } from '@/Utils/camaStatus';
import { Head, router } from '@inertiajs/vue3';

defineProps({
    notifications: Array,
    unreadCount: Number,
});

const notifIconClass = (type) => {
    const color = NOTIF_ICONS[type]?.color ?? 'primary';
    const map = { secondary: 'bg-secondary/10 text-secondary', primary: 'bg-primary/10 text-primary', tertiary: 'bg-tertiary/10 text-tertiary', error: 'bg-error/10 text-error' };
    return map[color] ?? map.primary;
};

function markAllRead() {
    router.post(route('assure.notifications.read-all'));
}
</script>

<template>
    <Head title="Notifications" />

    <AssureLayout active-nav="notifications" title="Notifications" :unread-count="unreadCount">
        <div class="flex justify-between items-center mb-4">
            <p class="text-sm text-on-surface-variant">{{ unreadCount }} non lue(s)</p>
            <button v-if="unreadCount > 0" type="button" class="text-primary text-sm font-bold hover:underline" @click="markAllRead">Tout marquer comme lu</button>
        </div>

        <div class="bg-white rounded-xl border border-outline-variant divide-y divide-outline-variant overflow-hidden">
            <button
                v-for="n in notifications"
                :key="n.id"
                type="button"
                class="w-full text-left flex gap-3 px-4 md:px-5 py-4 hover:bg-surface-container-low transition-colors"
                :class="n.lu ? '' : 'bg-primary/5'"
                @click="router.patch(route('assure.notifications.read', n.id))"
            >
                <div class="w-10 h-10 rounded-full flex items-center justify-center shrink-0" :class="notifIconClass(n.type)">
                    <span class="material-symbols-outlined text-[18px]">{{ NOTIF_ICONS[n.type]?.icon ?? 'notifications' }}</span>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="font-semibold text-sm text-on-surface">{{ n.titre }}</p>
                    <p class="text-sm text-on-surface-variant mt-0.5">{{ n.contenu }}</p>
                    <p class="text-xs text-on-surface-variant mt-1">{{ n.date }}</p>
                </div>
            </button>
            <p v-if="!notifications.length" class="p-8 text-center text-on-surface-variant text-sm">Aucune notification.</p>
        </div>
    </AssureLayout>
</template>
