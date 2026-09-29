<script setup>
import PublicCmsSections from '@/Components/PublicCmsSections.vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    cmsPage: {
        type: Object,
        required: true,
    },
    articles: {
        type: Array,
        default: () => [],
    },
});

</script>

<template>
    <Head :title="cmsPage.title" />

    <PublicLayout>
        <article class="bg-background">
            <section class="bg-primary text-white">
                <div class="max-w-container-max-width mx-auto px-4 md:px-8 py-12 md:py-16">
                    <p class="text-xs uppercase tracking-[0.18em] opacity-80">CAMA</p>
                    <h1 class="font-headline text-3xl md:text-5xl font-extrabold mt-2">{{ cmsPage.title }}</h1>
                    <p v-if="cmsPage.updatedAt" class="text-sm opacity-80 mt-3">Mis à jour le {{ cmsPage.updatedAt }}</p>
                </div>
            </section>

            <PublicCmsSections :sections="cmsPage.sections" :system-data="{ articles: props.articles }" />
        </article>
    </PublicLayout>
</template>
