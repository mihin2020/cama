<script setup>
import { computed } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, default: 'Confirmation' },
    message: { type: String, default: '' },
    confirmLabel: { type: String, default: 'Confirmer' },
    cancelLabel: { type: String, default: 'Annuler' },
    variant: { type: String, default: 'danger' },
    alertOnly: { type: Boolean, default: false },
});

const emit = defineEmits(['confirm', 'cancel']);

const icon = computed(() => ({
    danger: 'delete_forever',
    warning: 'warning',
    info: 'info',
}[props.variant] ?? 'help'));

const iconWrapClass = computed(() => ({
    danger: 'bg-error/10 text-error',
    warning: 'bg-tertiary/10 text-tertiary',
    info: 'bg-primary/10 text-primary',
}[props.variant] ?? 'bg-primary/10 text-primary'));

const confirmBtnClass = computed(() => {
    if (props.alertOnly) return 'bg-primary text-on-primary';
    if (props.variant === 'warning') return 'bg-tertiary text-white';
    if (props.variant === 'info') return 'bg-primary text-on-primary';
    return 'bg-error text-on-primary';
});

function onConfirm() {
    emit('confirm');
}

function onCancel() {
    emit('cancel');
}
</script>

<template>
    <Teleport to="body">
        <div
            v-if="show"
            class="fixed inset-0 bg-black/60 z-[100] flex items-center justify-center p-4"
            @click.self="onCancel"
        >
            <div
                class="bg-white rounded-2xl w-full max-w-md shadow-2xl overflow-hidden"
                role="alertdialog"
                aria-modal="true"
                :aria-labelledby="title ? 'confirm-modal-title' : undefined"
            >
                <div class="p-6">
                    <div class="flex items-start gap-4">
                        <div
                            class="w-11 h-11 rounded-full flex items-center justify-center shrink-0"
                            :class="iconWrapClass"
                        >
                            <span class="material-symbols-outlined text-[22px]">{{ icon }}</span>
                        </div>
                        <div class="flex-1 min-w-0 pt-0.5">
                            <h3 id="confirm-modal-title" class="font-semibold text-sm text-on-surface mb-1">
                                {{ title }}
                            </h3>
                            <p v-if="message" class="text-xs text-on-surface-variant leading-relaxed">
                                {{ message }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-2 px-6 py-4 border-t border-outline-variant bg-surface-container-low/50">
                    <button
                        v-if="!alertOnly"
                        class="px-4 py-2 text-xs font-bold border border-outline-variant rounded-lg bg-white"
                        type="button"
                        @click="onCancel"
                    >
                        {{ cancelLabel }}
                    </button>
                    <button
                        class="px-4 py-2 text-xs font-bold rounded-lg"
                        :class="confirmBtnClass"
                        type="button"
                        @click="onConfirm"
                    >
                        {{ alertOnly ? (confirmLabel === 'Confirmer' ? 'OK' : confirmLabel) : confirmLabel }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
