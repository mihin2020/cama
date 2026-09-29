<script setup>
import CmsLayout from '@/Layouts/CmsLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    module: String,
    activeNav: String,
});
</script>

<template>
    <Head :title="module" />

    <CmsLayout :active-nav="activeNav" :title="module" :subtitle="`Module ${module} — migration en cours`">
        <div class="assure-card p-8 text-center">
            <span class="material-symbols-outlined text-primary text-[48px] mb-4">construction</span>
            <h2 class="font-headline-lg text-headline-md font-bold mb-2">{{ module }}</h2>
            <p class="text-sm text-on-surface-variant max-w-md mx-auto">
                Ce module est en cours de migration depuis la maquette HTML. L'interface et les fonctionnalités seront identiques à la version prototype.
            </p>
        </div>
    </CmsLayout>
</template>
