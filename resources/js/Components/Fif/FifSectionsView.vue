<script setup>
/** Valeurs des champs ajoutés par l'administration (formulaire FIF configurable), en lecture seule. */
defineProps({
    // [{ titre, rows: [{ label, value }] }] — voir FifSchemaService::display().
    sections: { type: Array, default: () => [] },
    // (code, inline) => adresse du fichier chargé pour ce champ ; sans elle, seul le nom est affiché.
    fileUrl: { type: Function, default: null },
});
</script>

<template>
    <div v-if="sections?.length" class="space-y-4">
        <div v-for="section in sections" :key="section.titre">
            <h3 class="text-xs font-bold uppercase tracking-wide text-on-surface-variant mb-2 flex items-center gap-2">
                <span class="material-symbols-outlined text-[16px] text-primary">fact_check</span> {{ section.titre }}
            </h3>
            <div class="grid grid-cols-2 gap-2 text-xs">
                <div v-for="row in section.rows" :key="row.code" class="bg-white rounded-lg p-3 border border-outline-variant">
                    <p class="text-[10px] text-on-surface-variant uppercase">{{ row.label }}</p>
                    <p class="font-bold break-words">{{ row.value || 'Non renseigné' }}</p>
                    <p v-if="row.fichier && fileUrl" class="mt-1 flex items-center gap-3 font-semibold">
                        <a :href="fileUrl(row.code, true)" class="text-primary hover:underline inline-flex items-center gap-1" rel="noopener" target="_blank">
                            <span class="material-symbols-outlined text-[14px]">visibility</span> Visualiser
                        </a>
                        <a :href="fileUrl(row.code, false)" class="text-primary hover:underline inline-flex items-center gap-1">
                            <span class="material-symbols-outlined text-[14px]">download</span> Télécharger
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
