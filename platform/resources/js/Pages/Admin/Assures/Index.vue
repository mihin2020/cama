<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { exportFamilleFif, exportFamilleZip } from '@/Composables/useCamaExport';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    assures: Array,
    filters: Object,
    statutOptions: Array,
    pagination: { type: Object, default: () => ({ currentPage: 1, lastPage: 1, perPage: 20, total: 0 }) },
});

const page = usePage();
const selected = ref(null);
const exportBusy = ref(false);
const toast = ref(null);

const search = ref(props.filters.search ?? '');
const statut = ref(props.filters.statut ?? 'tous');

const canManage = computed(() => {
    const role = page.props.auth?.admin?.role;
    return ['gestionnaire', 'superviseur', 'administrateur'].includes(role);
});

const STATUS_COMPTE_STYLES = {
    Actif: 'bg-secondary text-on-secondary',
    'En attente de validation': 'bg-tertiary text-on-tertiary',
    Désactivé: 'bg-error/10 text-error',
    Refusé: 'bg-error/10 text-error',
};

const STATUS_DOSSIER_STYLES = {
    Brouillon: 'bg-surface-container-high text-on-surface-variant',
    Soumis: 'bg-primary/10 text-primary',
    'En instruction': 'bg-tertiary/10 text-tertiary',
    'Pièce manquante demandée': 'bg-tertiary text-on-tertiary',
    Validé: 'bg-secondary text-on-secondary',
    Refusé: 'bg-error text-on-error',
};

watch([search, statut], () => {
    router.get(
        route('admin.assures'),
        { search: search.value, statut: statut.value, page: 1 },
        { preserveState: true, replace: true },
    );
});

function goToPage(page) {
    if (page < 1 || page > props.pagination.lastPage) return;
    router.get(
        route('admin.assures'),
        { search: search.value, statut: statut.value, page },
        { preserveState: true, replace: true },
    );
}

function openDetail(assure) {
    selected.value = assure;
}

function closeDetail() {
    selected.value = null;
}

function showToast(message) {
    toast.value = message;
    setTimeout(() => {
        toast.value = null;
    }, 3000);
}

function toggleCompte(nouveauStatut) {
    if (!selected.value) return;
    let motif = null;
    if (nouveauStatut === 'Désactivé') {
        motif = window.prompt('Motif de désactivation (obligatoire) :');
        if (!motif?.trim()) {
            showToast('Motif requis pour désactiver un compte.');
            return;
        }
    }
    router.post(
        route('admin.assures.statut', selected.value.id),
        { statut: nouveauStatut, motif },
        {
            preserveScroll: true,
            onSuccess: () => {
                showToast(`Statut mis à jour : ${nouveauStatut}.`);
                closeDetail();
            },
        },
    );
}

async function exportFif() {
    if (!selected.value?.membresExport?.length) return;
    exportBusy.value = true;
    try {
        await exportFamilleFif(selected.value.membresExport, selected.value.exportProfile);
        showToast('FIF familial généré.');
    } catch {
        showToast('Export indisponible (librairie non chargée).');
    } finally {
        exportBusy.value = false;
    }
}

async function exportZip() {
    if (!selected.value?.membresExport?.length) return;
    exportBusy.value = true;
    try {
        await exportFamilleZip(selected.value.membresExport, selected.value.exportProfile);
        showToast(`Archive FIF (${selected.value.membresExport.length + 1} fichier(s)) générée.`);
    } catch {
        showToast('Export indisponible (librairie non chargée).');
    } finally {
        exportBusy.value = false;
    }
}
</script>

