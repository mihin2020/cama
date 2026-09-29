<script setup>
import { ref } from 'vue';

defineProps({
    items: { type: Array, default: () => [] },
    titleColor: { type: String, default: '#1b1c1c' },
    textColor: { type: String, default: '#5c403f' },
});

const activeTab = ref(0);
</script>

<template>
    <div>
        <div class="flex flex-wrap gap-1 border-b border-outline-variant" role="tablist">
            <button
                v-for="(item, index) in items"
                :key="index"
                type="button"
                role="tab"
                class="px-4 py-2.5 text-sm font-bold transition-colors border-b-2 -mb-px"
                :class="activeTab === index ? 'text-primary border-primary' : 'text-on-surface-variant border-transparent hover:text-on-surface'"
                :style="activeTab !== index ? { color: titleColor } : undefined"
                :aria-selected="activeTab === index"
                @click="activeTab = index"
            >
                {{ item.title || `Onglet ${index + 1}` }}
            </button>
        </div>
        <div
            v-for="(item, index) in items"
            v-show="activeTab === index"
            :key="`panel-${index}`"
            role="tabpanel"
            class="pt-4 text-sm leading-relaxed"
            :style="{ color: textColor }"
        >
            {{ item.content || '' }}
        </div>
        <p v-if="!items.length" class="text-sm text-on-surface-variant italic py-4">Aucun onglet configuré.</p>
    </div>
</template>
