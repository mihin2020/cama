<script setup>
import { Link } from '@inertiajs/vue3';
import { onMounted, ref, watch } from 'vue';

const COOKIE_KEY = 'cama_cookie_consent';
const visible = ref(false);

function updateAnchorOffset() {
    if (visible.value) {
        document.documentElement.style.setProperty('--cama-anchor-bottom', '7.5rem');
    } else {
        document.documentElement.style.removeProperty('--cama-anchor-bottom');
    }
}

onMounted(() => {
    if (!localStorage.getItem(COOKIE_KEY)) {
        visible.value = true;
    }
});

watch(visible, updateAnchorOffset, { immediate: true });

function hide(choice) {
    localStorage.setItem(COOKIE_KEY, choice);
    visible.value = false;
}
</script>

<template>
    <div
        v-if="visible"
        id="site-cookie-bar"
        class="is-visible"
        role="dialog"
        aria-label="Consentement cookies"
    >
        <div class="max-w-container-max-width mx-auto px-4 md:px-margin-desktop py-4 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div class="flex items-start gap-3 flex-1">
                <span class="material-symbols-outlined text-primary shrink-0 mt-0.5">cookie</span>
                <p class="text-sm text-on-surface leading-snug">
                    Ce site utilise des cookies essentiels au fonctionnement et, avec votre accord, des cookies de mesure d'audience.
                    <Link href="/mention_legales#cookies" class="text-primary font-semibold underline">En savoir plus</Link>
                </p>
            </div>
            <div class="flex gap-2 shrink-0 w-full sm:w-auto">
                <button
                    type="button"
                    class="flex-1 sm:flex-none px-4 py-2 text-sm font-bold border border-outline-variant rounded-lg hover:bg-surface-container-low"
                    @click="hide('essential')"
                >
                    Essentiels uniquement
                </button>
                <button
                    type="button"
                    class="flex-1 sm:flex-none px-4 py-2 text-sm font-bold bg-primary text-on-primary rounded-lg hover:opacity-90"
                    @click="hide('all')"
                >
                    Tout accepter
                </button>
            </div>
        </div>
    </div>
</template>
