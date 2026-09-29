<script setup>
import AssureLayout from '@/Layouts/AssureLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    events: Array,
    filter: String,
    categories: Array,
    unreadCount: Number,
});

const cat = ref(props.filter);

watch(cat, (value) => {
    router.get(route('assure.historique'), { cat: value }, { preserveState: true, replace: true });
});

const CAT_COLORS = { Compte: 'bg-primary', Dossiers: 'bg-tertiary', Membres: 'bg-secondary' };

const PAGE_SIZE = 10;
const page = ref(1);
const totalPages = computed(() => Math.max(1, Math.ceil((props.events?.length ?? 0) / PAGE_SIZE)));
const pagedEvents = computed(() => (props.events ?? []).slice((page.value - 1) * PAGE_SIZE, page.value * PAGE_SIZE));

// Revenir à la page 1 quand la liste change (changement de filtre).
watch(() => props.events, () => { page.value = 1; });

function goToPage(n) {
    if (n < 1 || n > totalPages.value) return;
    page.value = n;
}
</script>

<template>
    <Head title="Historique" />

    <AssureLayout active-nav="historique" title="Historique" subtitle="Journal de votre activité sur la plateforme" :unread-count="unreadCount">
        <div class="flex flex-wrap gap-2 mb-6">
            <button
                v-for="c in categories"
                :key="c"
                type="button"
                class="px-4 py-2 rounded-full text-sm font-semibold transition-colors"
                :class="cat === c ? 'bg-primary text-on-primary' : 'bg-white border border-outline-variant text-on-surface-variant'"
                @click="cat = c"
            >{{ c }}</button>
        </div>

        <div class="bg-white rounded-xl border border-outline-variant p-5 md:p-6">
            <div v-for="(e, i) in pagedEvents" :key="`${e.date}-${i}`" class="flex gap-3">
                <div class="relative flex flex-col items-center">
                    <div class="w-2.5 h-2.5 rounded-full z-10 mt-1" :class="CAT_COLORS[e.cat] || 'bg-primary'" />
                    <div v-if="i < pagedEvents.length - 1" class="w-0.5 flex-1 bg-outline-variant" />
                </div>
                <div class="pb-5 min-w-0">
                    <p class="text-sm font-bold text-on-surface">{{ e.libelle }}</p>
                    <p v-if="e.membre" class="text-xs text-primary font-semibold mt-0.5">{{ e.membre }}</p>
                    <p class="text-xs text-on-surface-variant mt-1">{{ e.date }} · {{ e.cat }}</p>
                </div>
            </div>
            <p v-if="!events.length" class="text-center text-on-surface-variant text-sm py-8">Aucun événement dans cette catégorie.</p>
        </div>

        <div v-if="totalPages > 1" class="flex items-center justify-between gap-3 mt-4">
            <p class="text-xs text-on-surface-variant">Page {{ page }} / {{ totalPages }} · {{ events.length }} événement(s)</p>
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
