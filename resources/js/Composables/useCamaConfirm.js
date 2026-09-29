import { reactive } from 'vue';

const state = reactive({
    show: false,
    title: '',
    message: '',
    confirmLabel: 'Confirmer',
    cancelLabel: 'Annuler',
    variant: 'danger',
    alertOnly: false,
});

let onConfirmCallback = null;

export function useCamaConfirm() {
    function askConfirm({
        title,
        message = '',
        confirmLabel = 'Confirmer',
        cancelLabel = 'Annuler',
        variant = 'danger',
        alertOnly = false,
        onConfirm = null,
    }) {
        state.title = title;
        state.message = message;
        state.confirmLabel = confirmLabel;
        state.cancelLabel = cancelLabel;
        state.variant = variant;
        state.alertOnly = alertOnly;
        onConfirmCallback = onConfirm;
        state.show = true;
    }

    function confirm() {
        state.show = false;
        const callback = onConfirmCallback;
        onConfirmCallback = null;
        callback?.();
    }

    function cancel() {
        state.show = false;
        onConfirmCallback = null;
    }

    return {
        confirmState: state,
        askConfirm,
        confirm,
        cancel,
    };
}
