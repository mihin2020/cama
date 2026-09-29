<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    title: { type: String, default: '' },
    articles: { type: Array, default: () => [] },
    columns: { type: Number, default: 3 },
    variant: { type: String, default: 'posts' },
});

const gridClass = computed(() => {
    const cols = Math.min(Math.max(props.columns, 1), 4);
    return {
        1: 'grid-cols-1',
        2: 'grid-cols-1 md:grid-cols-2',
        3: 'grid-cols-1 md:grid-cols-2 lg:grid-cols-3',
        4: 'grid-cols-1 md:grid-cols-2 lg:grid-cols-4',
    }[cols];
});

function articleImage(article) {
    return article.image_src || article.imageSrc || '/images/CAMA_8.jfif';
}

function articleHref(article) {
    return article.href || (article.slug ? `/actualites/${article.slug}` : '/actualites');
}

function articleDate(article) {
    return article.published_at || article.date || '';
}

function articleCategory(article) {
    return article.category || 'Actualité';
}
</script>

<template>
    <div>
        <h3 v-if="title && variant === 'posts'" class="font-bold text-xl mb-4 text-on-surface">{{ title }}</h3>

        <div v-if="articles.length" class="grid gap-5" :class="gridClass">
            <article
                v-for="article in articles"
                :key="article.id ?? article.slug ?? article.title"
                class="bg-white border border-outline-variant rounded-xl overflow-hidden shadow-sm hover:border-primary/30 transition-colors group"
            >
                <Link :href="articleHref(article)" class="block">
                    <img
                        :src="articleImage(article)"
                        :alt="article.title"
                        class="h-44 w-full object-cover group-hover:scale-[1.02] transition-transform duration-300"
                        loading="lazy"
                    />
                    <div class="p-5">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="text-[10px] uppercase font-bold text-primary">{{ articleCategory(article) }}</span>
                            <span v-if="articleDate(article)" class="text-[10px] text-on-surface-variant">{{ articleDate(article) }}</span>
                        </div>
                        <h4 class="font-bold text-on-surface leading-snug group-hover:text-primary transition-colors">{{ article.title }}</h4>
                        <p v-if="article.excerpt" class="text-sm text-on-surface-variant mt-2 line-clamp-2">{{ article.excerpt }}</p>
                        <span class="inline-flex items-center gap-1 text-primary text-xs font-bold mt-3">
                            Lire la suite
                            <span class="material-symbols-outlined text-[14px]">arrow_forward</span>
                        </span>
                    </div>
                </Link>
            </article>
        </div>

        <p v-else class="text-sm text-on-surface-variant italic py-8 text-center bg-surface-container-low rounded-xl border border-dashed border-outline-variant">
            Aucun article publié pour le moment.
        </p>
    </div>
</template>
