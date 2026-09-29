<script setup>
import PublicCmsSections from '@/Components/PublicCmsSections.vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    pageSections: { type: Array, default: () => [] },
    articles: { type: Array, default: () => [] },
});
</script>

<template>
    <Head title="Mentions légales" />

    <PublicLayout>
        <main class="flex-grow py-stack-lg">
            <PublicCmsSections :sections="pageSections" :system-data="{ articles: props.articles }" />
        </main>
    </PublicLayout>
</template>
