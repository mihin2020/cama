<script setup>
import AssureLayout from '@/Layouts/AssureLayout.vue';
import CamaConfirmModal from '@/Components/CamaConfirmModal.vue';
import { useCamaConfirm } from '@/Composables/useCamaConfirm';
import { exportFamilleFif, exportFamilleZip, exportMembreFif } from '@/Composables/useCamaExport';
import { pieceBadgeClass, statusBadgeClass } from '@/Utils/camaStatus';
import { ACCEPTED_TYPES, MAX_FILE_SIZE } from '@/Utils/familleWizard';
import { formatPhonesDisplay } from '@/Utils/camaPhone';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    membres: Array,
    unreadCount: Number,
    peutEnroler: Boolean,
    assureExport: Object,
});

const filter = ref('Tous');
const filters = [
    { value: 'Tous', label: 'Tous' },
    { value: 'Brouillon', label: 'Brouillon' },
    { value: 'Soumis', label: 'Soumis' },
    { value: 'En instruction', label: 'En instruction' },
    { value: 'Pièce manquante demandée', label: 'Pièce manquante' },
    { value: 'Validé', label: 'Validé' },
    { value: 'Refusé', label: 'Refusé' },
];

const toast = ref({ show: false, html: '' });
const exportingZip = ref(false);
const complementFiles = ref({});
const complementSubmitting = ref({});
const complementErrors = ref({});

const { confirmState, askConfirm, confirm, cancel } = useCamaConfirm();

const brouillons = computed(() => props.membres.filter((m) => m.statut === 'Brouillon'));
const hasValidatedMembers = computed(() => props.membres.some((m) => m.statut === 'Validé'));
const ajouterLabel = computed(() => (hasValidatedMembers.value ? 'Ajouter de nouveaux membres' : 'Dossier familial'));

// État global du dossier familial (workflow) à partir des membres soumis.
const familyStatus = computed(() => {
    const submitted = props.membres.filter((m) => m.statut !== 'Brouillon');
    const total = submitted.length;
    const validated = submitted.filter((m) => m.statut === 'Validé').length;
    const refused = submitted.filter((m) => m.statut === 'Refusé').length;
    const complement = submitted.filter((m) => m.statut === 'Pièce manquante demandée').length;
    const enInstruction = submitted.filter((m) => ['Soumis', 'En instruction', 'En attente supervision'].includes(m.statut)).length;
    const allValidated = total > 0 && validated === total;

    let key = 'aucun';
    if (total === 0) key = 'aucun';
    else if (allValidated) key = 'valide';
    else if (complement > 0) key = 'complement';
    else key = 'instruction';

    // Étape courante du workflow : 1 = soumission, 2 = instruction, 3 = validation.
    const step = total === 0 ? 0 : (allValidated ? 3 : 2);

    return {
        key,
        step,
        total,
        validated,
        refused,
        complement,
        enInstruction,
        allValidated,
        pct: total ? Math.round((validated / total) * 100) : 0,
    };
});

const WORKFLOW_STEPS = [
    { n: 1, label: 'Soumission', icon: 'send' },
    { n: 2, label: 'Instruction CAMA', icon: 'fact_check' },
    { n: 3, label: 'Validation', icon: 'verified' },
];

function pieceHasFile(p) {
    return !!(p?.hasFile || p?.path);
}

function pieceViewUrl(m, p) {
    if (!pieceHasFile(p) || !p?.type) return null;
    return `${route('assure.dossiers.piece.download', m.id)}?type=${encodeURIComponent(p.type)}&inline=1`;
}

function pieceDownloadUrl(m, p) {
    if (!pieceHasFile(p) || !p?.type) return null;
    return `${route('assure.dossiers.piece.download', m.id)}?type=${encodeURIComponent(p.type)}`;
}

const filtered = computed(() => {
    if (filter.value === 'Tous') return props.membres;
    return props.membres.filter((m) => m.statut === filter.value);
});

const filterCount = (value) => {
    if (value === 'Tous') return props.membres.length;
    return props.membres.filter((m) => m.statut === value).length;
};

const parentLabel = computed(() =>
    props.assureExport?.sexe === 'Féminin' ? 'Nom et prénoms du père' : 'Nom et prénoms de la mère',
);

function showToast(html) {
    toast.value = { show: true, html };
    setTimeout(() => {
        toast.value.show = false;
    }, 3000);
}

