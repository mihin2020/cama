<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import CamaLoadingButton from '@/Components/CamaLoadingButton.vue';
import { exportMembreFif } from '@/Composables/useCamaExport';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps({
    catalog: { type: Array, default: () => [] },
});

const STORAGE_KEY = 'cama.exports.selection';

const formats = [
    { key: 'xlsx', label: 'Excel', ext: '.xlsx', icon: 'table_view', hint: 'Un onglet par élément, en-têtes figés et filtres.' },
    { key: 'csv', label: 'CSV', ext: '.csv', icon: 'csv', hint: 'Un fichier par élément (regroupés en .zip si plusieurs).' },
    { key: 'sql', label: 'SQL', ext: '.sql', icon: 'database', hint: 'Sauvegarde réimportable dans une base MySQL.' },
];

const format = ref('xlsx');
const from = ref('');
const to = ref('');
const structure = ref(true);
const search = ref('');
const checked = reactive({});
const columns = reactive({});
const openColumns = ref(null);
const busy = ref(false);
const error = ref('');
const toast = ref(null);

props.catalog.forEach((item) => {
    checked[item.key] = false;
    columns[item.key] = item.columns.map((c) => c.key);
});
restoreSelection();

const numberFr = (n) => Number(n || 0).toLocaleString('fr-FR');

const groups = computed(() => {
    const term = search.value.trim().toLowerCase();
    const map = new Map();
    props.catalog
        .filter((item) => !term || `${item.label} ${item.description} ${item.group}`.toLowerCase().includes(term))
        .forEach((item) => {
            if (!map.has(item.group)) map.set(item.group, []);
            map.get(item.group).push(item);
        });
    return [...map.entries()].map(([name, items]) => ({ name, items }));
});

const selectedItems = computed(() => props.catalog.filter((item) => checked[item.key]));
const selectedRows = computed(() => selectedItems.value.reduce((sum, item) => sum + item.count, 0));
const isSql = computed(() => format.value === 'sql');
const hasDatedSelection = computed(() => selectedItems.value.some((item) => item.hasDate));
const emptyColumns = computed(() => (isSql.value ? [] : selectedItems.value.filter((item) => !columns[item.key].length)));
const canExport = computed(() => selectedItems.value.length > 0 && emptyColumns.value.length === 0 && !busy.value);

function groupState(group) {
    const count = group.items.filter((item) => checked[item.key]).length;
    return { all: count === group.items.length, some: count > 0 && count < group.items.length, count };
}

function toggleGroup(group) {
    const value = !groupState(group).all;
    group.items.forEach((item) => {
        checked[item.key] = value;
    });
}

function setAll(value) {
    groups.value.forEach((group) => group.items.forEach((item) => {
        checked[item.key] = value;
    }));
}

function toggleColumn(item, key) {
    const list = columns[item.key];
    columns[item.key] = list.includes(key)
        ? list.filter((k) => k !== key)
        : item.columns.map((c) => c.key).filter((k) => k === key || list.includes(k));
}

function setColumns(item, all) {
    columns[item.key] = all ? item.columns.map((c) => c.key) : [];
}

function showToast(message) {
    toast.value = message;
    setTimeout(() => {
        toast.value = null;
    }, 3500);
}

/* Mémorise la sélection dans ce navigateur, pour refaire le même export en un clic. */
function restoreSelection() {
    try {
        const saved = JSON.parse(localStorage.getItem(STORAGE_KEY) || 'null');
        if (!saved) return;
        if (formats.some((f) => f.key === saved.format)) format.value = saved.format;
        structure.value = saved.structure !== false;
        props.catalog.forEach((item) => {
            checked[item.key] = (saved.checked || []).includes(item.key);
            const cols = saved.columns?.[item.key];
            if (Array.isArray(cols)) {
                const valid = item.columns.map((c) => c.key).filter((k) => cols.includes(k));
                if (valid.length) columns[item.key] = valid;
            }
        });
    } catch {
        /* stockage indisponible : sélection vide */
    }
}

watch([format, structure, checked, columns], () => {
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify({
            format: format.value,
            structure: structure.value,
            checked: Object.keys(checked).filter((k) => checked[k]),
            columns: { ...columns },
        }));
    } catch {
        /* stockage indisponible : rien à faire */
    }
}, { deep: true });

function filenameFrom(disposition, fallback) {
    const utf = /filename\*=UTF-8''([^;]+)/i.exec(disposition || '');
    if (utf) return decodeURIComponent(utf[1]);
    const plain = /filename="?([^";]+)"?/i.exec(disposition || '');
    return plain ? plain[1] : fallback;
}

