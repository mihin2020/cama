<script setup>
import PublicCmsSections from '@/Components/PublicCmsSections.vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    faqs: { type: Array, default: () => [] },
    partners: { type: Array, default: () => [] },
    articles: { type: Array, default: () => [] },
    pageSections: { type: Array, default: () => [] },
});
</script>

<template>
    <Head title="Services" />

    <PublicLayout>
        <main class="min-h-screen pb-stack-lg">
            <PublicCmsSections
                :sections="pageSections"
                :system-data="{ faqs: props.faqs, partners: props.partners, articles: props.articles }"
            />
        </main>
    </PublicLayout>
</template>
