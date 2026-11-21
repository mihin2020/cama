<script setup>
/**
 * Champ d'envoi de fichier : même apparence que les autres champs du formulaire
 * (cadre, bouton « Choisir un fichier » à gauche, nom du fichier à droite).
 * L'événement `change` est celui de l'input natif.
 */
defineProps({
    accept: { type: String, default: '.pdf,application/pdf' },
    fileName: { type: String, default: '' },
    label: { type: String, default: 'Choisir un fichier' },
    id: { type: String, default: undefined },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['change']);
</script>

<template>
    <label
        class="flex items-center w-full border border-outline-variant rounded-lg bg-white overflow-hidden transition-colors focus-within:ring-2 focus-within:ring-primary"
        :class="disabled ? 'opacity-60' : 'cursor-pointer hover:border-primary'"
    >
        <span class="shrink-0 px-3 py-2 text-sm font-medium bg-surface-container-low border-r border-outline-variant text-on-surface">{{ label }}</span>
        <span class="flex-1 min-w-0 truncate px-3 text-sm" :class="fileName ? 'text-on-surface' : 'text-on-surface-variant'">
            {{ fileName || 'Aucun fichier choisi' }}
        </span>
        <input :id="id" type="file" class="sr-only" :accept="accept" :disabled="disabled" @change="emit('change', $event)" />
    </label>
</template>
