<script setup>
import CamaSpinner from '@/Components/CamaSpinner.vue';

defineProps({
    loading: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    type: { type: String, default: 'button' },
    variant: { type: String, default: 'primary' },
    loadingText: { type: String, default: 'Enregistrement…' },
    buttonClass: { type: String, default: '' },
});

const variants = {
    primary: 'bg-primary text-on-primary hover:opacity-90',
    secondary: 'border border-outline-variant bg-white text-on-surface hover:bg-surface-container-low',
    success: 'bg-secondary text-on-secondary hover:opacity-90',
    danger: 'bg-error text-on-primary hover:opacity-90',
    dark: 'bg-on-background text-white hover:opacity-90',
};

const spinnerClass = {
    primary: 'border-t-white border-white/30',
    secondary: '',
    success: 'border-t-white border-white/30',
    danger: 'border-t-white border-white/30',
    dark: 'border-t-white border-white/30',
};
</script>

<template>
    <button
        :type="type"
        class="inline-flex items-center justify-center gap-2 px-4 py-2 text-xs font-bold rounded-lg transition-opacity disabled:opacity-60 disabled:cursor-not-allowed"
        :class="[variants[variant] ?? variants.primary, buttonClass]"
        :disabled="loading || disabled"
    >
        <template v-if="loading">
            <CamaSpinner size="sm" :class="spinnerClass[variant] ?? spinnerClass.primary" />
            <span>{{ loadingText }}</span>
        </template>
        <slot v-else />
    </button>
</template>
