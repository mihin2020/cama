<script setup>
import CamaSkeleton from '@/Components/CamaSkeleton.vue';

/** Silhouette d'une page (titre, indicateurs, tableau) affichée pendant le changement de page. */
defineProps({
    cards: { type: Number, default: 4 },
    rows: { type: Number, default: 6 },
});
</script>

<template>
    <div class="space-y-6" role="status" aria-live="polite" aria-label="Chargement de la page">
        <span class="sr-only">Chargement de la page…</span>

        <div class="space-y-2">
            <CamaSkeleton width="14rem" height="1.25rem" />
            <CamaSkeleton width="22rem" height="0.75rem" class="max-w-full" />
        </div>

        <div v-if="cards" class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div v-for="n in cards" :key="n" class="assure-card p-5 space-y-3">
                <CamaSkeleton width="2.5rem" height="2.5rem" rounded="rounded-xl" />
                <CamaSkeleton width="60%" height="1.5rem" />
                <CamaSkeleton width="80%" />
            </div>
        </div>

        <div class="assure-card overflow-hidden">
            <div class="px-5 py-4 border-b border-outline-variant flex items-center gap-3">
                <CamaSkeleton width="10rem" height="1rem" />
                <CamaSkeleton width="8rem" height="2rem" rounded="rounded-lg" class="ml-auto" />
            </div>
            <div v-for="n in rows" :key="n" class="px-5 py-4 border-b border-outline-variant last:border-b-0 flex items-center gap-4">
                <CamaSkeleton width="2rem" height="2rem" rounded="rounded-full" class="shrink-0" />
                <div class="flex-1 space-y-2">
                    <CamaSkeleton :width="`${45 + ((n * 17) % 35)}%`" />
                    <CamaSkeleton :width="`${25 + ((n * 11) % 25)}%`" height="0.6rem" />
                </div>
                <CamaSkeleton width="5rem" height="1.25rem" rounded="rounded-full" class="hidden sm:block shrink-0" />
            </div>
        </div>
    </div>
</template>
