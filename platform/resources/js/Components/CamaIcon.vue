<script setup>
import { computed } from 'vue';
import { isEmojiIcon } from '@/Utils/camaIconCatalog';

const props = defineProps({
    name: { type: String, default: '' },
    size: { type: String, default: 'md' },
});

const isEmoji = computed(() => isEmojiIcon(props.name));

const materialSizes = {
    sm: 'text-[18px]',
    md: 'text-[22px]',
    lg: 'text-4xl',
};

const emojiSizes = {
    sm: 'text-base',
    md: 'text-xl',
    lg: 'text-4xl',
};
</script>

<template>
    <span
        v-if="isEmoji"
        :class="emojiSizes[size] ?? emojiSizes.md"
        role="img"
        aria-hidden="true"
    >{{ name }}</span>
    <span
        v-else-if="name"
        class="material-symbols-outlined"
        :class="materialSizes[size] ?? materialSizes.md"
    >{{ name }}</span>
</template>
