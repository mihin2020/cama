<script setup>
import PublicCmsSections from '@/Components/PublicCmsSections.vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    categories: { type: Array, default: () => [] },
    resources: { type: Array, default: () => [] },
    articles: { type: Array, default: () => [] },
    pageSections: { type: Array, default: () => [] },
});
</script>

<template>
    <Head title="Ressources" />

    <PublicLayout>
        <main>
            <PublicCmsSections :sections="pageSections" :system-data="{ categories: props.categories, resources: props.resources, articles: props.articles }" />
        </main>
    </PublicLayout>
</template>
