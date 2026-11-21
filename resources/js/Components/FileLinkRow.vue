<script setup>
/**
 * Un fichier envoyé par un assuré : on peut le visualiser (ouverture dans un nouvel onglet) et le
 * télécharger. Les adresses viennent de routes protégées (droits vérifiés par le serveur).
 */
defineProps({
    label: { type: String, required: true },
    filename: { type: String, default: null },
    viewUrl: { type: String, required: true },
    downloadUrl: { type: String, required: true },
});
</script>

<template>
    <div class="flex items-center justify-between gap-2 bg-white rounded-lg p-3 border border-outline-variant">
        <span class="flex items-center gap-2 min-w-0">
            <span class="material-symbols-outlined text-[18px] text-primary shrink-0">description</span>
            <span class="min-w-0">
                <span class="text-xs font-bold block">{{ label }}</span>
                <span v-if="filename" class="text-[10px] text-on-surface-variant break-all">{{ filename }}</span>
            </span>
        </span>
        <span class="flex items-center gap-1 shrink-0">
            <a
                :href="viewUrl"
                class="w-9 h-9 flex items-center justify-center rounded-lg hover:bg-surface-container-low text-primary"
                :aria-label="`Visualiser ${label}`"
                rel="noopener"
                target="_blank"
                title="Visualiser"
            >
                <span class="material-symbols-outlined text-[18px]">visibility</span>
            </a>
            <a
                :href="downloadUrl"
                class="w-9 h-9 flex items-center justify-center rounded-lg hover:bg-surface-container-low text-on-surface-variant"
                :aria-label="`Télécharger ${label}`"
                title="Télécharger"
            >
                <span class="material-symbols-outlined text-[18px]">download</span>
            </a>
        </span>
    </div>
</template>
