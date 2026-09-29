<script setup>
defineProps({
    size: { type: String, default: 'md' },
    spinnerClass: { type: String, default: '' },
});

const sizes = {
    sm: 'w-4 h-4 border-2',
    md: 'w-8 h-8 border-[3px]',
    lg: 'w-10 h-10 border-[3px]',
};
</script>

<template>
    <span
        class="inline-block rounded-full border-primary/25 border-t-primary animate-spin shrink-0"
        :class="[sizes[size] ?? sizes.md, spinnerClass]"
        role="status"
        aria-label="Chargement"
    />
</template>
