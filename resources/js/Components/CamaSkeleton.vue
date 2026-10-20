<script setup>
/**
 * Bloc gris animé qui réserve la place d'un contenu en cours de chargement.
 * L'animation est coupée si l'utilisateur a demandé moins de mouvement.
 */
defineProps({
    width: { type: String, default: '100%' },
    height: { type: String, default: '0.75rem' },
    rounded: { type: String, default: 'rounded-md' },
});
</script>

<template>
    <span
        class="block bg-surface-container-high motion-safe:animate-pulse"
        :class="rounded"
        :style="{ width, height }"
        aria-hidden="true"
    />
</template>
