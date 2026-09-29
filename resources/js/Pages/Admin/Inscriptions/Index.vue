<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    registrations: Array,
    stats: Object,
    filters: Object,
    statutOptions: Array,
    pagination: { type: Object, default: () => ({ currentPage: 1, lastPage: 1, perPage: 20, total: 0 }) },
});

const selected = ref(null);
const showRejectModal = ref(false);
const rejectMotif = ref('');

const search = ref(props.filters.search ?? '');
const statut = ref(props.filters.statut ?? 'en_attente_validation');
const dateFrom = ref(props.filters.date_from ?? '');
const dateTo = ref(props.filters.date_to ?? '');

const idForm = useForm({ numero_cim: '', numero_cama: '' });

const STATUS_STYLES = {
    en_attente_validation: 'bg-tertiary text-on-tertiary',
    actif: 'bg-secondary text-on-secondary',
    refuse: 'bg-error text-on-error',
    desactive: 'bg-error/10 text-error',
};

const STATUS_LABELS = {
    en_attente_validation: 'En attente',
    actif: 'Validée',
    refuse: 'Refusée',
    desactive: 'Désactivée',
};

function queryParams(page = 1) {
    return {
        search: search.value,
        statut: statut.value,
        date_from: dateFrom.value || undefined,
        date_to: dateTo.value || undefined,
        page,
    };
}

watch([search, statut, dateFrom, dateTo], () => {
    router.get(route('admin.inscriptions'), queryParams(1), { preserveState: true, replace: true });
});

function goToPage(page) {
    if (page < 1 || page > props.pagination.lastPage) return;
    router.get(route('admin.inscriptions'), queryParams(page), { preserveState: true, replace: true });
}

function filterByStatut(value) {
    statut.value = value;
}

function clearDates() {
    dateFrom.value = '';
    dateTo.value = '';
}

const hasDateFilter = computed(() => Boolean(dateFrom.value || dateTo.value));

function openDetail(reg) {
    selected.value = reg;
    idForm.numero_cim = reg.numeroCim ?? '';
    idForm.numero_cama = reg.numeroCama ?? '';
}

function closeDetail() {
    selected.value = null;
    showRejectModal.value = false;
}

function saveIds() {
    if (!selected.value) return;
    idForm.patch(route('admin.inscriptions.identifiers', selected.value.id), {
        preserveScroll: true,
        onSuccess: () => openDetail({ ...selected.value, numeroCim: idForm.numero_cim, numeroCama: idForm.numero_cama }),
    });
}

function validateReg() {
    if (!selected.value) return;
    router.post(route('admin.inscriptions.validate', selected.value.id), {}, {
        preserveScroll: true,
        preserveState: true,
        only: ['registrations', 'stats', 'pagination', 'flash'],
        onSuccess: closeDetail,
    });
}

function rejectReg() {
    if (!selected.value || !rejectMotif.value.trim()) return;
    router.post(route('admin.inscriptions.reject', selected.value.id), { motif: rejectMotif.value }, {
        preserveScroll: true,
        preserveState: true,
        only: ['registrations', 'stats', 'pagination', 'flash'],
        onSuccess: closeDetail,
    });
}

const isPending = computed(() => selected.value?.statut === 'en_attente_validation');
const isRefused = computed(() => selected.value?.statut === 'refuse');
const isActive = computed(() => selected.value?.statut === 'actif');

function infoValue(value) {
    if (value === null || value === undefined || String(value).trim() === '') {
        return '—';
    }
    return value;
}
</script>

