<script setup>
import { onMounted, onUnmounted, ref } from 'vue';

const SHOW_AFTER = 280;
const visible = ref(false);

function onScroll() {
    visible.value = window.scrollY > SHOW_AFTER;
}

onMounted(() => {
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
});

onUnmounted(() => {
    window.removeEventListener('scroll', onScroll);
});

function scrollToTop() {
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
</script>

<template>
    <a
        id="cama-anchor-top"
        href="#top"
        class="cama-anchor-top-btn"
        :class="{ 'is-visible': visible }"
        aria-label="Retour en haut de la page"
        @click.prevent="scrollToTop"
    >
        <span class="material-symbols-outlined" aria-hidden="true">keyboard_arrow_up</span>
    </a>
</template>
