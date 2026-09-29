<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    query: { type: String, default: '' },
    results: { type: Object, default: () => ({ pages: [], articles: [], resources: [] }) },
});

const total = (props.results.pages?.length ?? 0) + (props.results.articles?.length ?? 0) + (props.results.resources?.length ?? 0);
</script>

<template>
    <Head :title="`Recherche${query ? ` : ${query}` : ''}`" />
    <PublicLayout>
        <main class="max-w-container-max-width mx-auto px-4 md:px-8 py-12 space-y-8">
            <header>
                <p class="text-xs font-semibold uppercase tracking-widest text-primary">Recherche publique</p>
                <h1 class="text-2xl md:text-3xl font-bold text-on-surface mt-2">Résultats pour « {{ query || '...' }} »</h1>
                <p class="text-sm text-on-surface-variant mt-2">{{ total }} résultat(s)</p>
            </header>

            <section v-if="!query" class="bg-surface-container-low border border-outline-variant rounded-xl p-6 text-sm text-on-surface-variant">
                Saisissez un mot-clé dans la barre de recherche pour lancer une recherche.
            </section>

            <section v-else-if="!total" class="bg-surface-container-low border border-outline-variant rounded-xl p-6 text-sm text-on-surface-variant">
                Aucun résultat trouvé pour ce mot-clé.
            </section>

            <section v-if="results.pages?.length" class="space-y-3">
                <h2 class="text-xl font-bold">Pages</h2>
                <a v-for="item in results.pages" :key="`p-${item.id}`" :href="item.href" class="block bg-white border border-outline-variant rounded-xl p-4 hover:border-primary transition-colors">
                    <p class="font-bold text-on-surface">{{ item.title }}</p>
                    <p class="text-sm text-on-surface-variant">{{ item.excerpt }}</p>
                </a>
            </section>

            <section v-if="results.articles?.length" class="space-y-3">
                <h2 class="text-xl font-bold">Actualités</h2>
                <a v-for="item in results.articles" :key="`a-${item.id}`" :href="item.href" class="block bg-white border border-outline-variant rounded-xl p-4 hover:border-primary transition-colors">
                    <p class="font-bold text-on-surface">{{ item.title }}</p>
                    <p class="text-sm text-on-surface-variant">{{ item.excerpt }}</p>
                </a>
            </section>

            <section v-if="results.resources?.length" class="space-y-3">
                <h2 class="text-xl font-bold">Ressources</h2>
                <a v-for="item in results.resources" :key="`r-${item.id}`" :href="item.href" class="block bg-white border border-outline-variant rounded-xl p-4 hover:border-primary transition-colors">
                    <p class="font-bold text-on-surface">{{ item.title }}</p>
                    <p class="text-sm text-on-surface-variant">{{ item.excerpt }}</p>
                </a>
            </section>
        </main>
    </PublicLayout>
</template>
