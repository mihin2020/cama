<script setup>
import AssureLayout from '@/Layouts/AssureLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    module: String,
    activeNav: String,
});
</script>

<template>
    <Head :title="module" />

    <AssureLayout :active-nav="activeNav" :title="module">
        <div class="bg-white rounded-xl border border-outline-variant p-8 text-center">
            <span class="material-symbols-outlined text-primary text-[48px] mb-4">construction</span>
            <h2 class="font-headline-lg text-headline-md font-bold mb-2">{{ module }}</h2>
            <p class="text-sm text-on-surface-variant max-w-md mx-auto">
                Ce module est en cours de migration depuis la maquette HTML. L'interface et les fonctionnalités seront identiques à la version prototype.
            </p>
        </div>
    </AssureLayout>
</template>
