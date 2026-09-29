<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { socialIcon } from '@/Data/socialNetworks.js';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

const props = defineProps({
    article: {
        type: Object,
        required: true,
    },
});

const page = usePage();
const shareUrl = ref('');
const copied = ref(false);

onMounted(() => {
    shareUrl.value = window.location.href;
});

const shareTargets = computed(() => {
    const u = encodeURIComponent(shareUrl.value || page.url);
    const t = encodeURIComponent(props.article.title);

    return [
        { key: 'facebook', label: 'Partager sur Facebook', href: `https://www.facebook.com/sharer/sharer.php?u=${u}` },
        { key: 'x', label: 'Partager sur X', href: `https://twitter.com/intent/tweet?url=${u}&text=${t}` },
        { key: 'linkedin', label: 'Partager sur LinkedIn', href: `https://www.linkedin.com/sharing/share-offsite/?url=${u}` },
        { key: 'whatsapp', label: 'Partager sur WhatsApp', href: `https://wa.me/?text=${t}%20${u}` },
        { key: 'telegram', label: 'Partager sur Telegram', href: `https://t.me/share/url?url=${u}&text=${t}` },
    ];
});

const mailHref = computed(
    () => `mailto:?subject=${encodeURIComponent(props.article.title)}&body=${encodeURIComponent(shareUrl.value || page.url)}`,
);

async function copyLink() {
    try {
        await navigator.clipboard.writeText(shareUrl.value || page.url);
        copied.value = true;
        window.setTimeout(() => {
            copied.value = false;
        }, 2000);
    } catch (e) {
        // presse-papiers indisponible : on ignore silencieusement
    }
}
</script>

<template>
    <Head :title="article.title" />

    <PublicLayout>
        <main class="flex-grow py-stack-lg">
            <div class="max-w-container-max-width mx-auto px-4 md:px-margin-desktop">
                <article class="max-w-3xl mx-auto">
                    <Link class="inline-flex items-center gap-1 text-sm font-semibold text-primary mb-6 hover:underline" href="/actualites">
                        <span class="material-symbols-outlined text-[18px]">arrow_back</span> Toutes les actualités
                    </Link>
                    <div class="flex flex-wrap items-center gap-3 mb-4">
                        <span class="bg-primary text-on-primary px-3 py-1 rounded text-caption font-bold uppercase tracking-wider">{{ article.category }}</span>
                        <span v-if="article.publishedAt" class="text-on-surface-variant text-sm">{{ article.publishedAt }}</span>
                        <span v-if="article.author" class="text-on-surface-variant text-sm">· {{ article.author }}</span>
                    </div>
                    <h1 class="font-headline-lg text-headline-lg text-on-surface mb-6">{{ article.title }}</h1>
                    <div v-if="article.imageSrc" class="rounded-xl overflow-hidden mb-8 border border-outline-variant">
                        <img alt="" class="w-full max-h-[420px] object-cover" :src="article.imageSrc" />
                    </div>
                    <p v-if="article.excerpt" class="font-body-lg text-body-lg text-on-surface-variant mb-8 border-l-4 border-primary pl-4">{{ article.excerpt }}</p>
                    <div class="prose-article font-body-md text-body-md text-on-surface space-y-4 leading-relaxed" v-html="article.bodyHtml" />
                    <div class="mt-10 pt-8 border-t border-outline-variant flex flex-wrap items-center gap-3">
                        <span class="text-sm font-semibold text-on-surface-variant mr-1">Partager :</span>
                        <a
                            v-for="target in shareTargets"
                            :key="target.key"
                            class="w-10 h-10 rounded-full border border-outline-variant flex items-center justify-center text-on-surface-variant hover:text-on-primary hover:bg-primary hover:border-primary transition-colors"
                            :href="target.href"
                            :aria-label="target.label"
                            :title="target.label"
                            rel="noopener"
                            target="_blank"
                        >
                            <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24"><path :d="socialIcon(target.key)" /></svg>
                        </a>
                        <a
                            class="w-10 h-10 rounded-full border border-outline-variant flex items-center justify-center text-on-surface-variant hover:text-on-primary hover:bg-primary hover:border-primary transition-colors"
                            :href="mailHref"
                            aria-label="Partager par e-mail"
                            title="Partager par e-mail"
                        >
                            <span class="material-symbols-outlined text-[20px]">mail</span>
                        </a>
                        <button
                            type="button"
                            class="w-10 h-10 rounded-full border border-outline-variant flex items-center justify-center transition-colors"
                            :class="copied ? 'text-on-primary bg-primary border-primary' : 'text-on-surface-variant hover:text-on-primary hover:bg-primary hover:border-primary'"
                            :aria-label="copied ? 'Lien copié' : 'Copier le lien'"
                            :title="copied ? 'Lien copié !' : 'Copier le lien'"
                            @click="copyLink"
                        >
                            <span class="material-symbols-outlined text-[20px]">{{ copied ? 'check' : 'link' }}</span>
                        </button>
                    </div>
                </article>
            </div>
        </main>
    </PublicLayout>
</template>
