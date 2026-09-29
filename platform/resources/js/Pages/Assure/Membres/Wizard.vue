<script setup>
import AssureLayout from '@/Layouts/AssureLayout.vue';
import CamaConfirmModal from '@/Components/CamaConfirmModal.vue';
import CamaCountrySelect from '@/Components/CamaCountrySelect.vue';
import CamaPhoneInput from '@/Components/CamaPhoneInput.vue';
import { useCamaConfirm } from '@/Composables/useCamaConfirm';
import {
    ACCEPTED_TYPES,
    MAX_FILE_SIZE,
    PHOTO_TYPES,
    PIECE_LABELS,
    buildExportMembresFromState,
    computeAge,
    effectivePiecesForRow,
    existingPhoto,
    existingPiece,
    needsCertificatScolarite,
    newConjoint,
    newEnfant,
    photoSettingsForList,
    rowToPayload,
    validateFamille,
} from '@/Utils/familleWizard';
import { exportFamilleFif } from '@/Composables/useCamaExport';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue';

const props = defineProps({
    groupesSanguins: Array,
    ageMaxEnfant: Number,
    piecesMatrix: Object,
    filiationOptions: { type: Array, default: () => ['Enfant biologique', 'Enfant du conjoint', 'Enfant adopté'] },
    certificatScolarite: { type: Object, default: () => ({ actif: true, label: 'Certificat de scolarité' }) },
    membrePhoto: { type: Object, default: () => ({ conjoint: { actif: true, required: false }, enfant: { actif: true, required: false } }) },
    fifSigneeRequise: { type: Boolean, default: false },
    hasValidatedMembers: { type: Boolean, default: false },
    lotType: { type: String, default: 'initial' },
    parentLabel: String,
    unreadCount: Number,
    peutEnroler: Boolean,
    initialRows: Object,
    focusDraftId: { type: [Number, String], default: null },
    assureExport: Object,
    fifPieceType: { type: String, default: 'FIF signée' },
});

const page = usePage();
const inputCls = 'w-full px-3 py-2 text-sm border border-outline-variant rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition-all outline-none';

const { confirmState, askConfirm, confirm, cancel } = useCamaConfirm();

const state = reactive({ conjoints: [], enfants: [] });
const chkHonneur = ref(false);
const chkConsentement = ref(false);
const showWarning = ref(false);
const submitting = ref(false);
const submitted = ref(false);
const submittedCount = ref(0);
const toast = ref({ show: false, html: '' });
const pieceErrors = reactive({});
const fifSignee = ref(null);
const fifError = ref('');
const generatingFif = ref(false);
const fifGenerated = ref(false);
const fifInput = ref(null);
const highlightedDraftId = ref(null);

const showForm = computed(() => !submitted.value);

function memberRowId(row) {
    return `wizard-membre-${row.dossier_id || row.uid}`;
}

function isHighlightedRow(row) {
    return highlightedDraftId.value && row.dossier_id === highlightedDraftId.value;
}

async function focusDraftMember() {
    const id = props.focusDraftId ? Number(props.focusDraftId) : null;
    if (!id) return;

    const row = [...state.conjoints, ...state.enfants].find((r) => r.dossier_id === id);
    if (!row) return;

    highlightedDraftId.value = id;
    await nextTick();

    const el = document.getElementById(memberRowId(row));
    el?.scrollIntoView({ behavior: 'smooth', block: 'center' });

    setTimeout(() => {
        if (highlightedDraftId.value === id) {
            highlightedDraftId.value = null;
        }
    }, 4000);
}

const canSubmit = computed(() => {
    const hasMembers = (state.conjoints.length + state.enfants.length) > 0;
    const fifOk = !props.fifSigneeRequise || !!fifSignee.value;
    return props.peutEnroler && chkHonneur.value && chkConsentement.value && hasMembers && fifOk;
});

const validationOptions = computed(() => ({
    ageMax: props.ageMaxEnfant,
    piecesMatrix: props.piecesMatrix,
    certificatScolarite: props.certificatScolarite,
    membrePhoto: props.membrePhoto,
}));

const sortedEnfants = computed(() =>
    [...state.enfants].sort((a, b) => (a.dateNaissance || '9999').localeCompare(b.dateNaissance || '9999')),
);

function showToast(html) {
    toast.value = { show: true, html };
    setTimeout(() => { toast.value.show = false; }, 3000);
}

function mapServerRow(row, list) {
    const base = list === 'conjoints' ? newConjoint() : newEnfant();
    Object.assign(base, {
        dossier_id: row.id,
        nom: row.nom || '',
        prenoms: row.prenom || '',
        sexe: row.sexe || '',
        dateNaissance: row.date_naissance || '',
        lieuNaissance: row.lieu_naissance || '',
        groupeSanguin: row.groupe_sanguin || '',
        refIdentite: row.ref_identite || '',
        telephone: row.telephone || '',
        pieces: {},
        existing: Array.isArray(row.pieces) ? row.pieces : [],
        removedTypes: [],
    });
    if (list === 'conjoints') {
        base.refActeMariage = row.ref_acte_mariage || '';
        base.profession = row.profession || '';
        base.lieuResidence = row.lieu_residence || '';
        base.nationalite = row.nationalite || '';
    } else {
        base.refActeScolariteEtatCivil = row.ref_acte_scolarite || '';
        base.nomPrenomsParent = row.nom_prenoms_parent || '';
        base.filiation = row.lien || props.filiationOptions[0] || 'Enfant biologique';
    }
    return base;
}

