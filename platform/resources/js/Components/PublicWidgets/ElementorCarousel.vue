<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    items: { type: Array, default: () => [] },
    gap: { type: Number, default: 24 },
    slidesPerView: { type: Number, default: 1 },
});

const activeIndex = ref(0);
const trackRef = ref(null);

const slideWidth = computed(() => {
    const perView = Math.max(1, Math.min(props.slidesPerView, 3));
    return `calc((100% - ${(perView - 1) * props.gap}px) / ${perView})`;
});

function goTo(index) {
    if (!props.items.length) return;
    activeIndex.value = ((index % props.items.length) + props.items.length) % props.items.length;
    scrollToActive();
}

function scrollToActive() {
    const track = trackRef.value;
    if (!track) return;
    const slide = track.children[activeIndex.value];
    slide?.scrollIntoView({ behavior: 'smooth', inline: 'start', block: 'nearest' });
}

function prev() {
    goTo(activeIndex.value - 1);
}

function next() {
    goTo(activeIndex.value + 1);
}
</script>

<template>
    <div class="relative">
        <div
            ref="trackRef"
            class="flex overflow-x-auto snap-x snap-mandatory scroll-smooth no-scrollbar pb-2"
            :style="{ gap: `${gap}px` }"
        >
            <article
                v-for="(item, index) in items"
                :key="index"
                class="snap-start shrink-0 bg-white border border-outline-variant rounded-xl overflow-hidden shadow-sm"
                :style="{ width: slideWidth, minWidth: '240px' }"
            >
                <img
                    :src="item.image || '/images/CAMA_1.jfif'"
                    :alt="item.title || ''"
                    class="h-40 w-full object-cover"
                    loading="lazy"
                />
                <div class="p-4">
                    <h3 class="font-bold text-on-surface">{{ item.title || '' }}</h3>
                    <p class="text-sm text-on-surface-variant mt-1">{{ item.text || '' }}</p>
                </div>
            </article>
        </div>

        <template v-if="items.length > 1">
            <button
                type="button"
                class="absolute left-0 top-1/2 -translate-y-1/2 -translate-x-1/2 w-10 h-10 rounded-full bg-white border border-outline-variant shadow-md flex items-center justify-center hover:border-primary hidden md:flex"
                aria-label="Slide précédent"
                @click="prev"
            >
                <span class="material-symbols-outlined text-[20px]">chevron_left</span>
            </button>
            <button
                type="button"
                class="absolute right-0 top-1/2 -translate-y-1/2 translate-x-1/2 w-10 h-10 rounded-full bg-white border border-outline-variant shadow-md flex items-center justify-center hover:border-primary hidden md:flex"
                aria-label="Slide suivant"
                @click="next"
            >
                <span class="material-symbols-outlined text-[20px]">chevron_right</span>
            </button>
            <div class="flex justify-center gap-1.5 mt-4">
                <button
                    v-for="(_, index) in items"
                    :key="`dot-${index}`"
                    type="button"
                    class="h-2 rounded-full transition-all"
                    :class="activeIndex === index ? 'w-6 bg-primary' : 'w-2 bg-outline-variant'"
                    :aria-label="`Aller au slide ${index + 1}`"
                    @click="goTo(index)"
                />
            </div>
        </template>

        <p v-if="!items.length" class="text-sm text-on-surface-variant italic py-8 text-center">Aucune slide dans le carousel.</p>
    </div>
</template>