function memberInfoRows(m) {
    const rows = [
        ['Date de naissance', m.dateNaissance],
        ['Lieu de naissance', m.lieuNaissance],
        ['Sexe', m.sexe],
        ['Groupe sanguin', m.groupeSanguin],
        ['Téléphone', m.telephone ? formatPhonesDisplay(m.telephone) : null],
        ['Numéro CAMA', m.numeroCama],
        ['Date de soumission', m.dateSoumission],
    ];

    if ((m.lien || '').startsWith('Enfant')) {
        rows.push(["Réf. d'identité", m.refIdentite]);
        rows.push(['Réf. acte scolarité / état civil', m.refActeScolariteEtatCivil]);
        rows.push([parentLabel.value, m.nomPrenomsParent]);
    } else if (m.lien === 'Conjoint(e)') {
        rows.push(["Réf. document d'identité", m.refIdentite]);
        rows.push(['Réf. acte de mariage', m.refActeMariage]);
        rows.push(['Profession', m.profession]);
        rows.push(['Lieu de résidence', m.lieuResidence]);
        rows.push(['Nationalité', m.nationalite]);
    }

    return rows.filter(([, value]) => value && String(value).trim() && value !== '—');
}

function missingPieces(m) {
    return (m.pieces || []).filter((p) => p.statut === 'Manquante');
}

function canModify(m) {
    return m.statut === 'Brouillon' || m.statut === 'Pièce manquante demandée';
}

async function exportDossier(m, index) {
    try {
        await exportMembreFif(m, props.assureExport, index + 1);
        showToast('<span class="material-symbols-outlined">picture_as_pdf</span> FIF généré.');
    } catch {
        showToast('<span class="material-symbols-outlined">error</span> Export indisponible (librairie non chargée).');
    }
}

async function exportAllZip() {
    if (!props.membres.length) {
        showToast('<span class="material-symbols-outlined">info</span> Aucun dossier à exporter.');
        return;
    }
    exportingZip.value = true;
    try {
        await exportFamilleZip(props.membres, props.assureExport);
        showToast(`<span class="material-symbols-outlined">folder_zip</span> Archive ZIP de ${props.membres.length} dossier(s) générée.`);
    } catch {
        showToast('<span class="material-symbols-outlined">error</span> Export ZIP indisponible (librairie non chargée).');
    } finally {
        exportingZip.value = false;
    }
}

async function exportFormulaire() {
    try {
        await exportFamilleFif(props.membres, props.assureExport);
        showToast('<span class="material-symbols-outlined">picture_as_pdf</span> FIF familial généré.');
    } catch {
        showToast('<span class="material-symbols-outlined">error</span> Export indisponible (librairie non chargée).');
    }
}

function deleteMembre(id) {
    askConfirm({
        title: 'Supprimer ce membre ?',
        message: 'Le dossier sera définitivement retiré de votre liste. Cette action est irréversible.',
        confirmLabel: 'Supprimer',
        variant: 'danger',
        onConfirm: () => router.delete(route('assure.dossiers.destroy', id), {
            preserveScroll: true,
            onSuccess: () => showToast('<span class="material-symbols-outlined">check_circle</span> Membre supprimé.'),
        }),
    });
}

function requestWithdrawal(id) {
    router.post(route('assure.dossiers.retrait', id), {}, {
        preserveScroll: true,
        onSuccess: () => showToast('<span class="material-symbols-outlined">check_circle</span> Demande de retrait envoyée au gestionnaire.'),
    });
}

function complementFileName(dossierId, key) {
    return complementFiles.value[dossierId]?.[key]?.name || null;
}

function validateComplementFile(file) {
    if (!file) return 'Fichier requis.';
    if (!ACCEPTED_TYPES.includes(file.type)) return 'Format non accepté (PDF, JPEG ou PNG).';
    if (file.size > MAX_FILE_SIZE) return 'Fichier trop volumineux (5 Mo max).';
    return null;
}

function setComplementFile(dossierId, key, event) {
    const file = event.target.files?.[0];
    if (!complementFiles.value[dossierId]) {
        complementFiles.value[dossierId] = {};
    }
    if (!complementErrors.value[dossierId]) {
        complementErrors.value[dossierId] = {};
    }

    if (!file) {
        delete complementFiles.value[dossierId][key];
        delete complementErrors.value[dossierId][key];
        return;
    }

    const error = validateComplementFile(file);
    if (error) {
        complementErrors.value[dossierId][key] = error;
        delete complementFiles.value[dossierId][key];
        event.target.value = '';
        return;
    }

    complementErrors.value[dossierId][key] = null;
    complementFiles.value[dossierId][key] = file;
}

