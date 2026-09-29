<script setup>
import AssureLayout from '@/Layouts/AssureLayout.vue';
import { NOTIF_ICONS } from '@/Utils/camaStatus';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    notifications: Array,
    unreadCount: Number,
});

const notifIconClass = (type) => {
    const color = NOTIF_ICONS[type]?.color ?? 'primary';
    const map = { secondary: 'bg-secondary/10 text-secondary', primary: 'bg-primary/10 text-primary', tertiary: 'bg-tertiary/10 text-tertiary', error: 'bg-error/10 text-error' };
    return map[color] ?? map.primary;
};

const PAGE_SIZE = 10;
const page = ref(1);
const totalPages = computed(() => Math.max(1, Math.ceil((props.notifications?.length ?? 0) / PAGE_SIZE)));
const pagedNotifications = computed(() => (props.notifications ?? []).slice((page.value - 1) * PAGE_SIZE, page.value * PAGE_SIZE));
watch(() => props.notifications, () => { if (page.value > totalPages.value) page.value = totalPages.value; });

function goToPage(n) {
    if (n < 1 || n > totalPages.value) return;
    page.value = n;
}

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
                v-for="n in pagedNotifications"
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

        <div v-if="totalPages > 1" class="flex items-center justify-between gap-3 mt-4">
            <p class="text-xs text-on-surface-variant">Page {{ page }} / {{ totalPages }} · {{ notifications.length }} notification(s)</p>
            <div class="flex items-center gap-1">
                <button
                    type="button"
                    class="w-9 h-9 rounded-lg border border-outline-variant flex items-center justify-center text-on-surface-variant hover:bg-surface-container-low disabled:opacity-40 disabled:pointer-events-none"
                    :disabled="page <= 1"
                    aria-label="Page précédente"
                    @click="goToPage(page - 1)"
                >
                    <span class="material-symbols-outlined text-[20px]">chevron_left</span>
                </button>
                <button
                    v-for="n in totalPages"
                    :key="n"
                    type="button"
                    class="min-w-9 h-9 px-2 rounded-lg border text-sm font-semibold transition-colors"
                    :class="n === page ? 'bg-primary text-on-primary border-primary' : 'border-outline-variant text-on-surface-variant hover:bg-surface-container-low'"
                    @click="goToPage(n)"
                >{{ n }}</button>
                <button
                    type="button"
                    class="w-9 h-9 rounded-lg border border-outline-variant flex items-center justify-center text-on-surface-variant hover:bg-surface-container-low disabled:opacity-40 disabled:pointer-events-none"
                    :disabled="page >= totalPages"
                    aria-label="Page suivante"
                    @click="goToPage(page + 1)"
                >
                    <span class="material-symbols-outlined text-[20px]">chevron_right</span>
                </button>
            </div>
        </div>
    </AssureLayout>
</template>