<template>
    <Head title="Inscriptions" />

    <AdminLayout active-nav="inscriptions" title="Inscriptions" subtitle="Demandes de création de compte assuré en attente de validation">
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 md:gap-4 mb-5">
            <button
                type="button"
                class="stat-tile group text-left rounded-xl border p-4 transition-all hover:shadow-md hover:-translate-y-0.5"
                :class="statut === 'en_attente_validation' ? 'border-tertiary ring-2 ring-tertiary/30 bg-tertiary/5' : 'border-outline-variant bg-white'"
                @click="filterByStatut('en_attente_validation')"
            >
                <div class="flex items-center justify-between">
                    <span class="w-9 h-9 rounded-lg bg-tertiary/15 text-tertiary flex items-center justify-center"><span class="material-symbols-outlined text-[20px]">hourglass_top</span></span>
                    <span class="material-symbols-outlined text-[16px] text-on-surface-variant/40 group-hover:text-tertiary">filter_alt</span>
                </div>
                <p class="text-3xl font-extrabold text-tertiary mt-3 leading-none tabular-nums">{{ stats.pending }}</p>
                <p class="text-[11px] font-semibold text-on-surface-variant uppercase tracking-wide mt-1">En attente</p>
            </button>

            <button
                type="button"
                class="stat-tile group text-left rounded-xl border p-4 transition-all hover:shadow-md hover:-translate-y-0.5"
                :class="statut === 'actif' ? 'border-secondary ring-2 ring-secondary/30 bg-secondary/5' : 'border-outline-variant bg-white'"
                @click="filterByStatut('actif')"
            >
                <div class="flex items-center justify-between">
                    <span class="w-9 h-9 rounded-lg bg-secondary/15 text-secondary flex items-center justify-center"><span class="material-symbols-outlined text-[20px]">verified</span></span>
                    <span class="material-symbols-outlined text-[16px] text-on-surface-variant/40 group-hover:text-secondary">filter_alt</span>
                </div>
                <p class="text-3xl font-extrabold text-secondary mt-3 leading-none tabular-nums">{{ stats.validated }}</p>
                <p class="text-[11px] font-semibold text-on-surface-variant uppercase tracking-wide mt-1">Validées</p>
            </button>

            <button
                type="button"
                class="stat-tile group text-left rounded-xl border p-4 transition-all hover:shadow-md hover:-translate-y-0.5"
                :class="statut === 'refuse' ? 'border-error ring-2 ring-error/30 bg-error/5' : 'border-outline-variant bg-white'"
                @click="filterByStatut('refuse')"
            >
                <div class="flex items-center justify-between">
                    <span class="w-9 h-9 rounded-lg bg-error/15 text-error flex items-center justify-center"><span class="material-symbols-outlined text-[20px]">cancel</span></span>
                    <span class="material-symbols-outlined text-[16px] text-on-surface-variant/40 group-hover:text-error">filter_alt</span>
                </div>
                <p class="text-3xl font-extrabold text-error mt-3 leading-none tabular-nums">{{ stats.refused }}</p>
                <p class="text-[11px] font-semibold text-on-surface-variant uppercase tracking-wide mt-1">Refusées</p>
            </button>

            <button
                type="button"
                class="stat-tile group text-left rounded-xl border p-4 transition-all hover:shadow-md hover:-translate-y-0.5"
                :class="statut === 'tous' ? 'border-primary ring-2 ring-primary/30 bg-primary/5' : 'border-outline-variant bg-white'"
                @click="filterByStatut('tous')"
            >
                <div class="flex items-center justify-between">
                    <span class="w-9 h-9 rounded-lg bg-primary/15 text-primary flex items-center justify-center"><span class="material-symbols-outlined text-[20px]">groups</span></span>
                    <span class="material-symbols-outlined text-[16px] text-on-surface-variant/40 group-hover:text-primary">filter_alt</span>
                </div>
                <p class="text-3xl font-extrabold text-on-surface mt-3 leading-none tabular-nums">{{ stats.total }}</p>
                <p class="text-[11px] font-semibold text-on-surface-variant uppercase tracking-wide mt-1">Total</p>
            </button>
        </div>

        <div class="assure-card p-4 mb-5 flex flex-wrap gap-3 items-center">
            <div class="relative flex-1 min-w-[200px]">
                <span class="material-symbols-outlined absolute left-2.5 top-1/2 -translate-y-1/2 text-on-surface-variant text-[18px]">search</span>
                <input v-model="search" class="w-full pl-8 pr-3 py-2 text-xs border border-outline-variant rounded-lg bg-white" placeholder="Nom, matricule, e-mail…" type="text" />
            </div>
            <select v-model="statut" class="px-3 py-2 text-xs border border-outline-variant rounded-lg bg-white">
                <option v-for="opt in statutOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
            <div class="flex items-center gap-1.5">
                <span class="material-symbols-outlined text-on-surface-variant text-[18px]">calendar_month</span>
                <label class="text-[11px] text-on-surface-variant">Du</label>
                <input v-model="dateFrom" type="date" :max="dateTo || undefined" class="px-2 py-2 text-xs border border-outline-variant rounded-lg bg-white" />
                <label class="text-[11px] text-on-surface-variant">au</label>
                <input v-model="dateTo" type="date" :min="dateFrom || undefined" class="px-2 py-2 text-xs border border-outline-variant rounded-lg bg-white" />
                <button
                    v-if="hasDateFilter"
                    type="button"
                    class="inline-flex items-center gap-1 text-[11px] font-semibold text-on-surface-variant hover:text-error transition-colors"
                    title="Effacer les dates"
                    @click="clearDates"
                >
                    <span class="material-symbols-outlined text-[16px]">close</span>
                </button>
            </div>
        </div>

        <div class="assure-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="bg-surface-container-low text-on-surface-variant text-[11px] uppercase tracking-wide">
                            <th class="px-5 py-3 font-medium">Demandeur</th>
                            <th class="px-5 py-3 font-medium hidden md:table-cell">Matricule</th>
                            <th class="px-5 py-3 font-medium hidden lg:table-cell">Grade</th>
                            <th class="px-5 py-3 font-medium hidden lg:table-cell">Soumis le</th>
                            <th class="px-5 py-3 font-medium">Statut</th>
                            <th class="px-5 py-3 font-medium text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant">
                        <tr v-for="r in registrations" :key="r.id" class="hover:bg-surface-container-low transition-colors cursor-pointer" @click="openDetail(r)">
                            <td class="px-5 py-3 text-xs">
                                <div class="flex items-center gap-2">
                                    <div class="w-8 h-8 rounded-full bg-surface-container flex items-center justify-center font-bold text-[10px] text-primary shrink-0">{{ r.initiales }}</div>
                                    <div><p class="font-bold">{{ r.fullName }}</p><p class="text-on-surface-variant text-[10px]">{{ r.email }}</p></div>
                                </div>
                            </td>
                            <td class="px-5 py-3 text-xs text-on-surface-variant hidden md:table-cell">{{ r.matricule }}</td>
                            <td class="px-5 py-3 text-xs text-on-surface-variant hidden lg:table-cell">{{ r.grade || '—' }}</td>
                            <td class="px-5 py-3 text-xs text-on-surface-variant hidden lg:table-cell">{{ r.dateCreation }}</td>
                            <td class="px-5 py-3">
                                <span class="px-2.5 py-1 rounded-full text-[11px] font-bold whitespace-nowrap" :class="STATUS_STYLES[r.statut]">{{ STATUS_LABELS[r.statut] || r.statutLabel }}</span>
                            </td>
                            <td class="px-5 py-3 text-right"><span class="material-symbols-outlined text-on-surface-variant">chevron_right</span></td>
                        </tr>
                        <tr v-if="!registrations.length"><td colspan="6" class="px-5 py-10 text-center text-on-surface-variant text-sm">Aucune inscription ne correspond.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="pagination.lastPage > 1" class="px-1 py-4 flex items-center justify-between flex-wrap gap-3">
            <p class="text-[11px] text-on-surface-variant">{{ pagination.total }} inscription(s) · page {{ pagination.currentPage }} / {{ pagination.lastPage }}</p>
            <div class="flex gap-2">
                <button type="button" class="px-3 py-1.5 border border-outline-variant rounded-lg text-xs font-bold disabled:opacity-50" :disabled="pagination.currentPage <= 1" @click="goToPage(pagination.currentPage - 1)">Précédent</button>
                <button type="button" class="px-3 py-1.5 border border-outline-variant rounded-lg text-xs font-bold disabled:opacity-50" :disabled="pagination.currentPage >= pagination.lastPage" @click="goToPage(pagination.currentPage + 1)">Suivant</button>
            </div>
        </div>
        <p v-else-if="pagination.total" class="text-[11px] text-on-surface-variant px-1 py-2">{{ pagination.total }} inscription(s)</p>

        <div v-if="selected" class="fixed inset-0 bg-black/50 z-50 flex items-stretch justify-end" @click.self="closeDetail">
            <div class="bg-surface-container-low w-full sm:max-w-xl h-full overflow-y-auto shadow-2xl">
                <div class="sticky top-0 bg-white border-b border-outline-variant px-5 py-4 flex items-center justify-between z-10">
                    <div class="flex items-center gap-3">
                        <div class="w-11 h-11 rounded-full bg-surface-container flex items-center justify-center font-bold text-primary shrink-0">{{ selected.initiales }}</div>
                        <div>
                            <h2 class="font-title-lg text-title-lg leading-tight">{{ selected.fullName }}</h2>
                            <p class="text-[11px] text-on-surface-variant">
                                {{ selected.numeroCama ? selected.numeroCama : 'N° Carte CAMA non renseigné' }}{{ selected.numeroCim ? ` · CIM ${selected.numeroCim}` : '' }}
                            </p>
                        </div>
                    </div>
                    <button type="button" class="w-9 h-9 rounded-full hover:bg-surface-container-low flex items-center justify-center" @click="closeDetail"><span class="material-symbols-outlined">close</span></button>
                </div>

                <div class="p-5 space-y-5">
                    <div>
                        <span class="px-2.5 py-1 rounded-full text-[11px] font-bold whitespace-nowrap" :class="STATUS_STYLES[selected.statut]">{{ STATUS_LABELS[selected.statut] || selected.statutLabel }}</span>
                        <p v-if="selected.motifRefus" class="text-[11px] text-error mt-2">Motif : {{ selected.motifRefus }}</p>
                    </div>

                    <div v-if="isPending" class="bg-amber-50 border border-amber-200 rounded-lg p-3 text-xs text-on-surface">
                        <p class="font-bold mb-1 flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">info</span> Identifiants CAMA</p>
                        <p class="text-on-surface-variant mb-2">L'assuré renseigne son N° CIM et son N° Carte CAMA à l'inscription. Corrigez-les ici si besoin avant validation.</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <div><label class="text-[10px] uppercase text-on-surface-variant">N° CIM</label><input v-model="idForm.numero_cim" class="w-full mt-1 px-2 py-1.5 text-xs border border-outline-variant rounded" /></div>
                            <div><label class="text-[10px] uppercase text-on-surface-variant">N° Carte CAMA</label><input v-model="idForm.numero_cama" class="w-full mt-1 px-2 py-1.5 text-xs border border-outline-variant rounded" /></div>
                        </div>
                        <button type="button" class="mt-2 text-primary text-[11px] font-bold hover:underline" @click="saveIds">Enregistrer les identifiants</button>
                    </div>

                    <div>
                        <h3 class="text-xs font-bold uppercase tracking-wide text-on-surface-variant mb-2 flex items-center gap-2">
                            <span class="material-symbols-outlined text-[16px] text-primary">badge</span> Informations administratives
                        </h3>
                        <div class="grid grid-cols-2 gap-2 text-xs">
                            <div class="bg-white rounded-lg p-3 border border-outline-variant"><p class="text-[10px] text-on-surface-variant uppercase">Matricule militaire</p><p class="font-bold break-words">{{ infoValue(selected.matricule) }}</p></div>
                            <div class="bg-white rounded-lg p-3 border border-outline-variant"><p class="text-[10px] text-on-surface-variant uppercase">Sexe</p><p class="font-bold break-words">{{ infoValue(selected.sexe) }}</p></div>
                            <div class="bg-white rounded-lg p-3 border border-outline-variant"><p class="text-[10px] text-on-surface-variant uppercase">Grade</p><p class="font-bold break-words">{{ infoValue(selected.grade) }}</p></div>
                            <div class="bg-white rounded-lg p-3 border border-outline-variant"><p class="text-[10px] text-on-surface-variant uppercase">Catégorie</p><p class="font-bold break-words">{{ infoValue(selected.categorie) }}</p></div>
                            <div class="bg-white rounded-lg p-3 border border-outline-variant"><p class="text-[10px] text-on-surface-variant uppercase">N° informatique</p><p class="font-bold break-words">{{ infoValue(selected.numeroInformatique) }}</p></div>
                            <div class="bg-white rounded-lg p-3 border border-outline-variant"><p class="text-[10px] text-on-surface-variant uppercase">N° CIM</p><p class="font-bold break-words">{{ selected.numeroCim || 'Non renseigné' }}</p></div>
                            <div class="bg-white rounded-lg p-3 border border-outline-variant"><p class="text-[10px] text-on-surface-variant uppercase">N° IUP</p><p class="font-bold break-words">{{ infoValue(selected.numeroIup) }}</p></div>
                            <div class="bg-white rounded-lg p-3 border border-outline-variant"><p class="text-[10px] text-on-surface-variant uppercase">N° Carte CAMA</p><p class="font-bold break-words">{{ selected.numeroCama || 'Non renseigné' }}</p></div>
                            <div class="bg-white rounded-lg p-3 border border-outline-variant"><p class="text-[10px] text-on-surface-variant uppercase">Téléphones</p><p class="font-bold break-words">{{ infoValue(selected.telephone) }}</p></div>
                            <div class="bg-white rounded-lg p-3 border border-outline-variant"><p class="text-[10px] text-on-surface-variant uppercase">E-mail</p><p class="font-bold break-all">{{ infoValue(selected.email) }}</p></div>
                        </div>
                    </div>

                    <div>
                        <h3 class="text-xs font-bold uppercase tracking-wide text-on-surface-variant mb-2 flex items-center gap-2">
                            <span class="material-symbols-outlined text-[16px] text-primary">account_tree</span> Structure de rattachement
                        </h3>
                        <div class="grid grid-cols-2 gap-2 text-xs">
                            <div class="bg-white rounded-lg p-3 border border-outline-variant"><p class="text-[10px] text-on-surface-variant uppercase">Armée</p><p class="font-bold break-words">{{ infoValue(selected.armee) }}</p></div>
                            <div class="bg-white rounded-lg p-3 border border-outline-variant"><p class="text-[10px] text-on-surface-variant uppercase">Région</p><p class="font-bold break-words">{{ infoValue(selected.region) }}</p></div>
                            <div class="bg-white rounded-lg p-3 border border-outline-variant"><p class="text-[10px] text-on-surface-variant uppercase">Corps</p><p class="font-bold break-words">{{ infoValue(selected.corps) }}</p></div>
                            <div class="bg-white rounded-lg p-3 border border-outline-variant"><p class="text-[10px] text-on-surface-variant uppercase">Service</p><p class="font-bold break-words">{{ infoValue(selected.service) }}</p></div>
                            <div class="bg-white rounded-lg p-3 border border-outline-variant"><p class="text-[10px] text-on-surface-variant uppercase">Section</p><p class="font-bold break-words">{{ infoValue(selected.section) }}</p></div>
                            <div class="bg-white rounded-lg p-3 border border-outline-variant"><p class="text-[10px] text-on-surface-variant uppercase">Sous-section</p><p class="font-bold break-words">{{ infoValue(selected.sousSection) }}</p></div>
                        </div>
                    </div>

                    <div v-if="selected.documents && selected.documents.length">
                        <h3 class="text-xs font-bold uppercase tracking-wide text-on-surface-variant mb-2 flex items-center gap-2">
                            <span class="material-symbols-outlined text-[16px] text-primary">badge</span> Pièces justificatives
                        </h3>
                        <div class="space-y-2">
                            <a
                                v-for="doc in selected.documents"
                                :key="doc.key"
                                :href="doc.url"
                                target="_blank"
                                rel="noopener"
                                class="flex items-center justify-between gap-2 bg-white rounded-lg p-3 border border-outline-variant hover:border-primary transition-colors"
                            >
                                <span class="flex items-center gap-2 min-w-0">
                                    <span class="material-symbols-outlined text-[18px] text-primary shrink-0">description</span>
                                    <span class="min-w-0">
                                        <span class="text-xs font-bold block">{{ doc.label }}</span>
                                        <span class="text-[10px] text-on-surface-variant break-all">{{ doc.filename }}</span>
                                    </span>
                                </span>
                                <span class="material-symbols-outlined text-[18px] text-on-surface-variant shrink-0">download</span>
                            </a>
                        </div>
                    </div>

                    <div>
                        <h3 class="text-xs font-bold uppercase tracking-wide text-on-surface-variant mb-2 flex items-center gap-2">
                            <span class="material-symbols-outlined text-[16px] text-primary">contact_emergency</span> Personne à prévenir
                        </h3>
                        <div class="grid grid-cols-2 gap-2 text-xs">
                            <div class="bg-white rounded-lg p-3 border border-outline-variant"><p class="text-[10px] text-on-surface-variant uppercase">Nom et prénom(s)</p><p class="font-bold break-words">{{ infoValue(selected.personneAPrevenir) }}</p></div>
                            <div class="bg-white rounded-lg p-3 border border-outline-variant"><p class="text-[10px] text-on-surface-variant uppercase">Téléphone</p><p class="font-bold break-words">{{ infoValue(selected.telPersonneAPrevenir) }}</p></div>
                        </div>
                    </div>

                    <div>
                        <h3 class="text-xs font-bold uppercase tracking-wide text-on-surface-variant mb-2 flex items-center gap-2">
                            <span class="material-symbols-outlined text-[16px] text-primary">history</span> Journal
                        </h3>
                        <div class="bg-surface-container-low rounded-lg px-3 py-3">
                            <div v-for="(j, i) in selected.journal || []" :key="i" class="flex gap-3">
                                <div class="relative flex flex-col items-center">
                                    <div class="w-2 h-2 rounded-full bg-primary z-10 mt-1" />
                                    <div v-if="i < (selected.journal || []).length - 1" class="w-0.5 flex-1 bg-outline-variant" />
                                </div>
                                <div class="pb-3">
                                    <p class="text-xs font-bold">{{ j.libelle }}</p>
                                    <p class="text-[11px] text-on-surface-variant">{{ j.date }}</p>
                                </div>
                            </div>
                            <p v-if="!(selected.journal || []).length" class="text-xs text-on-surface-variant italic">Aucune entrée.</p>
                        </div>
                    </div>
                </div>

                <div class="sticky bottom-0 bg-white border-t border-outline-variant p-4 flex gap-2">
                    <template v-if="isPending">
                        <button type="button" class="flex-1 px-4 py-2.5 rounded-lg bg-secondary text-on-secondary text-xs font-bold flex items-center justify-center gap-1.5" @click="validateReg">
                            <span class="material-symbols-outlined text-[16px]">how_to_reg</span> Valider et activer
                        </button>
                        <button type="button" class="flex-1 px-4 py-2.5 rounded-lg bg-error text-on-error text-xs font-bold flex items-center justify-center gap-1.5" @click="showRejectModal = true">
                            <span class="material-symbols-outlined text-[16px]">cancel</span> Refuser
                        </button>
                    </template>
                    <button
                        v-else-if="isRefused"
                        type="button"
                        class="flex-1 px-4 py-2.5 rounded-lg bg-secondary text-on-secondary text-xs font-bold flex items-center justify-center gap-1.5"
                        @click="validateReg"
                    >
                        <span class="material-symbols-outlined text-[16px]">restart_alt</span> Reconsidérer et valider
                    </button>
                    <Link
                        v-else-if="isActive"
                        :href="route('admin.assures')"
                        class="flex-1 px-4 py-2.5 rounded-lg border border-outline text-on-surface text-xs font-bold flex items-center justify-center gap-1.5"
                    >
                        <span class="material-symbols-outlined text-[16px]">groups</span> Voir dans les assurés
                    </Link>
                </div>
            </div>
        </div>

        <div v-if="showRejectModal" class="fixed inset-0 bg-black/50 z-[60] flex items-center justify-center p-4">
            <div class="bg-white rounded-xl p-6 max-w-md w-full">
                <h3 class="font-bold mb-3">Motif du refus</h3>
                <textarea v-model="rejectMotif" class="w-full border border-outline-variant rounded-lg p-3 text-sm" rows="4" placeholder="Communiqué à l'assuré" />
                <div class="flex gap-2 mt-4">
                    <button type="button" class="flex-1 py-2 border rounded-lg text-sm" @click="showRejectModal = false">Annuler</button>
                    <button type="button" class="flex-1 py-2 bg-error text-white rounded-lg text-sm font-bold" @click="rejectReg">Confirmer le refus</button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