async function errorMessage(err) {
    const status = err.response?.status;
    if (status === 419) return 'Votre session a expiré : rechargez la page puis relancez l\'export.';
    if (status === 403) return 'Vous n\'avez pas la permission d\'exporter ces données.';
    try {
        const data = JSON.parse(await err.response.data.text());
        const first = data.errors ? Object.values(data.errors).flat()[0] : null;
        return first || data.message || 'Export impossible.';
    } catch {
        return 'Export impossible. Réessayez dans un instant.';
    }
}

async function runExport() {
    if (!canExport.value) return;
    busy.value = true;
    error.value = '';

    const datasets = Object.fromEntries(selectedItems.value.map((item) => [item.key, columns[item.key]]));

    try {
        const response = await axios.post(route('admin.exports.download'), {
            format: format.value,
            datasets,
            from: hasDatedSelection.value && from.value ? from.value : null,
            to: hasDatedSelection.value && to.value ? to.value : null,
            structure: structure.value,
        }, { responseType: 'blob' });

        // Une page HTML à la place du fichier : session expirée (redirection vers la connexion).
        if (String(response.headers['content-type'] || '').includes('text/html')) {
            error.value = 'Votre session a expiré : reconnectez-vous puis relancez l\'export.';
            return;
        }

        const ext = formats.find((f) => f.key === format.value).ext;
        const name = filenameFrom(response.headers['content-disposition'], `cama-export${ext}`);
        const url = URL.createObjectURL(response.data);
        const link = document.createElement('a');
        link.href = url;
        link.download = name;
        document.body.appendChild(link);
        link.click();
        link.remove();
        setTimeout(() => URL.revokeObjectURL(url), 10000);

        showToast(`Export prêt : ${name}`);
    } catch (err) {
        error.value = await errorMessage(err);
    } finally {
        busy.value = false;
    }
}

/* Fiche PDF d'un dossier */
const refPdf = ref('');
const pdfBusy = ref(false);

async function exportPdf() {
    const reference = refPdf.value.trim();
    if (!reference) {
        showToast('Renseignez une référence de dossier.');
        return;
    }
    pdfBusy.value = true;
    try {
        const { data } = await axios.post(route('admin.exports.dossier-pdf'), { ref: reference });
        await exportMembreFif(data.membre, data.assure);
        showToast(`Fiche PDF générée pour ${reference}.`);
    } catch (err) {
        showToast(err.response?.data?.error ?? err.response?.data?.message ?? 'Fiche PDF indisponible.');
    } finally {
        pdfBusy.value = false;
    }
}
</script>