function submitComplement(m) {
    const missing = missingPieces(m);
    const files = complementFiles.value[m.id] || {};
    const errors = complementErrors.value[m.id] || {};

    if (missing.length) {
        for (const piece of missing) {
            if (!files[piece.type]) {
                showToast(`<span class="material-symbols-outlined">warning</span> Joignez le fichier : ${piece.type}.`);
                return;
            }
            if (errors[piece.type]) {
                showToast(`<span class="material-symbols-outlined">error</span> ${errors[piece.type]}`);
                return;
            }
        }
    } else if (!files.__generic) {
        showToast('<span class="material-symbols-outlined">warning</span> Sélectionnez un fichier à envoyer.');
        return;
    }

    const formData = new FormData();

    if (missing.length) {
        for (const piece of missing) {
            formData.append(`piece_files[${piece.type}]`, files[piece.type]);
        }
    } else {
        formData.append('complement_file', files.__generic);
    }

    complementSubmitting.value[m.id] = true;

    router.post(route('assure.dossiers.complement', m.id), formData, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            delete complementFiles.value[m.id];
            delete complementErrors.value[m.id];
            showToast('<span class="material-symbols-outlined">check_circle</span> Pièces complémentaires envoyées.');
        },
        onError: (errs) => {
            const first = Object.values(errs)[0];
            showToast(`<span class="material-symbols-outlined">error</span> ${Array.isArray(first) ? first[0] : first || 'Envoi impossible.'}`);
        },
        onFinish: () => {
            complementSubmitting.value[m.id] = false;
        },
    });
}
</script>

