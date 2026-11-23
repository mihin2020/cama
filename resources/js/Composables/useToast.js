import { notify } from '@/Composables/useFlashToast';

/** Ancienne signature (icône, texte) conservée pour les pages de l'espace assuré : passe par le toast commun. */
export function useToast() {
    return { showToast: (icon, text) => notify(text, icon) };
}
