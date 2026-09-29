<script setup>
defineProps({
    open: { type: Boolean, default: false },
    versions: { type: Array, default: () => [] },
    permissions: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['close', 'preview', 'compare', 'duplicate', 'restore']);
</script>

<template>
    <div v-if="open" class="fixed inset-0 bg-black/60 z-[86] flex items-center justify-center p-6">
        <div class="bg-white rounded-xl w-full max-w-2xl max-h-[85vh] flex flex-col overflow-hidden shadow-2xl">
            <div class="px-4 py-3 border-b border-outline-variant flex items-center justify-between">
                <div>
                    <span class="text-sm font-bold flex items-center gap-2"><span class="material-symbols-outlined text-[18px] text-primary">history</span>Versions de la page</span>
                    <p class="text-[10px] text-on-surface-variant">Chaque enregistrement et publication crée une version restaurable.</p>
                </div>
                <button class="text-on-surface-variant hover:text-primary" type="button" @click="emit('close')"><span class="material-symbols-outlined">close</span></button>
            </div>
            <div v-if="versions.length" class="flex-1 overflow-auto divide-y divide-outline-variant">
                <div v-for="version in versions" :key="version.id" class="p-4 flex items-center justify-between gap-4">
                    <div class="min-w-0">
                        <p class="text-sm font-bold text-on-surface">
                            Version {{ version.versionNumber }}
                            <span class="text-[10px] uppercase tracking-wide text-primary bg-primary/10 px-2 py-0.5 rounded-full">{{ version.eventLabel }}</span>
                        </p>
                        <p class="text-xs text-on-surface-variant truncate">{{ version.title }} · {{ version.sectionCount }} section(s)</p>
                        <p v-if="version.comment" class="text-[11px] text-on-surface mt-1 truncate">“{{ version.comment }}”</p>
                        <p class="text-[10px] text-on-surface-variant">Créée le {{ version.createdAt }} par {{ version.createdBy }}</p>
                    </div>
                    <div class="flex flex-wrap justify-end gap-1 shrink-0">
                        <button class="px-2.5 py-1.5 rounded-lg text-xs font-bold border border-outline-variant" type="button" @click="emit('preview', version)">Aperçu</button>
                        <button class="px-2.5 py-1.5 rounded-lg text-xs font-bold border border-outline-variant" type="button" @click="emit('compare', version)">Comparer</button>
                        <button v-if="permissions.canDuplicateVersions" class="px-2.5 py-1.5 rounded-lg text-xs font-bold border border-outline-variant" type="button" @click="emit('duplicate', version)">Dupliquer</button>
                        <button v-if="permissions.canRestoreVersions" class="px-2.5 py-1.5 rounded-lg text-xs font-bold border border-primary text-primary" type="button" @click="emit('restore', version)">Restaurer</button>
                    </div>
                </div>
            </div>
            <div v-else class="p-10 text-center text-sm text-on-surface-variant">
                Aucune version pour l’instant. Cliquez sur Enregistrer ou Publier pour créer le premier snapshot.
            </div>
        </div>
    </div>
</template>
