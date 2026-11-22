<script setup>
import { ref } from 'vue';

defineProps({
    accept: { type: String, default: 'image/*' },
    label: { type: String, default: 'Choisir un fichier' },
    hint: { type: String, default: '' },
    fileName: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['change']);

const inputRef = ref(null);

function openPicker() {
    inputRef.value?.click();
}

function clear() {
    if (inputRef.value) {
        inputRef.value.value = '';
    }
}

defineExpose({ clear });

function onChange(event) {
    emit('change', event);
}
</script>

<template>
    <div class="space-y-1.5">
        <div class="flex flex-wrap items-center gap-2">
            <label
                class="flex-1 min-w-[220px] flex items-center border border-outline-variant rounded-lg bg-white overflow-hidden transition-colors focus-within:ring-2 focus-within:ring-primary"
                :class="disabled ? 'opacity-60' : 'cursor-pointer hover:border-primary'"
            >
                <span class="shrink-0 px-3 py-2 text-sm font-medium bg-surface-container-low border-r border-outline-variant text-on-surface">{{ label }}</span>
                <span class="flex-1 min-w-0 truncate px-3 text-sm" :class="fileName ? 'text-on-surface' : 'text-on-surface-variant'">
                    {{ fileName || 'Aucun fichier choisi' }}
                </span>
                <input
                    ref="inputRef"
                    type="file"
                    class="sr-only"
                    :accept="accept"
                    :disabled="disabled"
                    @change="onChange"
                />
            </label>

            <slot name="actions" />
        </div>

        <p v-if="hint" class="text-[11px] text-on-surface-variant">{{ hint }}</p>
    </div>
</template>
