<script setup>
import { internalPath } from '@/Utils/internalLink';
import { Link } from '@inertiajs/vue3';
import { computed, useAttrs } from 'vue';

/**
 * Lien dont l'adresse est saisie dans le CMS : page du site → navigation sans rechargement
 * (Inertia), sinon lien classique (site externe, ancre, mailto:, fichier).
 */
const props = defineProps({
    href: { type: String, default: '' },
});

const attrs = useAttrs();
// Un lien ouvert dans un nouvel onglet reste un lien classique.
const path = computed(() => (attrs.target && attrs.target !== '_self' ? null : internalPath(props.href)));
</script>

<template>
    <Link v-if="path" :href="path"><slot /></Link>
    <a v-else :href="href || '#'"><slot /></a>
</template>
