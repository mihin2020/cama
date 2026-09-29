<script setup>
import { ref } from 'vue';

const props = defineProps({
    items: { type: Array, default: () => [] },
    titleColor: { type: String, default: '#1b1c1c' },
    textColor: { type: String, default: '#5c403f' },
});

const openIndex = ref(0);

function toggle(index) {
    openIndex.value = openIndex.value === index ? -1 : index;
}
</script>

<template>
    <div class="space-y-2">
        <div
            v-for="(item, index) in items"
            :key="index"
            class="border border-outline-variant rounded-xl bg-white overflow-hidden"
        >
            <button
                type="button"
                class="w-full flex items-center justify-between gap-3 px-4 py-3.5 text-left font-bold text-sm hover:bg-surface-container-low transition-colors"
                :style="{ color: titleColor }"
                :aria-expanded="openIndex === index"
                @click="toggle(index)"
            >
                <span>{{ item.title || `Élément ${index + 1}` }}</span>
                <span
                    class="material-symbols-outlined text-[20px] text-on-surface-variant transition-transform shrink-0"
                    :class="{ 'rotate-180': openIndex === index }"
                >expand_more</span>
            </button>
            <div
                v-show="openIndex === index"
                class="px-4 pb-4 text-sm leading-relaxed border-t border-outline-variant pt-3"
                :style="{ color: textColor }"
            >
                {{ item.content || '' }}
            </div>
        </div>
        <p v-if="!items.length" class="text-sm text-on-surface-variant italic py-4 text-center">Aucun élément dans l'accordéon.</p>
    </div>
</template>
