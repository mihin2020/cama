<script setup>
import PublicCmsSections from '@/Components/PublicCmsSections.vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    locations: { type: Array, default: () => [] },
    partners: { type: Array, default: () => [] },
    articles: { type: Array, default: () => [] },
    pageSections: { type: Array, default: () => [] },
});
</script>

<template>
    <Head title="Contact & Cartographie" />

    <PublicLayout>
        <main class="min-h-screen">
            <PublicCmsSections :sections="pageSections" :system-data="{ locations: props.locations, partners: props.partners, articles: props.articles }" />
        </main>
    </PublicLayout>
</template>