<template>
    <Head title="Assurés" />

    <AdminLayout active-nav="assures" title="Assurés" subtitle="Comptes militaires · chaque membre listé = une demande d'enrôlement">
        <div class="assure-card p-4 mb-5 flex flex-wrap gap-3 items-center">
            <div class="relative flex-1 min-w-[200px]">
                <span class="material-symbols-outlined absolute left-2.5 top-1/2 -translate-y-1/2 text-on-surface-variant text-[18px]">search</span>
                <input v-model="search" class="w-full pl-8 pr-3 py-2 text-xs border border-outline-variant rounded-lg bg-white" placeholder="Nom, matricule, numéro CAMA…" type="text" />
            </div>
            <select v-model="statut" class="px-3 py-2 text-xs border border-outline-variant rounded-lg bg-white">
                <option v-for="opt in statutOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
            <Link :href="route('admin.exports')" class="px-4 py-2 rounded-lg border border-outline text-on-surface text-xs font-bold flex items-center gap-1.5 hover:bg-surface-container-low">
                <span class="material-symbols-outlined text-[16px]">file_download</span> Exporter
            </Link>
        </div>

        <div class="assure-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="bg-surface-container-low text-on-surface-variant text-[11px] uppercase tracking-wide">
                            <th class="px-5 py-3 font-medium">Assuré</th>
                            <th class="px-5 py-3 font-medium hidden md:table-cell">Matricule</th>
                            <th class="px-5 py-3 font-medium hidden lg:table-cell">Membres</th>
                            <th class="px-5 py-3 font-medium hidden lg:table-cell">Créé le</th>
                            <th class="px-5 py-3 font-medium">Statut</th>
                            <th class="px-5 py-3 font-medium text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant">
                        <tr v-for="a in assures" :key="a.id" class="hover:bg-surface-container-low transition-colors cursor-pointer" @click="openDetail(a)">
                            <td class="px-5 py-3 text-xs">
                                <div class="flex items-center gap-2">
                                    <div class="w-7 h-7 rounded-full bg-surface-container flex items-center justify-center font-bold text-[10px] text-primary shrink-0">{{ a.initiales }}</div>
                                    <div>
                                        <p class="font-bold">{{ a.nom }}</p>
                                        <p class="text-on-surface-variant text-[10px]">{{ a.numeroCama || '—' }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3 text-xs text-on-surface-variant hidden md:table-cell">{{ a.matricule }}</td>
                            <td class="px-5 py-3 text-xs text-on-surface-variant hidden lg:table-cell">{{ a.membresCount }}</td>
                            <td class="px-5 py-3 text-xs text-on-surface-variant hidden lg:table-cell">{{ a.dateCreation }}</td>
                            <td class="px-5 py-3">
                                <span class="px-2.5 py-1 rounded-full text-[11px] font-bold whitespace-nowrap" :class="STATUS_COMPTE_STYLES[a.statut] || ''">{{ a.statut }}</span>
                            </td>
                            <td class="px-5 py-3 text-right"><span class="material-symbols-outlined text-on-surface-variant">chevron_right</span></td>
                        </tr>
                        <tr v-if="!assures.length">
                            <td colspan="6" class="px-5 py-10 text-center text-on-surface-variant text-sm">Aucun assuré ne correspond.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="pagination.lastPage > 1" class="px-1 py-4 flex items-center justify-between flex-wrap gap-3">
            <p class="text-[11px] text-on-surface-variant">{{ pagination.total }} assuré(s) · page {{ pagination.currentPage }} / {{ pagination.lastPage }}</p>
            <div class="flex gap-2">
                <button type="button" class="px-3 py-1.5 border border-outline-variant rounded-lg text-xs font-bold disabled:opacity-50" :disabled="pagination.currentPage <= 1" @click="goToPage(pagination.currentPage - 1)">Précédent</button>
                <button type="button" class="px-3 py-1.5 border border-outline-variant rounded-lg text-xs font-bold disabled:opacity-50" :disabled="pagination.currentPage >= pagination.lastPage" @click="goToPage(pagination.currentPage + 1)">Suivant</button>
            </div>
        </div>
        <p v-else-if="pagination.total" class="text-[11px] text-on-surface-variant px-1 py-2">{{ pagination.total }} assuré(s)</p>

        <div v-if="selected" class="fixed inset-0 bg-black/50 z-50 flex items-stretch justify-end" @click.self="closeDetail">
            <div class="bg-surface-container-low w-full sm:max-w-xl h-full overflow-y-auto shadow-2xl">
                <div class="sticky top-0 bg-white border-b border-outline-variant px-5 py-4 flex items-center justify-between z-10">
                    <div>
                        <p class="text-[11px] text-on-surface-variant">{{ selected.numeroCama || '—' }}</p>
                        <h2 class="font-title-lg text-title-lg">{{ selected.nom }}</h2>
                    </div>
                    <button type="button" class="w-9 h-9 rounded-full hover:bg-surface-container-low flex items-center justify-center" @click="closeDetail">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>

                <div class="p-5 space-y-5">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-white rounded-lg p-3 border border-outline-variant">
                            <p class="text-[10px] text-on-surface-variant uppercase">Matricule</p>
                            <p class="text-xs font-bold">{{ selected.matricule }}</p>
                        </div>
                        <div class="bg-white rounded-lg p-3 border border-outline-variant">
                            <p class="text-[10px] text-on-surface-variant uppercase">Statut</p>
                            <span class="px-2.5 py-1 rounded-full text-[11px] font-bold whitespace-nowrap" :class="STATUS_COMPTE_STYLES[selected.statut] || ''">{{ selected.statut }}</span>
                        </div>
                        <div class="bg-white rounded-lg p-3 border border-outline-variant col-span-2">
                            <p class="text-[10px] text-on-surface-variant uppercase">Compte créé le</p>
                            <p class="text-xs font-bold">{{ selected.dateCreation }}</p>
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="text-xs font-bold uppercase tracking-wide text-on-surface-variant flex items-center gap-2">
                                <span class="material-symbols-outlined text-[16px] text-primary">family_restroom</span>
                                Demandes d'enrôlement ({{ selected.membres?.length || 0 }})
                            </h3>
                            <div v-if="selected.membres?.length" class="flex items-center gap-3">
                                <button type="button" class="text-[11px] font-bold text-primary flex items-center gap-1 hover:underline disabled:opacity-50" :disabled="exportBusy" @click="exportFif">
                                    <span class="material-symbols-outlined text-[14px]">picture_as_pdf</span> FIF (PDF)
                                </button>
                                <button type="button" class="text-[11px] font-bold text-primary flex items-center gap-1 hover:underline disabled:opacity-50" :disabled="exportBusy" @click="exportZip">
                                    <span class="material-symbols-outlined text-[14px]">folder_zip</span> Dossiers (ZIP)
                                </button>
                            </div>
                        </div>
                        <div class="bg-surface-container-low rounded-lg px-3">
                            <div v-for="m in selected.membres" :key="m.id" class="flex items-center justify-between py-2.5 border-b border-outline-variant last:border-0">
                                <span class="text-xs">{{ m.nom }} <span class="text-on-surface-variant">— {{ m.lien }}</span></span>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold whitespace-nowrap" :class="STATUS_DOSSIER_STYLES[m.statut] || ''">{{ m.statut }}</span>
                            </div>
                            <p v-if="!selected.membres?.length" class="text-xs text-on-surface-variant italic py-3">Aucun membre enrôlé.</p>
                        </div>
                    </div>
                </div>

                <div v-if="canManage" class="sticky bottom-0 bg-white border-t border-outline-variant p-4 flex flex-wrap gap-2">
                    <button
                        v-if="selected.statut === 'Désactivé'"
                        type="button"
                        class="px-4 py-2.5 rounded-lg bg-secondary text-on-secondary text-xs font-bold flex items-center gap-1.5"
                        @click="toggleCompte('Actif')"
                    >
                        <span class="material-symbols-outlined text-[16px]">check_circle</span> Réactiver le compte
                    </button>
                    <button
                        v-else
                        type="button"
                        class="px-4 py-2.5 rounded-lg bg-error text-on-error text-xs font-bold flex items-center gap-1.5"
                        @click="toggleCompte('Désactivé')"
                    >
                        <span class="material-symbols-outlined text-[16px]">block</span> Désactiver le compte
                    </button>
                    <button
                        v-if="selected.statut === 'En attente de validation'"
                        type="button"
                        class="px-4 py-2.5 rounded-lg bg-secondary text-on-secondary text-xs font-bold flex items-center gap-1.5"
                        @click="toggleCompte('Actif')"
                    >
                        <span class="material-symbols-outlined text-[16px]">how_to_reg</span> Valider le compte
                    </button>
                </div>
            </div>
        </div>

        <div v-if="toast" class="fixed bottom-6 right-6 z-[70]">
            <div class="bg-on-background text-white px-5 py-3 rounded-lg shadow-xl flex items-center gap-2 font-label-md text-label-md">
                <span class="material-symbols-outlined">check_circle</span> {{ toast }}
            </div>
        </div>
    </AdminLayout>
</template>
