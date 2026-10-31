import { ref } from 'vue';

/**
 * Toast court (icône Material + texte) propre à une page.
 * Le texte est affiché par interpolation Vue : un nom de pièce ou un message ne peut pas injecter de HTML.
 */
export function useToast(duration = 3000) {
    const toast = ref({ show: false, icon: '', text: '' });
    let timer = null;

    function showToast(icon, text) {
        window.clearTimeout(timer);
        toast.value = { show: true, icon, text };
        timer = window.setTimeout(() => {
            toast.value.show = false;
        }, duration);
    }

    return { toast, showToast };
}
