<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { exportMembreFif } from '@/Composables/useCamaExport';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    history: Array,
});

const refPdf = ref('');
const toast = ref(null);
const pdfBusy = ref(false);

function showToast(message) {
    toast.value = message;
    setTimeout(() => {
        toast.value = null;
    }, 3000);
}

function downloadExport(routeName, format) {
    const url = route(routeName, { format });
    window.location.href = url;
    setTimeout(() => router.reload({ only: ['history'] }), 1500);
    showToast(`Génération de l'export (${format}) en cours…`);
}

async function exportPdf() {
    if (!refPdf.value.trim()) {
        showToast('Renseignez une référence de dossier.');
        return;
    }
    pdfBusy.value = true;
    try {
        const response = await fetch(route('admin.exports.dossier-pdf'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                Accept: 'application/json',
            },
            body: JSON.stringify({ ref: refPdf.value.trim() }),
        });
        const data = await response.json();
        if (!response.ok) {
            showToast(data.error ?? 'Dossier introuvable.');
            return;
        }
        await exportMembreFif(data.membre, data.assure);
        showToast(`Fiche PDF générée pour ${refPdf.value.trim()}.`);
        router.reload({ only: ['history'] });
    } catch {
        showToast('Export indisponible (librairie non chargée).');
    } finally {
        pdfBusy.value = false;
    }
}
</script>

<template>
    <Head title="Exports" />

    <AdminLayout active-nav="exports" title="Centre d'export" subtitle="Excel, CSV, PDF — tous les exports sont tracés">
        <div class="max-w-[1100px] w-full mx-auto">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
                <div class="assure-card p-5">
                    <div class="w-11 h-11 rounded-xl bg-secondary/10 text-secondary flex items-center justify-center mb-3">
                        <span class="material-symbols-outlined">table_view</span>
                    </div>
                    <h3 class="font-title-lg text-title-lg mb-1">Membres enrôlés</h3>
                    <p class="text-xs text-on-surface-variant mb-4">Export Excel (.xlsx) ou CSV avec les données actuelles.</p>
                    <div class="flex gap-2">
                        <button type="button" class="flex-1 px-3 py-2 rounded-lg bg-secondary text-on-secondary text-xs font-bold" @click="downloadExport('admin.exports.membres', 'Excel')">.xlsx</button>
                        <button type="button" class="flex-1 px-3 py-2 rounded-lg border border-outline text-xs font-bold" @click="downloadExport('admin.exports.membres', 'CSV')">.csv</button>
                    </div>
                </div>

                <div class="assure-card p-5">
                    <div class="w-11 h-11 rounded-xl bg-primary/10 text-primary flex items-center justify-center mb-3">
                        <span class="material-symbols-outlined">folder_shared</span>
                    </div>
                    <h3 class="font-title-lg text-title-lg mb-1">Dossiers d'enrôlement</h3>
                    <p class="text-xs text-on-surface-variant mb-4">Liste des dossiers, statuts et délais de traitement.</p>
                    <div class="flex gap-2">
                        <button type="button" class="flex-1 px-3 py-2 rounded-lg bg-secondary text-on-secondary text-xs font-bold" @click="downloadExport('admin.exports.dossiers', 'Excel')">.xlsx</button>
                        <button type="button" class="flex-1 px-3 py-2 rounded-lg border border-outline text-xs font-bold" @click="downloadExport('admin.exports.dossiers', 'CSV')">.csv</button>
                    </div>
                </div>

                <div class="assure-card p-5">
                    <div class="w-11 h-11 rounded-xl bg-tertiary/10 text-tertiary flex items-center justify-center mb-3">
                        <span class="material-symbols-outlined">description</span>
                    </div>
                    <h3 class="font-title-lg text-title-lg mb-1">Fiche dossier (PDF)</h3>
                    <p class="text-xs text-on-surface-variant mb-4">Archivage papier d'un dossier individuel.</p>
                    <div class="flex gap-2">
                        <input v-model="refPdf" class="flex-1 px-3 py-2 text-xs border border-outline-variant rounded-lg" placeholder="Référence (ex: CAMA-2025-88213)" type="text" />
                        <button type="button" class="px-3 py-2 rounded-lg bg-tertiary text-on-tertiary text-xs font-bold disabled:opacity-50" :disabled="pdfBusy" @click="exportPdf">PDF</button>
                    </div>
                </div>
            </div>

            <div class="assure-card overflow-hidden">
                <div class="px-5 py-4 border-b border-outline-variant bg-surface-container-low">
                    <h3 class="font-title-lg text-title-lg">Historique de mes exports</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="bg-surface-container-low text-on-surface-variant text-[11px] uppercase tracking-wide">
                                <th class="px-5 py-3 font-medium">Export</th>
                                <th class="px-5 py-3 font-medium">Format</th>
                                <th class="px-5 py-3 font-medium hidden md:table-cell">Par</th>
                                <th class="px-5 py-3 font-medium">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant">
                            <tr v-for="e in history" :key="e.id" class="hover:bg-surface-container-low transition-colors">
                                <td class="px-5 py-3 text-xs font-bold">{{ e.nom }}</td>
                                <td class="px-5 py-3 text-xs">{{ e.format }}</td>
                                <td class="px-5 py-3 text-xs text-on-surface-variant hidden md:table-cell">{{ e.par }}</td>
                                <td class="px-5 py-3 text-xs text-on-surface-variant">{{ e.date }}</td>
                            </tr>
                            <tr v-if="!history.length">
                                <td colspan="4" class="px-5 py-10 text-center text-on-surface-variant text-sm">Aucun export enregistré.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div v-if="toast" class="fixed bottom-6 right-6 z-[70]">
            <div class="bg-on-background text-white px-5 py-3 rounded-lg shadow-xl flex items-center gap-2 font-label-md text-label-md">
                <span class="material-symbols-outlined">download</span> {{ toast }}
            </div>
        </div>
    </AdminLayout>
</template>
