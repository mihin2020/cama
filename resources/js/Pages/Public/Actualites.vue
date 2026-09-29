<script setup>
import PublicCmsSections from '@/Components/PublicCmsSections.vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    articles: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    initialSearch: { type: String, default: '' },
    pageSections: { type: Array, default: () => [] },
});
</script>

<template>
    <Head title="Actualités" />

    <PublicLayout>
        <main class="flex-grow">
            <PublicCmsSections
                :sections="pageSections"
                :system-data="{ articles: props.articles, categories: props.categories, initialSearch: props.initialSearch }"
            />
        </main>
    </PublicLayout>
</template>
