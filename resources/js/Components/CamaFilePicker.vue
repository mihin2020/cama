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
    <div class="space-y-2">
        <div class="flex flex-wrap items-center gap-2">
            <button
                type="button"
                class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-bold bg-primary text-on-primary rounded-lg hover:opacity-90 transition-opacity disabled:opacity-50"
                :disabled="disabled"
                @click="openPicker"
            >
                <span class="material-symbols-outlined text-[16px]">upload</span>
                {{ label }}
            </button>

            <span
                v-if="fileName"
                class="inline-flex items-center gap-1 max-w-[220px] px-2.5 py-1 rounded-lg bg-surface-container-low text-[11px] text-on-surface-variant truncate"
            >
                <span class="material-symbols-outlined text-[14px] shrink-0">attach_file</span>
                <span class="truncate">{{ fileName }}</span>
            </span>

            <slot name="actions" />
        </div>

        <p v-if="hint" class="text-[10px] text-on-surface-variant">{{ hint }}</p>

        <input
            ref="inputRef"
            type="file"
            class="hidden"
            :accept="accept"
            :disabled="disabled"
            @change="onChange"
        />
    </div>
</template>