function initState() {
    const fromServer = (props.initialRows?.conjoints?.length || 0) + (props.initialRows?.enfants?.length || 0);
    if (fromServer) {
        state.conjoints = (props.initialRows.conjoints || []).map((r) => mapServerRow(r, 'conjoints'));
        state.enfants = (props.initialRows.enfants || []).map((r) => mapServerRow(r, 'enfants'));
        return;
    }
    try {
        const raw = localStorage.getItem(`cama_famille_draft_${page.props.auth?.assure?.numeroCama || page.props.auth?.assure?.matricule}`);
        if (raw) {
            const data = JSON.parse(raw);
            state.conjoints = (data.conjoints || []).map((r) => ({ ...newConjoint(), ...r, pieces: {} }));
            state.enfants = (data.enfants || []).map((r) => ({ ...newEnfant(), ...r, pieces: {} }));
            if (state.conjoints.length + state.enfants.length > 0) return;
        }
    } catch { /* ignore */ }
    state.conjoints.push(newConjoint());
}

function payload() {
    return {
        conjoints: state.conjoints.map((r) => rowToPayload(r, 'conjoints')),
        enfants: state.enfants.map((r) => rowToPayload(r, 'enfants')),
    };
}

function saveDraft() {
    const strip = (r) => {
        const { pieces, uid, ...rest } = r;
        return rest;
    };
    localStorage.setItem(
        `cama_famille_draft_${page.props.auth?.assure?.numeroCama || page.props.auth?.assure?.matricule}`,
        JSON.stringify({ conjoints: state.conjoints.map(strip), enfants: state.enfants.map(strip) }),
    );
    router.post(route('assure.dossiers.draft'), payload(), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => showToast('<span class="material-symbols-outlined">save</span> Brouillon enregistré.'),
    });
}

function openSubmit() {
    if (!props.peutEnroler) {
        showToast('<span class="material-symbols-outlined">error</span> Votre compte doit être validé avant toute soumission.');
        return;
    }
    const problems = validateFamille(state, {
        ...validationOptions.value,
        fifSignee: fifSignee.value,
        requireFif: props.fifSigneeRequise,
    });
    if (problems.length) {
        showToast(`<span class="material-symbols-outlined">error</span> ${problems[0]}`);
        return;
    }
    showWarning.value = true;
}

async function generateFif() {
    const problems = validateFamille(state, validationOptions.value);
    if (problems.length) {
        showToast(`<span class="material-symbols-outlined">error</span> Complétez d'abord les informations des membres : ${problems[0]}`);
        return;
    }
    generatingFif.value = true;
    try {
        const membres = buildExportMembresFromState(state);
        await exportFamilleFif(membres, props.assureExport);
        fifGenerated.value = true;
        showToast('<span class="material-symbols-outlined">picture_as_pdf</span> FIF générée — imprimez-la et faites-la signer par le chef de corps et le GRH.');
    } catch {
        showToast('<span class="material-symbols-outlined">error</span> Impossible de générer la FIF.');
    } finally {
        generatingFif.value = false;
    }
}

function handleFifUpload(event) {
    fifError.value = '';
    const file = event.target.files?.[0];
    if (!file) return;
    if (!ACCEPTED_TYPES.includes(file.type)) {
        fifError.value = 'Format non accepté (PDF, JPEG, PNG).';
        event.target.value = '';
        return;
    }
    if (file.size > MAX_FILE_SIZE) {
        fifError.value = 'Fichier trop volumineux (5 Mo max).';
        event.target.value = '';
        return;
    }
    fifSignee.value = file;
}

function removeFif() {
    fifSignee.value = null;
    if (fifInput.value) {
        fifInput.value.value = '';
    }
}

function openFifPicker() {
    fifInput.value?.click();
}

function confirmSubmit() {
    submitting.value = true;
    const body = { ...payload() };
    if (fifSignee.value) {
        body.fif_signee = fifSignee.value;
    }
    router.post(route('assure.dossiers.submit'), body, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            const count = page.props.flash?.famille_submitted ?? (state.conjoints.length + state.enfants.length);
            submittedCount.value = count;
            submitted.value = true;
            showWarning.value = false;
            localStorage.removeItem(`cama_famille_draft_${page.props.auth?.assure?.numeroCama || page.props.auth?.assure?.matricule}`);
            window.scrollTo({ top: 0, behavior: 'smooth' });
        },
        onFinish: () => { submitting.value = false; },
    });
}

function removeRow(list, uid) {
    const label = list === 'conjoints' ? 'ce conjoint' : 'cet enfant';
    askConfirm({
        title: 'Retirer cette ligne ?',
        message: `Les informations saisies pour ${label} seront perdues.`,
        confirmLabel: 'Retirer',
        variant: 'warning',
        onConfirm: () => {
            state[list] = state[list].filter((r) => r.uid !== uid);
        },
    });
}

function handlePiece(list, row, key, event) {
    const errKey = `${row.uid}-${key}`;
    pieceErrors[errKey] = '';
    const file = event.target.files?.[0];
    if (!file) return;
    if (!ACCEPTED_TYPES.includes(file.type)) {
        pieceErrors[errKey] = 'Format non accepté (PDF, JPEG, PNG).';
        event.target.value = '';
        return;
    }
    if (file.size > MAX_FILE_SIZE) {
        pieceErrors[errKey] = 'Fichier trop volumineux (5 Mo max).';
        event.target.value = '';
        return;
    }
    row.pieces[key] = file;
}

function removePiece(row, key) {
    delete row.pieces[key];
}

function piecesFor(row, list) {
    return effectivePiecesForRow(row, list, props.piecesMatrix, {
        ageMax: props.ageMaxEnfant,
        certificatScolarite: props.certificatScolarite,
    });
}

function showPhoto(list) {
    return photoSettingsForList(list, props.membrePhoto).actif;
}

function photoRequired(list) {
    return photoSettingsForList(list, props.membrePhoto).required;
}

function photoPreviewUrl(row) {
    if (row.photoPreview) return row.photoPreview;
    if (row.photo instanceof File) {
        row.photoPreview = URL.createObjectURL(row.photo);
        return row.photoPreview;
    }
    return null;
}

