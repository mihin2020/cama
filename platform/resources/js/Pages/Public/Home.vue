<script setup>
import { Head } from '@inertiajs/vue3';
import PublicCmsSections from '@/Components/PublicCmsSections.vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';

const props = defineProps({
    slides: {
        type: Array,
        default: () => [],
    },
    keyFigures: {
        type: Array,
        default: () => [],
    },
    latestArticles: {
        type: Array,
        default: () => [],
    },
    pillars: {
        type: Array,
        default: () => [],
    },
    pageSections: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <Head title="Accueil" />

    <PublicLayout>
        <main>
            <PublicCmsSections
                :sections="pageSections"
                :system-data="{ slides: props.slides, pillars: props.pillars, keyFigures: props.keyFigures, latestArticles: props.latestArticles }"
            />
        </main>
    </PublicLayout>
</template>
