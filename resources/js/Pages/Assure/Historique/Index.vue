<script setup>
import AssureLayout from '@/Layouts/AssureLayout.vue';
import CamaPagination from '@/Components/CamaPagination.vue';
import { useClientPagination } from '@/Composables/usePagination';
import { Head, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

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

// Fil d'activité : 10 événements par page (lisible sur téléphone), retour au début à chaque filtre.
const listTop = ref(null);
const { pagination, paged: pagedEvents, goToPage, setPerPage } = useClientPagination(() => props.events, {
    perPageOptions: [10, 20, 50],
    anchor: listTop,
    resetOn: cat,
});
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

        <div ref="listTop" class="bg-white rounded-xl border border-outline-variant p-5 md:p-6 scroll-mt-24">
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

        <CamaPagination class="mt-2" v-bind="pagination" label="événement(s)" @change="goToPage" @per-page="setPerPage" />
    </AssureLayout>
</template>