function handlePhoto(row, event) {
    const errKey = `${row.uid}-photo`;
    pieceErrors[errKey] = '';
    const file = event.target.files?.[0];
    if (!file) return;
    if (!PHOTO_TYPES.includes(file.type)) {
        pieceErrors[errKey] = 'Format non accepté (JPEG, PNG, WebP).';
        event.target.value = '';
        return;
    }
    if (file.size > MAX_FILE_SIZE) {
        pieceErrors[errKey] = 'Fichier trop volumineux (5 Mo max).';
        event.target.value = '';
        return;
    }
    if (row.photoPreview) {
        try { URL.revokeObjectURL(row.photoPreview); } catch { /* ignore */ }
    }
    row.photo = file;
    row.photoPreview = URL.createObjectURL(file);
    const label = PIECE_LABELS.photo_membre;
    if (!row.removedTypes) row.removedTypes = [];
    // remplacer une photo existante côté serveur si nouvelle sélection
    const existing = existingPhoto(row);
    if (existing && !row.removedTypes.includes(existing.type)) {
        row.removedTypes.push(existing.type);
    }
}

function clearPhoto(row) {
    if (row.photoPreview) {
        try { URL.revokeObjectURL(row.photoPreview); } catch { /* ignore */ }
    }
    row.photo = null;
    row.photoPreview = '';
    const existing = existingPhoto(row);
    if (existing) {
        if (!row.removedTypes) row.removedTypes = [];
        if (!row.removedTypes.includes(existing.type)) row.removedTypes.push(existing.type);
    }
}

function keptPhoto(row) {
    const found = existingPhoto(row);
    if (!found) return null;
    if ((row.removedTypes || []).includes(found.type)) return null;
    return found;
}

function keptExisting(row, p) {
    const found = existingPiece(row, p);
    if (!found) return null;
    if ((row.removedTypes || []).includes(found.type)) return null;
    return found;
}

function removeExisting(row, p) {
    const found = existingPiece(row, p);
    if (!found) return;
    if (!row.removedTypes) row.removedTypes = [];
    if (!row.removedTypes.includes(found.type)) row.removedTypes.push(found.type);
}

function pieceDownloadUrl(row, found) {
    if (!row.dossier_id || !found?.type) return null;
    return `${route('assure.dossiers.piece.download', row.dossier_id)}?type=${encodeURIComponent(found.type)}`;
}

onMounted(async () => {
    if (page.props.flash?.famille_submitted) {
        submittedCount.value = page.props.flash.famille_submitted;
        submitted.value = true;
    } else {
        initState();
        await focusDraftMember();
    }
});

watch(() => page.props.flash?.famille_submitted, (v) => {
    if (v) {
        submittedCount.value = v;
        submitted.value = true;
    }
});

// Après enregistrement d'un brouillon, le serveur renvoie les dossiers à jour :
// on resynchronise l'état pour récupérer les identifiants et les pièces déjà stockées.
watch(() => props.initialRows, () => {
    if (!submitted.value) {
        initState();
    }
});
</script>