<template>
    <Head title="Exports" />

    <AdminLayout active-nav="exports" title="Centre d'export" subtitle="Cochez les données voulues, choisissez Excel, CSV ou SQL — chaque export est tracé">
        <div class="max-w-[1200px] w-full mx-auto">
            <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_340px] gap-6 items-start">
                <!-- Sélection -->
                <section class="assure-card overflow-hidden">
                    <div class="px-5 py-4 border-b border-outline-variant bg-surface-container-low flex flex-wrap items-center gap-3">
                        <h3 class="font-title-lg text-title-lg mr-auto">Que voulez-vous exporter ?</h3>
                        <div class="relative w-full sm:w-56">
                            <span class="material-symbols-outlined absolute left-2.5 top-1/2 -translate-y-1/2 text-[18px] text-on-surface-variant">search</span>
                            <input v-model="search" class="w-full pl-9 pr-3 py-2 text-xs border border-outline-variant rounded-lg" placeholder="Rechercher un élément…" type="search" />
                        </div>
                        <div class="flex gap-2">
                            <button type="button" class="px-3 py-2 rounded-lg border border-outline-variant text-xs font-bold hover:bg-surface-container" @click="setAll(true)">Tout cocher</button>
                            <button type="button" class="px-3 py-2 rounded-lg border border-outline-variant text-xs font-bold hover:bg-surface-container" @click="setAll(false)">Tout décocher</button>
                        </div>
                    </div>

                    <div v-if="!catalog.length" class="px-5 py-12 text-center text-sm text-on-surface-variant">
                        Aucune donnée exportable avec vos permissions.
                    </div>
                    <div v-else-if="!groups.length" class="px-5 py-12 text-center text-sm text-on-surface-variant">
                        Aucun élément ne correspond à « {{ search }} ».
                    </div>

                    <div v-for="group in groups" :key="group.name" class="border-b border-outline-variant last:border-b-0">
                        <label class="flex items-center gap-3 px-5 py-3 bg-surface-container-lowest cursor-pointer select-none">
                            <input
                                class="rounded border-outline-variant text-primary w-4 h-4"
                                type="checkbox"
                                :checked="groupState(group).all"
                                :indeterminate="groupState(group).some"
                                @change="toggleGroup(group)"
                            />
                            <span class="text-[11px] font-bold uppercase tracking-wide text-on-surface-variant">{{ group.name }}</span>
                            <span class="ml-auto text-[11px] text-on-surface-variant">{{ groupState(group).count }} / {{ group.items.length }}</span>
                        </label>

                        <ul class="divide-y divide-outline-variant/60">
                            <li v-for="item in group.items" :key="item.key" :class="checked[item.key] ? 'bg-primary/[0.03]' : ''">
                                <div class="flex items-start gap-3 px-5 py-3">
                                    <input
                                        :id="`exp-${item.key}`"
                                        v-model="checked[item.key]"
                                        class="mt-0.5 rounded border-outline-variant text-primary w-4 h-4 shrink-0"
                                        type="checkbox"
                                    />
                                    <label :for="`exp-${item.key}`" class="flex-1 min-w-0 cursor-pointer">
                                        <span class="block text-sm font-bold text-on-surface">{{ item.label }}</span>
                                        <span class="block text-xs text-on-surface-variant">{{ item.description }}</span>
                                    </label>
                                    <span class="shrink-0 text-[11px] px-2 py-0.5 rounded-full bg-surface-container text-on-surface-variant font-medium whitespace-nowrap">
                                        {{ numberFr(item.count) }} ligne{{ item.count > 1 ? 's' : '' }}
                                    </span>
                                    <button
                                        v-if="!isSql"
                                        type="button"
                                        class="shrink-0 flex items-center gap-1 text-[11px] font-bold px-2 py-1 rounded-lg hover:bg-surface-container"
                                        :class="columns[item.key].length < item.columns.length ? 'text-primary' : 'text-on-surface-variant'"
                                        :aria-expanded="openColumns === item.key"
                                        @click="openColumns = openColumns === item.key ? null : item.key"
                                    >
                                        <span class="hidden sm:inline">Colonnes</span> {{ columns[item.key].length }}/{{ item.columns.length }}
                                        <span class="material-symbols-outlined text-[16px] transition-transform" :class="openColumns === item.key ? 'rotate-180' : ''">expand_more</span>
                                    </button>
                                </div>

                                <div v-if="openColumns === item.key && !isSql" class="px-5 pb-4 sm:pl-12">
                                    <div class="flex items-center gap-3 mb-2 text-[11px]">
                                        <span class="text-on-surface-variant">Colonnes à inclure :</span>
                                        <button type="button" class="font-bold text-primary hover:underline" @click="setColumns(item, true)">Toutes</button>
                                        <button type="button" class="font-bold text-primary hover:underline" @click="setColumns(item, false)">Aucune</button>
                                    </div>
                                    <div class="flex flex-wrap gap-1.5">
                                        <button
                                            v-for="col in item.columns"
                                            :key="col.key"
                                            type="button"
                                            class="px-2.5 py-1 rounded-full border text-[11px] transition-colors"
                                            :class="columns[item.key].includes(col.key)
                                                ? 'border-primary bg-primary/10 text-primary font-bold'
                                                : 'border-outline-variant text-on-surface-variant hover:bg-surface-container'"
                                            :aria-pressed="columns[item.key].includes(col.key)"
                                            @click="toggleColumn(item, col.key)"
                                        >
                                            {{ col.label }}
                                        </button>
                                    </div>
                                    <p v-if="!columns[item.key].length" class="mt-2 text-[11px] text-error">Choisissez au moins une colonne.</p>
                                </div>
                            </li>
                        </ul>
                    </div>
                </section>

                <!-- Réglages et lancement -->
                <aside class="space-y-4 lg:sticky lg:top-4">
                    <div class="assure-card p-5">
                        <h3 class="text-xs font-bold uppercase tracking-wide text-on-surface-variant mb-3">Format</h3>
                        <div class="space-y-2">
                            <label
                                v-for="f in formats"
                                :key="f.key"
                                class="flex items-start gap-3 p-3 rounded-xl border cursor-pointer transition-colors"
                                :class="format === f.key ? 'border-primary bg-primary/5' : 'border-outline-variant hover:bg-surface-container-low'"
                            >
                                <input v-model="format" class="sr-only" type="radio" name="export-format" :value="f.key" />
                                <span class="material-symbols-outlined" :class="format === f.key ? 'text-primary' : 'text-on-surface-variant'">{{ f.icon }}</span>
                                <span class="flex-1">
                                    <span class="block text-sm font-bold">{{ f.label }} <span class="font-normal text-on-surface-variant">{{ f.ext }}</span></span>
                                    <span class="block text-[11px] text-on-surface-variant">{{ f.hint }}</span>
                                </span>
                                <span v-if="format === f.key" class="material-symbols-outlined text-primary text-[20px]">check_circle</span>
                            </label>
                        </div>

                        <div v-if="isSql" class="mt-3 space-y-2">
                            <label class="flex items-start gap-2 text-xs cursor-pointer">
                                <input v-model="structure" class="mt-0.5 rounded border-outline-variant text-primary" type="checkbox" />
                                <span>Inclure la structure des tables <span class="text-on-surface-variant">(CREATE TABLE IF NOT EXISTS)</span></span>
                            </label>
                            <p class="text-[11px] text-on-surface-variant leading-relaxed">
                                Le SQL reprend toutes les colonnes de chaque table pour rester réimportable. Il ne contient aucun DROP : l'importer ne peut pas effacer de données existantes.
                            </p>
                        </div>
                    </div>

                    <div class="assure-card p-5">
                        <h3 class="text-xs font-bold uppercase tracking-wide text-on-surface-variant mb-1">Période <span class="normal-case font-normal">(facultatif)</span></h3>
                        <p class="text-[11px] text-on-surface-variant mb-3">Filtre sur la date de création (date de soumission pour les dossiers). Les éléments sans date sont exportés en entier.</p>
                        <div class="grid grid-cols-2 gap-2">
                            <label class="text-[11px] text-on-surface-variant">Du
                                <input v-model="from" class="mt-1 w-full px-2 py-2 text-xs border border-outline-variant rounded-lg" type="date" :max="to || undefined" />
                            </label>
                            <label class="text-[11px] text-on-surface-variant">Au
                                <input v-model="to" class="mt-1 w-full px-2 py-2 text-xs border border-outline-variant rounded-lg" type="date" :min="from || undefined" />
                            </label>
                        </div>
                        <button v-if="from || to" type="button" class="mt-2 text-[11px] font-bold text-primary hover:underline" @click="from = ''; to = ''">Effacer la période</button>
                    </div>

                    <div class="assure-card p-5">
                        <p v-if="selectedItems.length" class="text-sm mb-1">
                            <strong>{{ selectedItems.length }}</strong> élément{{ selectedItems.length > 1 ? 's' : '' }} ·
                            <strong>{{ numberFr(selectedRows) }}</strong> ligne{{ selectedRows > 1 ? 's' : '' }}<span v-if="(from || to) && hasDatedSelection" class="text-on-surface-variant"> au maximum</span>
                        </p>
                        <p v-else class="text-sm text-on-surface-variant mb-1">Cochez les éléments à exporter.</p>
                        <p v-if="emptyColumns.length" class="text-[11px] text-error mb-2">
                            Aucune colonne choisie pour : {{ emptyColumns.map((i) => i.label).join(', ') }}.
                        </p>

                        <CamaLoadingButton
                            button-class="w-full mt-3 py-3 text-sm"
                            :disabled="!canExport"
                            :loading="busy"
                            loading-text="Préparation du fichier…"
                            @click="runExport"
                        >
                            <span class="material-symbols-outlined text-[18px]">download</span>
                            Exporter en {{ formats.find((f) => f.key === format).label }}
                        </CamaLoadingButton>

                        <p v-if="error" class="mt-3 text-xs text-error flex items-start gap-1.5">
                            <span class="material-symbols-outlined text-[16px]">error</span>{{ error }}
                        </p>
                        <p class="mt-3 text-[11px] text-on-surface-variant flex items-start gap-1.5">
                            <span class="material-symbols-outlined text-[16px]">lock</span>
                            Mots de passe, jetons et codes de sécurité ne sont jamais exportés.
                        </p>
                    </div>

                    <div class="assure-card p-5">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="material-symbols-outlined text-tertiary">description</span>
                            <h3 class="text-sm font-bold">Fiche dossier (PDF)</h3>
                        </div>
                        <p class="text-[11px] text-on-surface-variant mb-3">Archivage papier d'un dossier individuel.</p>
                        <form class="flex gap-2" @submit.prevent="exportPdf">
                            <input v-model="refPdf" class="flex-1 min-w-0 px-3 py-2 text-xs border border-outline-variant rounded-lg" placeholder="Référence (ex : CAMA-2025-88213)" type="text" />
                            <CamaLoadingButton type="submit" variant="dark" :loading="pdfBusy" loading-text="…">PDF</CamaLoadingButton>
                        </form>
                    </div>
                </aside>
            </div>
        </div>

        <div v-if="toast" class="fixed bottom-6 right-6 left-6 sm:left-auto z-[70]">
            <div class="bg-on-background text-white px-5 py-3 rounded-lg shadow-xl flex items-center gap-2 font-label-md text-label-md">
                <span class="material-symbols-outlined">download</span> {{ toast }}
            </div>
        </div>
    </AdminLayout>
</template>