<template>
    <Head title="Ma famille" />

    <AssureLayout active-nav="famille" title="Ma famille" subtitle="Suivi de vos dossiers d'enrôlement" :unread-count="unreadCount">
        <template #header-actions>
            <button
                type="button"
                class="hidden sm:flex items-center gap-2 border border-outline text-on-surface px-4 py-2 rounded-lg font-bold font-label-md text-label-md hover:bg-surface-container-low transition-all"
                @click="exportFormulaire"
            >
                <span class="material-symbols-outlined text-[18px]">picture_as_pdf</span> FIF familial (PDF)
            </button>
            <button
                type="button"
                class="hidden sm:flex items-center gap-2 border border-outline text-on-surface px-4 py-2 rounded-lg font-bold font-label-md text-label-md hover:bg-surface-container-low transition-all disabled:opacity-60"
                :disabled="exportingZip"
                @click="exportAllZip"
            >
                <span class="material-symbols-outlined text-[18px]" :class="exportingZip ? 'animate-spin' : ''">{{ exportingZip ? 'sync' : 'folder_zip' }}</span>
                {{ exportingZip ? 'Préparation…' : 'Tout télécharger (ZIP)' }}
            </button>
            <Link
                class="hidden sm:flex items-center gap-2 bg-primary text-on-primary px-4 py-2 rounded-lg font-bold font-label-md text-label-md hover:opacity-90 active:scale-95 transition-all shadow-sm"
                :href="route('assure.ajouter-membre')"
            >
                <span class="material-symbols-outlined text-[18px]">group_add</span> {{ ajouterLabel }}
            </Link>
        </template>

        <!-- Suivi du dossier familial (workflow) -->
        <div v-if="familyStatus.total" class="mb-6 bg-white border border-outline-variant rounded-xl p-4 md:p-5">
            <!-- Bandeau d'état global -->
            <div
                class="rounded-lg p-3.5 flex items-start gap-3 mb-4"
                :class="{
                    'bg-secondary/10 border border-secondary/30': familyStatus.key === 'valide',
                    'bg-tertiary/5 border border-tertiary/30': familyStatus.key === 'complement',
                    'bg-primary/5 border border-primary/20': familyStatus.key === 'instruction',
                }"
            >
                <span
                    class="material-symbols-outlined text-[28px] shrink-0"
                    :class="{
                        'text-secondary': familyStatus.key === 'valide',
                        'text-tertiary': familyStatus.key === 'complement',
                        'text-primary': familyStatus.key === 'instruction',
                    }"
                >{{ familyStatus.key === 'valide' ? 'task_alt' : (familyStatus.key === 'complement' ? 'assignment_late' : 'hourglass_top') }}</span>
                <div class="flex-1 min-w-0">
                    <p class="font-bold text-sm text-on-surface">
                        <template v-if="familyStatus.key === 'valide'">Dossier familial validé — tous vos membres sont pris en charge ✅</template>
                        <template v-else-if="familyStatus.key === 'complement'">Une pièce complémentaire est demandée</template>
                        <template v-else>Dossier en cours d'instruction par la CAMA</template>
                    </p>
                    <p class="text-xs text-on-surface-variant mt-1">
                        <strong class="text-on-surface">{{ familyStatus.validated }}/{{ familyStatus.total }}</strong> membre(s) validé(s)
                        <span v-if="familyStatus.complement"> · {{ familyStatus.complement }} en attente de pièce</span>
                        <span v-if="familyStatus.enInstruction"> · {{ familyStatus.enInstruction }} en examen</span>
                        <span v-if="familyStatus.refused"> · {{ familyStatus.refused }} refusé(s)</span>
                    </p>
                </div>
                <span class="text-sm font-extrabold tabular-nums shrink-0" :class="familyStatus.key === 'valide' ? 'text-secondary' : 'text-primary'">{{ familyStatus.pct }}%</span>
            </div>

            <!-- Étapes du workflow -->
            <div class="flex items-center">
                <template v-for="(s, i) in WORKFLOW_STEPS" :key="s.n">
                    <div class="flex flex-col items-center text-center shrink-0 w-20">
                        <span
                            class="w-9 h-9 rounded-full flex items-center justify-center border-2 transition-colors"
                            :class="familyStatus.step >= s.n
                                ? (s.n === 3 && familyStatus.allValidated ? 'bg-secondary border-secondary text-on-secondary' : 'bg-primary border-primary text-on-primary')
                                : 'bg-white border-outline-variant text-on-surface-variant'"
                        >
                            <span class="material-symbols-outlined text-[18px]">{{ familyStatus.step > s.n ? 'check' : s.icon }}</span>
                        </span>
                        <span class="text-[10px] font-semibold mt-1.5 leading-tight" :class="familyStatus.step >= s.n ? 'text-on-surface' : 'text-on-surface-variant'">{{ s.label }}</span>
                    </div>
                    <div v-if="i < WORKFLOW_STEPS.length - 1" class="flex-1 h-0.5 mx-1 rounded" :class="familyStatus.step > s.n ? (familyStatus.allValidated && s.n === 2 ? 'bg-secondary' : 'bg-primary') : 'bg-outline-variant'" />
                </template>
            </div>
        </div>

        <div v-if="brouillons.length" class="mb-6 bg-tertiary/5 border border-tertiary/30 rounded-xl p-4 flex flex-col sm:flex-row sm:items-center gap-3">
            <span class="material-symbols-outlined text-tertiary text-[26px] shrink-0">edit_note</span>
            <div class="flex-1">
                <p class="font-bold text-sm text-on-surface">{{ brouillons.length }} membre(s) en brouillon</p>
                <p class="text-xs text-on-surface-variant mt-1">
                    Reprenez votre dossier familial là où vous vous êtes arrêté — vos informations et pièces déjà téléversées sont conservées.
                    La <strong class="text-on-surface">FIF signée</strong> n'est demandée qu'au moment de la soumission finale.
                </p>
                <p class="text-[11px] text-on-surface-variant mt-1.5 flex items-start gap-1">
                    <span class="material-symbols-outlined text-tertiary text-[14px] shrink-0">schedule</span>
                    Signatures en cours ? Enregistrez en brouillon et revenez quand la FIF est signée.
                </p>
            </div>
            <Link
                class="px-4 py-2 rounded-lg bg-tertiary text-on-tertiary text-xs font-bold hover:opacity-90 flex items-center justify-center gap-1.5 shrink-0"
                :href="route('assure.ajouter-membre')"
            >
                <span class="material-symbols-outlined text-[18px]">play_arrow</span> Continuer le dossier familial
            </Link>
        </div>

        <div class="flex flex-wrap gap-2 mb-6">
            <button
                v-for="f in filters"
                :key="f.value"
                type="button"
                class="px-4 py-2 rounded-full font-label-md text-label-md transition-colors"
                :class="filter === f.value ? 'bg-primary text-on-primary' : 'bg-surface-container-high text-on-surface-variant hover:bg-outline-variant'"
                @click="filter = f.value"
            >
                {{ f.label }}
                <span
                    class="ml-1.5 inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 rounded-full text-[10px] font-bold"
                    :class="filter === f.value ? 'bg-white/25 text-white' : 'bg-on-surface/10 text-on-surface'"
                >{{ filterCount(f.value) }}</span>
            </button>
        </div>

        <div class="space-y-4">
            <details
                v-for="(m, index) in filtered"
                :key="m.id"
                class="member-row group bg-white rounded-xl border border-outline-variant overflow-hidden"
                :id="`membre-${m.id}`"
            >
                <summary class="flex items-center gap-4 px-6 py-4 cursor-pointer hover:bg-surface-container-low transition-colors list-none">
                    <div class="w-10 h-10 rounded-full bg-surface-container flex items-center justify-center font-bold text-sm text-primary shrink-0">{{ m.initiales }}</div>
                    <div class="flex-1 min-w-0">
                        <p class="font-bold text-sm text-on-surface truncate">{{ m.prenom }} {{ m.nom }}</p>
                        <p class="text-[11px] text-on-surface-variant">{{ m.lien }}{{ m.ref ? ` · ${m.ref}` : '' }}</p>
                    </div>
                    <span
                        v-if="m.isComplementFamilial"
                        class="px-2.5 py-1 rounded-full text-[10px] font-bold whitespace-nowrap shrink-0 bg-tertiary/15 text-tertiary border border-tertiary/30"
                    >Complément familial</span>
                    <span class="px-3 py-1 rounded-full text-xs font-bold whitespace-nowrap shrink-0" :class="statusBadgeClass(m.statut)">{{ m.statut }}</span>
                    <span class="material-symbols-outlined text-on-surface-variant group-open:rotate-180 transition-transform">expand_more</span>
                </summary>

                <div class="px-6 pb-6 pt-2 border-t border-outline-variant">
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-6 mb-6">
                        <div v-for="([label, value], i) in memberInfoRows(m)" :key="i">
                            <p class="text-[11px] text-on-surface-variant uppercase tracking-wide mb-1">{{ label }}</p>
                            <p class="font-bold text-xs">{{ value }}</p>
                        </div>
                    </div>

                    <div v-if="m.motifRefus" class="mt-4 bg-error/5 border border-error/20 rounded-lg p-3 flex gap-3">
                        <span class="material-symbols-outlined text-error text-[18px]">error</span>
                        <p class="text-xs text-on-surface-variant"><strong class="text-error">Motif du refus :</strong> {{ m.motifRefus }}</p>
                    </div>

                    <div v-if="m.statut === 'Pièce manquante demandée'" class="mt-4 p-4 bg-tertiary/5 border border-tertiary/30 rounded-lg">
                        <p class="text-xs font-bold text-on-surface mb-2 flex items-center gap-2">
                            <span class="material-symbols-outlined text-[16px] text-tertiary">upload_file</span> Pièces complémentaires demandées
                        </p>
                        <p v-if="!missingPieces(m).length" class="text-xs text-on-surface-variant mb-3">
                            Consultez le message du gestionnaire dans les échanges ci-dessous et joignez le document demandé.
                        </p>
                        <div class="space-y-3">
                            <div
                                v-for="piece in (missingPieces(m).length ? missingPieces(m) : [{ type: '__generic', label: 'Document complémentaire' }])"
                                :key="piece.type"
                                class="border border-outline-variant rounded-lg p-3 bg-white"
                            >
                                <p class="text-xs font-semibold text-on-surface mb-2">
                                    {{ piece.type === '__generic' ? piece.label : piece.type }}
                                    <span class="text-error">*</span>
                                </p>
                                <div
                                    v-if="complementFileName(m.id, piece.type)"
                                    class="text-[11px] flex items-center gap-1 bg-secondary/10 rounded px-2 py-1.5 mb-2"
                                >
                                    <span class="material-symbols-outlined text-secondary text-[14px]">task</span>
                                    <span class="truncate flex-1">{{ complementFileName(m.id, piece.type) }}</span>
                                </div>
                                <label class="text-[11px] text-primary font-semibold cursor-pointer inline-flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[15px]">upload_file</span>
                                    {{ complementFileName(m.id, piece.type) ? 'Remplacer le fichier' : 'Choisir un fichier' }}
                                    <input
                                        type="file"
                                        class="hidden"
                                        accept=".pdf,.jpg,.jpeg,.png"
                                        @change="setComplementFile(m.id, piece.type, $event)"
                                    />
                                </label>
                                <p class="text-[10px] text-on-surface-variant mt-1">PDF, JPEG ou PNG — 5 Mo max.</p>
                                <p v-if="complementErrors[m.id]?.[piece.type]" class="text-error text-[11px] mt-1">
                                    {{ complementErrors[m.id][piece.type] }}
                                </p>
                            </div>
                        </div>
                        <button
                            type="button"
                            class="mt-3 px-4 py-2 rounded-lg bg-tertiary text-on-tertiary text-xs font-bold hover:opacity-90 transition-opacity flex items-center gap-1.5 disabled:opacity-50"
                            :disabled="complementSubmitting[m.id]"
                            @click="submitComplement(m)"
                        >
                            <span class="material-symbols-outlined text-[16px]" :class="{ 'animate-spin': complementSubmitting[m.id] }">
                                {{ complementSubmitting[m.id] ? 'sync' : 'send' }}
                            </span>
                            {{ complementSubmitting[m.id] ? 'Envoi en cours…' : 'Envoyer les pièces' }}
                        </button>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                        <div>
                            <h4 class="font-bold text-xs uppercase tracking-wide mb-2 flex items-center gap-2">
                                <span class="material-symbols-outlined text-[16px] text-primary">forum</span> Échanges avec le gestionnaire
                            </h4>
                            <div class="bg-surface-container-low rounded-lg p-3 space-y-2 mb-2 max-h-48 overflow-y-auto">
                                <div v-for="(msg, i) in m.messages || []" :key="i" class="flex" :class="msg.auteur === 'gestionnaire' ? 'justify-start' : 'justify-end'">
                                    <div class="max-w-[90%] rounded-xl px-3 py-2" :class="msg.auteur === 'gestionnaire' ? 'bg-white border border-outline-variant text-on-surface' : 'bg-primary text-on-primary'">
                                        <p class="text-xs">{{ msg.texte }}</p>
                                        <p class="text-[10px] opacity-70 mt-1">{{ msg.date }}</p>
                                    </div>
                                </div>
                                <p v-if="!m.messages?.length" class="text-xs text-on-surface-variant italic">Aucun message échangé.</p>
                            </div>
                        </div>
                        <div>
                            <h4 class="font-bold text-xs uppercase tracking-wide mb-2 flex items-center gap-2">
                                <span class="material-symbols-outlined text-[16px] text-primary">folder</span> Pièces justificatives
                            </h4>
                            <div class="bg-surface-container-low rounded-lg px-4 py-1">
                                <template v-if="m.pieces?.length">
                                    <div
                                        v-for="p in m.pieces"
                                        :key="p.type"
                                        class="flex items-center justify-between py-2 border-b border-outline-variant last:border-0"
                                    >
                                        <span class="text-xs flex items-center gap-2">
                                            <span class="material-symbols-outlined text-[16px] text-on-surface-variant">description</span>
                                            {{ p.type }}
                                        </span>
                                        <span class="flex items-center gap-2 shrink-0">
                                            <template v-if="pieceHasFile(p)">
                                                <a
                                                    :href="pieceViewUrl(m, p)"
                                                    target="_blank"
                                                    rel="noopener"
                                                    class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-white text-primary"
                                                    title="Visualiser"
                                                >
                                                    <span class="material-symbols-outlined text-[18px]">visibility</span>
                                                </a>
                                                <a
                                                    :href="pieceDownloadUrl(m, p)"
                                                    class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-white text-on-surface-variant"
                                                    title="Télécharger"
                                                >
                                                    <span class="material-symbols-outlined text-[18px]">download</span>
                                                </a>
                                            </template>
                                            <span v-else class="text-[10px] text-on-surface-variant italic">Non disponible</span>
                                            <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold whitespace-nowrap" :class="pieceBadgeClass(p.statut)">{{ p.statut }}</span>
                                        </span>
                                    </div>
                                </template>
                                <p v-else class="text-xs text-on-surface-variant italic py-2">Aucune pièce téléversée.</p>
                            </div>
                        </div>
                        <div>
                            <h4 class="font-bold text-xs uppercase tracking-wide mb-2 flex items-center gap-2">
                                <span class="material-symbols-outlined text-[16px] text-primary">history</span> Historique du dossier
                            </h4>
                            <div class="bg-surface-container-low rounded-lg px-4 py-3">
                                <div v-for="(h, i) in m.historique || m.journal" :key="i" class="flex gap-3 relative">
                                    <div class="relative flex flex-col items-center">
                                        <div class="w-2 h-2 rounded-full bg-primary z-10 mt-1" />
                                        <div v-if="i < (m.historique || m.journal).length - 1" class="w-0.5 flex-1 bg-outline-variant" />
                                    </div>
                                    <div class="pb-3">
                                        <p class="text-xs font-bold text-on-surface">{{ h.libelle }}</p>
                                        <p class="text-[11px] text-on-surface-variant">{{ h.date }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2 mt-6 pt-4 border-t border-outline-variant">
                        <Link
                            v-if="canModify(m)"
                            :href="`${route('assure.ajouter-membre')}?draft=${m.id}`"
                            class="px-3 py-1.5 rounded-lg border border-outline text-on-surface text-xs font-bold hover:bg-surface-container-low transition-colors flex items-center gap-1.5"
                            title="Reprendre le dossier familial en brouillon (tous les membres) — ce membre sera mis en évidence"
                        >
                            <span class="material-symbols-outlined text-[16px]">edit</span> Modifier
                        </Link>
                        <button
                            v-if="m.statut === 'Validé'"
                            type="button"
                            class="px-3 py-1.5 rounded-lg border border-error text-error text-xs font-bold hover:bg-error/5 transition-colors flex items-center gap-1.5"
                            @click="requestWithdrawal(m.id)"
                        >
                            <span class="material-symbols-outlined text-[16px]">person_remove</span> Demander un retrait
                        </button>
                        <button
                            v-else-if="m.statut === 'Brouillon'"
                            type="button"
                            class="px-3 py-1.5 rounded-lg border border-error text-error text-xs font-bold hover:bg-error/5 transition-colors flex items-center gap-1.5"
                            @click="deleteMembre(m.id)"
                        >
                            <span class="material-symbols-outlined text-[16px]">delete</span> Supprimer
                        </button>
                        <button
                            v-if="m.dossierId || m.ref"
                            type="button"
                            class="px-3 py-1.5 rounded-lg bg-surface-container-high text-on-surface text-xs font-bold hover:bg-outline-variant transition-colors flex items-center gap-1.5"
                            @click="exportDossier(m, index)"
                        >
                            <span class="material-symbols-outlined text-[16px]">picture_as_pdf</span> Télécharger FIF
                        </button>
                    </div>
                </div>
            </details>
        </div>

        <div v-if="!filtered.length" class="text-center py-16 bg-white rounded-xl border border-outline-variant">
            <span class="material-symbols-outlined text-5xl text-outline-variant mb-3 block">folder_off</span>
            <p class="text-on-surface-variant font-bold mb-1">Aucun dossier dans cette catégorie</p>
            <button type="button" class="text-primary font-bold hover:underline" @click="filter = 'Tous'">Voir tous les dossiers</button>
        </div>

        <div v-if="peutEnroler" class="mt-6 sm:hidden">
            <Link :href="route('assure.ajouter-membre')" class="flex items-center justify-center gap-2 bg-primary text-on-primary px-4 py-3 rounded-lg font-bold">
                {{ ajouterLabel }}
            </Link>
        </div>
    </AssureLayout>

    <div v-if="toast.show" class="fixed bottom-6 right-6 z-50">
        <div class="bg-on-background text-white px-5 py-3 rounded-lg shadow-xl flex items-center gap-2 font-label-md text-label-md" v-html="toast.html" />
    </div>

    <CamaConfirmModal
        :show="confirmState.show"
        :title="confirmState.title"
        :message="confirmState.message"
        :confirm-label="confirmState.confirmLabel"
        :cancel-label="confirmState.cancelLabel"
        :variant="confirmState.variant"
        :alert-only="confirmState.alertOnly"
        @confirm="confirm"
        @cancel="cancel"
    />
</template>

<style scoped>
summary::-webkit-details-marker {
    display: none;
}
</style>