<template>
    <Head title="Mon dossier familial" />

    <AssureLayout
        active-nav="famille"
        title="Mon dossier familial"
        subtitle="Conjoint(e)s et enfants rattachés à l'assuré"
        :unread-count="unreadCount"
    >
        <div v-if="hasValidatedMembers && showForm" class="flex items-start gap-2 bg-tertiary/10 border border-tertiary/30 rounded-lg p-4 mb-5 text-sm">
            <span class="material-symbols-outlined text-tertiary text-[22px] shrink-0">group_add</span>
            <div class="text-xs text-on-surface-variant space-y-1">
                <p class="font-bold text-on-surface text-sm">Ajout complémentaire</p>
                <p>Vous avez déjà des membres <strong class="text-on-surface">validés</strong>. Ajoutez uniquement les <strong class="text-on-surface">nouveaux</strong> conjoints ou enfants. La soumission créera un lot complémentaire distinct, sans modifier les dossiers déjà validés.</p>
            </div>
        </div>

        <div v-if="!peutEnroler && showForm" class="flex items-start gap-2 bg-tertiary/15 border border-tertiary/40 rounded-lg p-4 mb-5 text-sm">
            <span class="material-symbols-outlined text-tertiary text-[20px] shrink-0">hourglass_top</span>
            <span>Votre compte est <strong>en attente de validation</strong> par la CAMA. Vous pouvez préparer votre dossier familial et l'enregistrer en brouillon, mais la <strong>soumission sera possible dès l'activation de votre compte</strong>.</span>
        </div>

        <div v-if="showForm" class="flex items-start gap-2 bg-primary/5 border border-primary/20 rounded-lg p-4 mb-5 text-sm">
            <span class="material-symbols-outlined text-primary text-[22px] shrink-0">route</span>
            <div class="space-y-2 text-xs text-on-surface-variant">
                <p class="font-bold text-on-surface text-sm">Parcours de soumission</p>
                <ol class="list-decimal list-inside space-y-1">
                    <li>Renseignez les membres et les pièces — vous pouvez <strong class="text-on-surface">enregistrer le brouillon</strong> à tout moment.</li>
                    <li v-if="fifSigneeRequise">Générez la FIF (PDF), imprimez-la et faites-la signer par le chef de corps et le GRH.</li>
                    <li v-if="fifSigneeRequise">Téléversez le scan de la FIF signée, puis cliquez <strong class="text-on-surface">Soumettre le dossier familial</strong>.</li>
                    <li v-else>Cochez les engagements puis cliquez <strong class="text-on-surface">Soumettre le dossier familial</strong>.</li>
                </ol>
                <p v-if="fifSigneeRequise" class="flex items-start gap-1.5 pt-1 border-t border-primary/15">
                    <span class="material-symbols-outlined text-tertiary text-[16px] shrink-0 mt-0.5">schedule</span>
                    <span><strong class="text-on-surface">Signatures longues ?</strong> Restez en brouillon et revenez plus tard : rien n'est transmis à la CAMA tant que vous n'avez pas soumis avec la FIF signée.</span>
                </p>
            </div>
        </div>

        <template v-if="showForm">
            <section class="bg-white rounded-xl border border-outline-variant p-4 md:p-6 mb-6">
                <div class="flex items-start justify-between gap-3 mb-1">
                    <div>
                        <h2 class="text-base font-semibold text-on-surface flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary text-[20px]">favorite</span> 2. Conjoint(e)s
                        </h2>
                        <p class="text-on-surface-variant text-xs">Marié(s) à la mairie uniquement.</p>
                    </div>
                    <button type="button" class="px-3 py-2 rounded-lg bg-primary text-on-primary text-xs font-semibold hover:opacity-90 flex items-center gap-1.5 shrink-0" @click="state.conjoints.push(newConjoint())">
                        <span class="material-symbols-outlined text-[18px]">add</span> Ajouter un conjoint
                    </button>
                </div>
                <div class="space-y-4 mt-4">
                    <div
                        v-for="(row, i) in state.conjoints"
                        :id="memberRowId(row)"
                        :key="row.uid"
                        class="border rounded-lg p-4 transition-colors duration-500"
                        :class="isHighlightedRow(row) ? 'border-primary ring-2 ring-primary/30 bg-primary/5' : 'border-outline-variant'"
                    >
                        <div class="flex items-start gap-3 mb-3">
                            <div v-if="showPhoto('conjoints')" class="shrink-0 text-center">
                                <div class="relative w-[72px] h-[72px] rounded-lg border border-dashed border-outline-variant bg-surface-container-low overflow-hidden flex items-center justify-center">
                                    <img v-if="photoPreviewUrl(row) || keptPhoto(row)" :src="photoPreviewUrl(row) || pieceDownloadUrl(row, keptPhoto(row))" class="w-full h-full object-cover" alt="" />
                                    <span v-else class="material-symbols-outlined text-[28px] text-primary/70">add_a_photo</span>
                                    <label class="absolute inset-0 cursor-pointer" title="Photo">
                                        <input type="file" class="hidden" accept=".jpg,.jpeg,.png,.webp,image/*" @change="handlePhoto(row, $event)" />
                                    </label>
                                    <button
                                        v-if="row.photo || keptPhoto(row)"
                                        type="button"
                                        class="absolute -top-2 -right-2 z-10 w-6 h-6 rounded-full bg-white border border-outline-variant text-error shadow flex items-center justify-center"
                                        @click="clearPhoto(row)"
                                    >
                                        <span class="material-symbols-outlined text-[14px]">close</span>
                                    </button>
                                </div>
                                <p class="text-[10px] text-on-surface-variant mt-1">Photo <span v-if="photoRequired('conjoints')" class="text-error">*</span></p>
                                <p v-if="pieceErrors[`${row.uid}-photo`]" class="text-error text-[10px]">{{ pieceErrors[`${row.uid}-photo`] }}</p>
                            </div>
                            <div class="flex-1 flex items-center justify-between min-w-0">
                                <p class="text-sm font-semibold">Conjoint {{ i + 1 }}</p>
                                <button type="button" class="text-error text-xs font-semibold flex items-center gap-1" @click="removeRow('conjoints', row.uid)">
                                    <span class="material-symbols-outlined text-[16px]">delete</span> Retirer
                                </button>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div class="space-y-1"><label class="text-xs font-medium text-on-surface-variant">Nom *</label><input v-model="row.nom" :class="inputCls" type="text" /></div>
                            <div class="space-y-1"><label class="text-xs font-medium text-on-surface-variant">Prénoms *</label><input v-model="row.prenoms" :class="inputCls" type="text" /></div>
                            <div class="space-y-1"><label class="text-xs font-medium text-on-surface-variant">Date de naissance *</label><input v-model="row.dateNaissance" :class="inputCls" type="date" /></div>
                            <div class="space-y-1"><label class="text-xs font-medium text-on-surface-variant">Lieu de naissance</label><input v-model="row.lieuNaissance" :class="inputCls" type="text" /></div>
                            <div class="space-y-1"><label class="text-xs font-medium text-on-surface-variant">Sexe *</label><select v-model="row.sexe" :class="inputCls + ' bg-white'"><option value="">—</option><option>Masculin</option><option>Féminin</option></select></div>
                            <div class="space-y-1"><label class="text-xs font-medium text-on-surface-variant">G.S.</label><select v-model="row.groupeSanguin" :class="inputCls + ' bg-white'"><option value="">—</option><option v-for="g in groupesSanguins" :key="g">{{ g }}</option></select></div>
                            <div class="space-y-1"><label class="text-xs font-medium text-on-surface-variant">Référence pièce d'identité (CNIB ou passeport)</label><input v-model="row.refIdentite" :class="inputCls" type="text" placeholder="Ex. CNIB B9033040 ou n° passeport" /></div>
                            <div class="space-y-1"><label class="text-xs font-medium text-on-surface-variant">Réf. Acte de mariage</label><input v-model="row.refActeMariage" :class="inputCls" type="text" /></div>
                            <div class="space-y-1"><label class="text-xs font-medium text-on-surface-variant">Profession</label><input v-model="row.profession" :class="inputCls" type="text" /></div>
                            <div class="space-y-1"><label class="text-xs font-medium text-on-surface-variant">Lieu de résidence</label><input v-model="row.lieuResidence" :class="inputCls" type="text" /></div>
                            <div class="space-y-1"><label class="text-xs font-medium text-on-surface-variant">Nationalité</label><CamaCountrySelect v-model="row.nationalite" /></div>
                            <div class="space-y-1 md:col-span-2"><label class="text-xs font-medium text-on-surface-variant">Téléphone(s)</label><CamaPhoneInput v-model="row.telephone" :max="2" :input-class="inputCls" /></div>
                        </div>
                        <div class="mt-3 pt-3 border-t border-outline-variant">
                            <p class="text-xs font-semibold text-on-surface-variant mb-2">Pièces justificatives</p>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                <div v-for="p in piecesFor(row, 'conjoints')" :key="p.key" class="border border-outline-variant rounded-lg p-2.5">
                                    <p class="text-xs mb-1.5">{{ p.label }} <span v-if="p.required" class="text-error">*</span><span v-else class="text-[10px] text-on-surface-variant">(optionnel)</span></p>
                                    <div v-if="row.pieces[p.key]" class="text-[11px] flex items-center gap-1 bg-surface-container-low rounded px-2 py-1 mb-1">
                                        <span class="material-symbols-outlined text-secondary text-[14px]">check_circle</span>
                                        <span class="truncate">{{ row.pieces[p.key].name }}</span>
                                        <button type="button" class="text-error ml-auto" @click="removePiece(row, p.key)"><span class="material-symbols-outlined text-[14px]">close</span></button>
                                    </div>
                                    <div v-else-if="keptExisting(row, p)" class="text-[11px] flex items-center gap-1 bg-secondary/10 rounded px-2 py-1 mb-1">
                                        <span class="material-symbols-outlined text-secondary text-[14px]">task</span>
                                        <a v-if="pieceDownloadUrl(row, keptExisting(row, p))" :href="pieceDownloadUrl(row, keptExisting(row, p))" target="_blank" class="truncate text-primary hover:underline">{{ keptExisting(row, p).filename || 'Fichier téléversé' }}</a>
                                        <span v-else class="truncate">{{ keptExisting(row, p).filename || 'Fichier téléversé' }}</span>
                                        <button type="button" class="text-error ml-auto" @click="removeExisting(row, p)"><span class="material-symbols-outlined text-[14px]">close</span></button>
                                    </div>
                                    <label class="text-[11px] text-primary font-semibold cursor-pointer inline-flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[15px]">upload_file</span> {{ (row.pieces[p.key] || keptExisting(row, p)) ? 'Remplacer le fichier' : 'Choisir un fichier' }}
                                        <input type="file" class="hidden" accept=".pdf,.jpg,.jpeg,.png" @change="handlePiece('conjoints', row, p.key, $event)" />
                                    </label>
                                    <p v-if="pieceErrors[`${row.uid}-${p.key}`]" class="text-error text-[11px] mt-1">{{ pieceErrors[`${row.uid}-${p.key}`] }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <p v-if="!state.conjoints.length" class="text-xs text-on-surface-variant italic mt-3">Aucun conjoint ajouté. Utilisez « Ajouter un conjoint » si nécessaire.</p>
            </section>

            <section class="bg-white rounded-xl border border-outline-variant p-4 md:p-6 mb-6">
                <div class="flex items-start justify-between gap-3 mb-1">
                    <div>
                        <h2 class="text-base font-semibold text-on-surface flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary text-[20px]">child_care</span> 3. Enfant(s)
                        </h2>
                        <p class="text-on-surface-variant text-xs">
                            À partir de {{ ageMaxEnfant }} ans,
                            <template v-if="certificatScolarite?.actif">un {{ certificatScolarite.label || 'certificat de scolarité' }} est exigé.</template>
                            <template v-else>aucun certificat de scolarité n'est exigé.</template>
                            Tri du plus âgé au plus jeune.
                        </p>
                    </div>
                    <button type="button" class="px-3 py-2 rounded-lg bg-primary text-on-primary text-xs font-semibold hover:opacity-90 flex items-center gap-1.5 shrink-0" @click="state.enfants.push(newEnfant())">
                        <span class="material-symbols-outlined text-[18px]">add</span> Ajouter un enfant
                    </button>
                </div>
                <div class="space-y-4 mt-4">
                    <div
                        v-for="(row, i) in sortedEnfants"
                        :id="memberRowId(row)"
                        :key="row.uid"
                        class="border rounded-lg p-4 transition-colors duration-500"
                        :class="isHighlightedRow(row)
                            ? 'border-primary ring-2 ring-primary/30 bg-primary/5'
                            : (needsCertificatScolarite(row, { ageMax: ageMaxEnfant, certificatScolarite }) ? 'border-tertiary/50' : 'border-outline-variant')"
                    >
                        <div class="flex items-start gap-3 mb-3">
                            <div v-if="showPhoto('enfants')" class="shrink-0 text-center">
                                <div class="relative w-[72px] h-[72px] rounded-lg border border-dashed border-outline-variant bg-surface-container-low overflow-hidden flex items-center justify-center">
                                    <img v-if="photoPreviewUrl(row) || keptPhoto(row)" :src="photoPreviewUrl(row) || pieceDownloadUrl(row, keptPhoto(row))" class="w-full h-full object-cover" alt="" />
                                    <span v-else class="material-symbols-outlined text-[28px] text-primary/70">add_a_photo</span>
                                    <label class="absolute inset-0 cursor-pointer" title="Photo">
                                        <input type="file" class="hidden" accept=".jpg,.jpeg,.png,.webp,image/*" @change="handlePhoto(row, $event)" />
                                    </label>
                                    <button
                                        v-if="row.photo || keptPhoto(row)"
                                        type="button"
                                        class="absolute -top-2 -right-2 z-10 w-6 h-6 rounded-full bg-white border border-outline-variant text-error shadow flex items-center justify-center"
                                        @click="clearPhoto(row)"
                                    >
                                        <span class="material-symbols-outlined text-[14px]">close</span>
                                    </button>
                                </div>
                                <p class="text-[10px] text-on-surface-variant mt-1">Photo <span v-if="photoRequired('enfants')" class="text-error">*</span></p>
                                <p v-if="pieceErrors[`${row.uid}-photo`]" class="text-error text-[10px]">{{ pieceErrors[`${row.uid}-photo`] }}</p>
                            </div>
                            <div class="flex-1 flex items-center justify-between min-w-0">
                                <p class="text-sm font-semibold">
                                    Enfant {{ i + 1 }}
                                    <span v-if="computeAge(row.dateNaissance) !== null" class="text-xs font-normal text-on-surface-variant">({{ computeAge(row.dateNaissance) }} ans)</span>
                                </p>
                                <button type="button" class="text-error text-xs font-semibold flex items-center gap-1" @click="removeRow('enfants', row.uid)">
                                    <span class="material-symbols-outlined text-[16px]">delete</span> Retirer
                                </button>
                            </div>
                        </div>
                        <div v-if="needsCertificatScolarite(row, { ageMax: ageMaxEnfant, certificatScolarite })" class="text-tertiary text-xs mb-2 flex items-center gap-1">
                            <span class="material-symbols-outlined text-[15px]">school</span>
                            Âge ≥ {{ ageMaxEnfant }} ans : {{ certificatScolarite.label || 'certificat de scolarité' }} obligatoire.
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div class="space-y-1"><label class="text-xs font-medium text-on-surface-variant">Nom *</label><input v-model="row.nom" :class="inputCls" type="text" /></div>
                            <div class="space-y-1"><label class="text-xs font-medium text-on-surface-variant">Prénoms *</label><input v-model="row.prenoms" :class="inputCls" type="text" /></div>
                            <div class="space-y-1"><label class="text-xs font-medium text-on-surface-variant">Date de naissance *</label><input v-model="row.dateNaissance" :class="inputCls" type="date" /></div>
                            <div class="space-y-1"><label class="text-xs font-medium text-on-surface-variant">Lieu de naissance</label><input v-model="row.lieuNaissance" :class="inputCls" type="text" /></div>
                            <div class="space-y-1"><label class="text-xs font-medium text-on-surface-variant">Sexe *</label><select v-model="row.sexe" :class="inputCls + ' bg-white'"><option value="">—</option><option>Masculin</option><option>Féminin</option></select></div>
                            <div class="space-y-1"><label class="text-xs font-medium text-on-surface-variant">G.S.</label><select v-model="row.groupeSanguin" :class="inputCls + ' bg-white'"><option value="">—</option><option v-for="g in groupesSanguins" :key="g">{{ g }}</option></select></div>
                            <div class="space-y-1"><label class="text-xs font-medium text-on-surface-variant">Référence pièce d'identité (CNIB ou passeport, si disponible)</label><input v-model="row.refIdentite" :class="inputCls" type="text" placeholder="CNIB ou passeport — si disponible" /></div>
                            <div class="space-y-1"><label class="text-xs font-medium text-on-surface-variant">Réf. Acte de scolarité / d'état civil</label><input v-model="row.refActeScolariteEtatCivil" :class="inputCls" type="text" /></div>
                            <div class="space-y-1"><label class="text-xs font-medium text-on-surface-variant">{{ parentLabel }}</label><input v-model="row.nomPrenomsParent" :class="inputCls" type="text" /></div>
                            <div class="space-y-1"><label class="text-xs font-medium text-on-surface-variant">Téléphone(s)</label><input v-model="row.telephone" :class="inputCls" type="text" /></div>
                            <div class="space-y-1">
                                <label class="text-xs font-medium text-on-surface-variant">Filiation</label>
                                <select v-model="row.filiation" :class="inputCls + ' bg-white'">
                                    <option v-for="opt in filiationOptions" :key="opt" :value="opt">{{ opt }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="mt-3 pt-3 border-t border-outline-variant">
                            <p class="text-xs font-semibold text-on-surface-variant mb-2">Pièces justificatives</p>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                <div
                                    v-for="p in piecesFor(row, 'enfants')"
                                    :key="p.key"
                                    class="border rounded-lg p-2.5"
                                    :class="p.key === 'certificat_scolarite' ? 'border-tertiary/40 bg-tertiary/5' : 'border-outline-variant'"
                                >
                                    <p class="text-xs mb-1.5">{{ p.label }} <span v-if="p.required" class="text-error">*</span><span v-else class="text-[10px] text-on-surface-variant">(optionnel)</span></p>
                                    <div v-if="row.pieces[p.key]" class="text-[11px] flex items-center gap-1 bg-surface-container-low rounded px-2 py-1 mb-1">
                                        <span class="material-symbols-outlined text-secondary text-[14px]">check_circle</span>
                                        <span class="truncate">{{ row.pieces[p.key].name }}</span>
                                        <button type="button" class="text-error ml-auto" @click="removePiece(row, p.key)"><span class="material-symbols-outlined text-[14px]">close</span></button>
                                    </div>
                                    <div v-else-if="keptExisting(row, p)" class="text-[11px] flex items-center gap-1 bg-secondary/10 rounded px-2 py-1 mb-1">
                                        <span class="material-symbols-outlined text-secondary text-[14px]">task</span>
                                        <a v-if="pieceDownloadUrl(row, keptExisting(row, p))" :href="pieceDownloadUrl(row, keptExisting(row, p))" target="_blank" class="truncate text-primary hover:underline">{{ keptExisting(row, p).filename || 'Fichier téléversé' }}</a>
                                        <span v-else class="truncate">{{ keptExisting(row, p).filename || 'Fichier téléversé' }}</span>
                                        <button type="button" class="text-error ml-auto" @click="removeExisting(row, p)"><span class="material-symbols-outlined text-[14px]">close</span></button>
                                    </div>
                                    <label class="text-[11px] text-primary font-semibold cursor-pointer inline-flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[15px]">upload_file</span> {{ (row.pieces[p.key] || keptExisting(row, p)) ? 'Remplacer le fichier' : 'Choisir un fichier' }}
                                        <input type="file" class="hidden" accept=".pdf,.jpg,.jpeg,.png" @change="handlePiece('enfants', row, p.key, $event)" />
                                    </label>
                                    <p v-if="pieceErrors[`${row.uid}-${p.key}`]" class="text-error text-[11px] mt-1">{{ pieceErrors[`${row.uid}-${p.key}`] }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <p v-if="!state.enfants.length" class="text-xs text-on-surface-variant italic mt-3">Aucun enfant ajouté. Utilisez « Ajouter un enfant » si nécessaire.</p>
            </section>

            <section v-if="fifSigneeRequise" class="bg-white rounded-xl border border-outline-variant p-4 md:p-6 mb-6">
                <div class="flex items-start justify-between gap-3 mb-3">
                    <div>
                        <h2 class="text-base font-semibold text-on-surface flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary text-[20px]">description</span> 4. FIF signée
                        </h2>
                        <p class="text-on-surface-variant text-xs mt-1 max-w-2xl">
                            Générez la FIF pré-remplie, faites-la signer par le chef de corps et le GRH, puis téléversez le scan
                            avant la soumission finale du dossier familial.
                        </p>
                    </div>
                </div>

                <div class="rounded-lg border border-primary/20 bg-primary/5 p-4 mb-4">
                    <p class="text-xs font-bold text-primary mb-2 flex items-center gap-1">
                        <span class="material-symbols-outlined text-[16px]">route</span> Parcours
                    </p>
                    <ol class="text-xs text-on-surface-variant space-y-1.5 list-decimal list-inside">
                        <li>Générer la FIF (PDF) depuis vos saisies</li>
                        <li>Imprimer et faire signer (chef de corps + GRH)</li>
                        <li>Téléverser le scan ci-dessous, puis cliquer « Soumettre »</li>
                    </ol>
                </div>

                <div class="flex flex-col sm:flex-row gap-3 mb-4">
                    <button
                        type="button"
                        class="px-4 py-2.5 rounded-lg border border-primary text-primary text-sm font-semibold hover:bg-primary/5 flex items-center justify-center gap-2 disabled:opacity-50"
                        :disabled="generatingFif"
                        @click="generateFif"
                    >
                        <span class="material-symbols-outlined text-[18px]" :class="{ 'animate-spin': generatingFif }">{{ generatingFif ? 'sync' : 'picture_as_pdf' }}</span>
                        {{ generatingFif ? 'Génération…' : 'Générer ma FIF (PDF)' }}
                    </button>
                    <p v-if="fifGenerated" class="text-xs text-secondary flex items-center gap-1 self-center">
                        <span class="material-symbols-outlined text-[16px]">check_circle</span> PDF téléchargé — procédez aux signatures papier
                    </p>
                </div>

                <div class="border border-outline-variant rounded-lg p-4">
                    <p class="text-xs font-semibold text-on-surface mb-2">{{ fifPieceType }} <span class="text-error">*</span></p>
                    <p class="text-[11px] text-on-surface-variant mb-3">Scan ou photo du formulaire signé (PDF, JPEG ou PNG — 5 Mo max).</p>
                    <div
                        class="border-2 border-dashed rounded-lg p-3"
                        :class="fifSignee ? 'border-secondary/40 bg-secondary/5' : 'border-outline-variant bg-surface-container-low'"
                    >
                        <div class="flex flex-col sm:flex-row sm:items-center gap-2">
                            <div class="flex items-center gap-2 min-w-0 flex-1">
                                <span class="material-symbols-outlined text-[20px]" :class="fifSignee ? 'text-secondary' : 'text-primary'">
                                    {{ fifSignee ? 'task' : 'upload_file' }}
                                </span>
                                <div class="min-w-0">
                                    <p class="text-xs font-semibold text-on-surface truncate">
                                        {{ fifSignee ? fifSignee.name : 'Aucun fichier sélectionné' }}
                                    </p>
                                    <p class="text-[11px] text-on-surface-variant">
                                        {{ fifSignee ? 'FIF prête pour la soumission.' : 'Formats acceptés : PDF, JPEG, PNG — 5 Mo max.' }}
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <button
                                    type="button"
                                    class="px-3 py-2 rounded-lg border border-primary text-primary text-xs font-semibold hover:bg-primary/5 flex items-center gap-1.5"
                                    @click="openFifPicker"
                                >
                                    <span class="material-symbols-outlined text-[16px]">upload_file</span>
                                    {{ fifSignee ? 'Remplacer' : 'Choisir un fichier' }}
                                </button>
                                <button
                                    v-if="fifSignee"
                                    type="button"
                                    class="px-3 py-2 rounded-lg border border-error text-error text-xs font-semibold hover:bg-error/5"
                                    @click="removeFif"
                                >
                                    Retirer
                                </button>
                            </div>
                        </div>
                        <input
                            ref="fifInput"
                            type="file"
                            class="hidden"
                            accept=".pdf,.jpg,.jpeg,.png"
                            @change="handleFifUpload"
                        />
                    </div>
                    <p v-if="fifError" class="text-error text-[11px] mt-2">{{ fifError }}</p>
                    <p v-else-if="!fifSignee" class="text-[11px] text-on-surface-variant mt-2 italic">Obligatoire pour activer le bouton « Soumettre ».</p>
                </div>
            </section>

            <div class="bg-white rounded-xl border border-outline-variant p-4 md:p-6">
                <div class="space-y-2.5 bg-surface-container-low rounded-lg p-4 mb-5">
                    <div class="flex gap-2 p-3 rounded-lg bg-tertiary/10 border border-tertiary/30 text-xs text-on-surface-variant mb-2">
                        <span class="material-symbols-outlined text-tertiary text-[18px] shrink-0">info</span>
                        <p><strong class="text-on-surface">Important :</strong> après soumission, les dossiers ne sont plus modifiables ici. Seuls les statuts « Pièce manquante » pourront être complétés depuis <Link class="text-primary font-semibold hover:underline" :href="route('assure.membres')">Ma famille</Link>.</p>
                    </div>
                    <label class="flex items-start gap-2.5 cursor-pointer">
                        <input v-model="chkHonneur" class="w-4 h-4 mt-0.5 text-primary border-outline-variant rounded focus:ring-primary cursor-pointer" type="checkbox" />
                        <span class="text-xs text-on-surface-variant leading-relaxed">Je déclare sur l'honneur que les informations fournies sont exactes et complètes.</span>
                    </label>
                    <label class="flex items-start gap-2.5 cursor-pointer">
                        <input v-model="chkConsentement" class="w-4 h-4 mt-0.5 text-primary border-outline-variant rounded focus:ring-primary cursor-pointer" type="checkbox" />
                        <span class="text-xs text-on-surface-variant leading-relaxed">Je consens au traitement de mes données et à la conservation des pièces justificatives conformément à la politique de confidentialité de la CAMA.</span>
                    </label>
                </div>
                <div class="flex flex-col sm:flex-row justify-between items-stretch sm:items-center gap-3">
                    <div class="flex flex-col gap-1">
                        <button type="button" class="px-4 py-2.5 rounded-lg border border-outline-variant text-on-surface-variant text-sm font-semibold hover:bg-surface-container-low flex items-center justify-center gap-1.5" @click="saveDraft">
                            <span class="material-symbols-outlined text-[18px]">save</span> Enregistrer le brouillon
                        </button>
                        <p class="text-[10px] text-on-surface-variant italic text-center sm:text-left">
                            {{ fifSigneeRequise ? "Sauvegarde vos saisies sans envoyer — la FIF n'est pas requise à cette étape." : 'Sauvegarde vos saisies sans envoyer à la CAMA.' }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="px-5 py-2.5 rounded-lg bg-secondary text-on-secondary text-sm font-semibold hover:opacity-90 flex items-center justify-center gap-1.5 disabled:opacity-40 disabled:cursor-not-allowed"
                        :disabled="!canSubmit || submitting"
                        @click="openSubmit"
                    >
                        <span v-if="submitting" class="material-symbols-outlined animate-spin text-[18px]">sync</span>
                        <span v-else class="material-symbols-outlined text-[18px]">send</span>
                        {{ submitting ? 'Envoi en cours…' : 'Soumettre le dossier familial' }}
                    </button>
                </div>
            </div>
        </template>

        <div v-else class="bg-white rounded-xl border border-secondary p-6 md:p-8 text-center">
            <span class="material-symbols-outlined text-secondary text-5xl mb-3">check_circle</span>
            <h2 class="text-lg font-bold text-on-surface mb-2">Dossier familial soumis</h2>
            <p class="text-sm text-on-surface-variant max-w-md mx-auto mb-2 leading-relaxed">{{ submittedCount }} dossier(s) transmis à la CAMA pour instruction.</p>
            <p class="text-sm text-on-surface-variant max-w-md mx-auto mb-6 leading-relaxed">
                Le service gestionnaire instruira chaque dossier et vous serez notifié à chaque changement de statut.
                <strong class="text-on-surface">Après soumission, les données ne sont plus modifiables ici</strong> — consultez
                <Link class="text-primary font-semibold hover:underline" :href="route('assure.membres')">Ma famille</Link> pour le suivi.
            </p>
            <div class="flex flex-col sm:flex-row gap-2.5 justify-center">
                <Link class="px-5 py-2.5 rounded-lg bg-primary text-on-primary text-sm font-semibold hover:opacity-90" :href="route('assure.dashboard')">Retour au tableau de bord</Link>
                <Link class="px-5 py-2.5 rounded-lg border border-outline text-on-surface text-sm font-semibold hover:bg-surface-container-low" :href="route('assure.membres')">Voir ma famille</Link>
            </div>
        </div>
    </AssureLayout>

    <div v-if="showWarning" class="fixed inset-0 bg-black/50 z-[60] flex items-center justify-center p-4">
        <div class="bg-white rounded-xl w-full max-w-md p-5 sm:p-6">
            <h3 class="font-title-lg text-title-lg mb-2 flex items-center gap-2 text-tertiary"><span class="material-symbols-outlined">warning</span> Confirmer la soumission</h3>
            <p class="text-xs text-on-surface-variant mb-3 leading-relaxed">Une fois soumis, <strong class="text-on-surface">vous ne pourrez plus modifier ces dossiers</strong> depuis ce formulaire. Vérifiez que toutes les informations et pièces sont correctes.</p>
            <div v-if="fifSignee" class="text-xs bg-secondary/10 border border-secondary/30 rounded-lg px-3 py-2 mb-3 flex items-center gap-2">
                <span class="material-symbols-outlined text-secondary text-[18px]">task</span>
                <span>FIF signée jointe : <strong>{{ fifSignee.name }}</strong></span>
            </div>
            <p class="text-xs text-on-surface-variant mb-4">Le suivi et les éventuels compléments se feront depuis <strong>Ma famille</strong>.</p>
            <div class="flex justify-end gap-2">
                <button type="button" class="px-4 py-2 text-xs rounded-lg border border-outline" @click="showWarning = false">Revoir le formulaire</button>
                <button type="button" class="px-4 py-2 text-xs rounded-lg bg-secondary text-on-secondary font-bold" @click="confirmSubmit">Confirmer et soumettre</button>
            </div>
        </div>
    </div>

    <div v-if="toast.show" class="fixed bottom-6 right-6 z-50">
        <div class="bg-on-background text-white px-4 py-2.5 rounded-lg shadow-xl flex items-center gap-2 text-sm font-medium" v-html="toast.html" />
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
