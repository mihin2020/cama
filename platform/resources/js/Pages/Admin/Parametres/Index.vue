<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import OrgStructureEditor from '@/Components/Admin/OrgStructureEditor.vue';
import CamaConfirmModal from '@/Components/CamaConfirmModal.vue';
import CamaLoadingButton from '@/Components/CamaLoadingButton.vue';
import { useCamaConfirm } from '@/Composables/useCamaConfirm';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    settings: Object,
});

const page = usePage();

const activeTab = ref('membres');

const { confirmState, askConfirm, confirm, cancel } = useCamaConfirm();

const membresForm = useForm({
    age_max_enfant: props.settings.ageMaxEnfant,
    certificat_scolarite_actif: props.settings.certificatScolarite?.actif ?? true,
    certificat_scolarite_label: props.settings.certificatScolarite?.label ?? 'Certificat de scolarité',
});

const photosForm = useForm({
    conjoint_actif: props.settings.membrePhoto?.conjoint?.actif ?? true,
    conjoint_required: props.settings.membrePhoto?.conjoint?.required ?? false,
    enfant_actif: props.settings.membrePhoto?.enfant?.actif ?? true,
    enfant_required: props.settings.membrePhoto?.enfant?.required ?? false,
});

const filiationsForm = useForm({
    filiations: (props.settings.enfantFiliations ?? []).map((f) => ({
        key: f.key,
        label: f.label,
        actif: f.actif,
        pieces: (f.pieces ?? []).map((p) => ({
            key: p.key,
            label: p.label,
            required: !!p.required,
        })),
    })),
});

const fifForm = useForm({
    fif_signee_requise: props.settings.fifSigneeRequise ?? false,
});

const affectationForm = useForm({
    affectation_mode: props.settings.affectationMode,
    validation_2niveaux: props.settings.validation2Niveaux,
});

const libellesForm = useForm({
    motifs_refus: [...props.settings.motifsRefus],
    email_validation: props.settings.emailTemplates.validation,
    email_complement: props.settings.emailTemplates.complement,
    email_refus: props.settings.emailTemplates.refus,
    email_message: props.settings.emailTemplates.message,
    notify_validation_in_app: props.settings.assureNotifyChannels?.validation_in_app ?? true,
    notify_validation_email: props.settings.assureNotifyChannels?.validation_email ?? false,
    notify_refus_in_app: props.settings.assureNotifyChannels?.refus_in_app ?? true,
    notify_refus_email: props.settings.assureNotifyChannels?.refus_email ?? false,
    notify_complement_in_app: props.settings.assureNotifyChannels?.complement_in_app ?? true,
    notify_complement_email: props.settings.assureNotifyChannels?.complement_email ?? false,
    notify_message_in_app: props.settings.assureNotifyChannels?.message_in_app ?? true,
    notify_message_email: props.settings.assureNotifyChannels?.message_email ?? false,
});

const structureForm = useForm({
    armees: [...props.settings.orgStructure.armees],
    categories: [...props.settings.orgStructure.categories],
    grades: [...(props.settings.orgStructure.grades ?? [])],
    groupesSanguins: [...props.settings.orgStructure.groupesSanguins],
    regions: JSON.parse(JSON.stringify(props.settings.orgStructure.regions ?? [])),
});

const documentsForm = useForm({
    documents: (props.settings.inscriptionDocuments ?? []).map((d) => ({
        key: d.key,
        actif: d.actif,
        titre: d.titre,
    })),
});

const TABS = [
    { key: 'membres', label: 'Membres rattachés' },
    { key: 'fif', label: 'FIF signée' },
    { key: 'libelles', label: 'Libellés & modèles' },
    { key: 'affectation', label: "Règles d'affectation" },
    { key: 'structure', label: 'Structure militaire' },
    { key: 'documents', label: "Pièces d'inscription" },
];

function saveDocuments() {
    documentsForm.post(route('admin.parametres.inscription-documents'), { preserveScroll: true });
}

function saveMembres() {
    membresForm.post(route('admin.parametres.membres'), { preserveScroll: true });
}

function savePhotos() {
    photosForm.post(route('admin.parametres.photos'), { preserveScroll: true });
}

function saveFiliations() {
    filiationsForm.post(route('admin.parametres.filiations'), { preserveScroll: true });
}

function saveFif() {
    fifForm.post(route('admin.parametres.fif'), { preserveScroll: true });
}

function toggleScolarite() {
    membresForm.certificat_scolarite_actif = !membresForm.certificat_scolarite_actif;
}

function togglePhoto(field) {
    photosForm[field] = !photosForm[field];
}

function toggleFif() {
    fifForm.fif_signee_requise = !fifForm.fif_signee_requise;
}

function toggleFiliation(index) {
    filiationsForm.filiations[index].actif = !filiationsForm.filiations[index].actif;
}

function addFiliationPiece(index) {
    filiationsForm.filiations[index].pieces.push({
        key: `piece_${Date.now()}`,
        label: '',
        required: false,
    });
}

function removeFiliationPiece(filiationIndex, pieceIndex) {
    filiationsForm.filiations[filiationIndex].pieces.splice(pieceIndex, 1);
}

function saveAffectation() {
    affectationForm.post(route('admin.parametres.affectation'), { preserveScroll: true });
}

function saveLibelles() {
    libellesForm.post(route('admin.parametres.libelles'), { preserveScroll: true });
}

function syncStructureFormFromProps() {
    const org = page.props.settings?.orgStructure;
    if (!org) return;
    structureForm.armees = [...org.armees];
    structureForm.categories = [...org.categories];
    structureForm.grades = [...(org.grades ?? [])];
    structureForm.groupesSanguins = [...org.groupesSanguins];
    structureForm.regions = JSON.parse(JSON.stringify(org.regions ?? []));
}

function saveStructure() {
    structureForm.armees = structureForm.armees.map((v) => v.trim()).filter(Boolean);
    structureForm.categories = structureForm.categories.map((v) => v.trim()).filter(Boolean);
    structureForm.grades = structureForm.grades.map((v) => v.trim()).filter(Boolean);
    structureForm.groupesSanguins = structureForm.groupesSanguins.map((v) => v.trim()).filter(Boolean);
    structureForm.post(route('admin.parametres.structure'), {
        preserveScroll: true,
        onSuccess: () => syncStructureFormFromProps(),
    });
}

function resetStructure() {
    askConfirm({
        title: 'Réinitialiser la structure militaire ?',
        message: 'Les listes et la hiérarchie de rattachement seront remises aux valeurs par défaut (Burkina Faso).',
        confirmLabel: 'Réinitialiser',
        variant: 'warning',
        onConfirm: () => router.post(route('admin.parametres.structure.reset'), {}, { preserveScroll: true }),
    });
}

function addMotif() {
    libellesForm.motifs_refus.push('');
}

function removeMotif(index) {
    const label = libellesForm.motifs_refus[index]?.trim() || 'ce motif';
    askConfirm({
        title: 'Retirer ce motif ?',
        message: `« ${label} » sera supprimé de la liste des motifs de refus.`,
        confirmLabel: 'Retirer',
        variant: 'danger',
        onConfirm: () => libellesForm.motifs_refus.splice(index, 1),
    });
}

const notifyRows = [
    { key: 'validation', label: 'Validation d\'un dossier membre' },
    { key: 'refus', label: 'Refus d\'un dossier' },
    { key: 'complement', label: 'Demande de pièce complémentaire' },
    { key: 'message', label: 'Message du fil de discussion (gestionnaire → assuré)' },
];

function addSimpleList(key) {
    structureForm[key].push('');
}

const simpleListLabels = {
    armees: 'cette armée',
    categories: 'cette catégorie',
    grades: 'ce grade',
    groupesSanguins: 'ce groupe sanguin',
};

function removeSimpleList(key, index) {
    const value = structureForm[key][index]?.trim() || simpleListLabels[key];
    askConfirm({
        title: 'Retirer cet élément ?',
        message: `« ${value} » sera retiré de la liste. Cliquez ensuite sur « Enregistrer la structure » pour appliquer la suppression.`,
        confirmLabel: 'Retirer',
        variant: 'danger',
        onConfirm: () => structureForm[key].splice(index, 1),
    });
}

function toggleValidation2N() {
    affectationForm.validation_2niveaux = !affectationForm.validation_2niveaux;
}
</script>

<template>
    <Head title="Paramètres" />

    <AdminLayout active-nav="parametres" title="Paramètres" subtitle="Réservé Administrateur technique">
        <div class="max-w-[1100px] w-full mx-auto">
            <div class="flex flex-wrap gap-2 mb-5">
                <button
                    v-for="tab in TABS"
                    :key="tab.key"
                    type="button"
                    class="px-4 py-2 rounded-full text-xs font-bold"
                    :class="activeTab === tab.key ? 'bg-primary text-on-primary' : 'bg-surface-container-high text-on-surface-variant'"
                    @click="activeTab = tab.key"
                >
                    {{ tab.label }}
                </button>
            </div>

            <!-- Membres rattachés -->
            <div v-show="activeTab === 'membres'" class="space-y-4 max-w-2xl">
                <div class="assure-card p-6">
                    <h3 class="font-title-lg text-title-lg mb-1">Éligibilité des membres rattachés</h3>
                    <p class="text-xs text-on-surface-variant mb-5">
                        À partir de l'âge seuil configuré (défaut <strong>26 ans</strong>), l'assuré devra téléverser un
                        <strong>certificat de scolarité</strong> si l'exigence est activée. Plus de blocage d'enrôlement purement lié à l'âge.
                    </p>
                    <form @submit.prevent="saveMembres">
                        <div class="border border-outline-variant rounded-lg p-4 mb-4">
                            <label class="text-[11px] font-bold uppercase text-on-surface-variant block mb-2" for="age-max-enfant">Âge seuil (certificat de scolarité)</label>
                            <div class="flex items-center gap-3">
                                <input
                                    id="age-max-enfant"
                                    v-model.number="membresForm.age_max_enfant"
                                    class="w-24 px-3 py-2 text-sm border border-outline-variant rounded-lg text-center"
                                    type="number"
                                    min="1"
                                    max="99"
                                />
                                <span class="text-sm text-on-surface-variant">ans</span>
                            </div>
                            <p v-if="membresForm.errors.age_max_enfant" class="text-error text-xs mt-1">{{ membresForm.errors.age_max_enfant }}</p>
                        </div>
                        <div class="border border-outline-variant rounded-lg p-4 mb-4">
                            <label class="flex items-start gap-3 cursor-pointer mb-3">
                                <button type="button" class="toggle-switch shrink-0" :class="membresForm.certificat_scolarite_actif ? 'on' : 'off'" @click="toggleScolarite">
                                    <span class="knob" />
                                </button>
                                <span>
                                    <span class="block text-sm font-semibold">Exiger un certificat de scolarité</span>
                                    <span class="block text-[11px] text-on-surface-variant">Si désactivé, aucun certificat n'est demandé quel que soit l'âge.</span>
                                </span>
                            </label>
                            <label class="text-[11px] font-bold uppercase text-on-surface-variant block mb-2" for="scolarite-label">Libellé de la pièce</label>
                            <input
                                id="scolarite-label"
                                v-model="membresForm.certificat_scolarite_label"
                                class="w-full px-3 py-2 text-sm border border-outline-variant rounded-lg"
                                type="text"
                                placeholder="Certificat de scolarité"
                            />
                        </div>
                        <CamaLoadingButton type="submit" :loading="membresForm.processing">Enregistrer</CamaLoadingButton>
                    </form>
                </div>

                <div class="assure-card p-6">
                    <h3 class="font-title-lg text-title-lg mb-1">Photo des membres</h3>
                    <p class="text-xs text-on-surface-variant mb-4">Activez la demande de photo d'identité pour les conjoints et/ou les enfants, et choisissez si elle est obligatoire à la soumission.</p>
                    <form class="space-y-3" @submit.prevent="savePhotos">
                        <div class="border border-outline-variant rounded-lg p-4 flex flex-col sm:flex-row sm:items-center gap-3">
                            <button type="button" class="toggle-switch shrink-0" :class="photosForm.conjoint_actif ? 'on' : 'off'" @click="togglePhoto('conjoint_actif')">
                                <span class="knob" />
                            </button>
                            <div class="flex-1">
                                <p class="text-sm font-semibold text-on-surface">Photo du conjoint</p>
                            </div>
                            <label class="flex items-center gap-2 text-xs cursor-pointer">
                                <input v-model="photosForm.conjoint_required" type="checkbox" :disabled="!photosForm.conjoint_actif" /> Obligatoire
                            </label>
                        </div>
                        <div class="border border-outline-variant rounded-lg p-4 flex flex-col sm:flex-row sm:items-center gap-3">
                            <button type="button" class="toggle-switch shrink-0" :class="photosForm.enfant_actif ? 'on' : 'off'" @click="togglePhoto('enfant_actif')">
                                <span class="knob" />
                            </button>
                            <div class="flex-1">
                                <p class="text-sm font-semibold text-on-surface">Photo de l'enfant</p>
                            </div>
                            <label class="flex items-center gap-2 text-xs cursor-pointer">
                                <input v-model="photosForm.enfant_required" type="checkbox" :disabled="!photosForm.enfant_actif" /> Obligatoire
                            </label>
                        </div>
                        <CamaLoadingButton type="submit" :loading="photosForm.processing">Enregistrer les photos</CamaLoadingButton>
                    </form>
                </div>

                <div class="assure-card p-6">
                    <h3 class="font-title-lg text-title-lg mb-1">Filiations enfants &amp; pièces justificatives</h3>
                    <p class="text-xs text-on-surface-variant mb-4">Activez les types de filiation proposés à l'assuré et définissez les pièces obligatoires ou optionnelles.</p>
                    <form class="space-y-4" @submit.prevent="saveFiliations">
                        <div
                            v-for="(filiation, fi) in filiationsForm.filiations"
                            :key="filiation.key"
                            class="border border-outline-variant rounded-lg p-4"
                        >
                            <div class="flex items-start gap-3 mb-3">
                                <button type="button" class="toggle-switch shrink-0" :class="filiation.actif ? 'on' : 'off'" @click="toggleFiliation(fi)">
                                    <span class="knob" />
                                </button>
                                <div class="flex-1">
                                    <label class="text-[11px] font-bold uppercase text-on-surface-variant block mb-1">Libellé</label>
                                    <input v-model="filiation.label" class="w-full px-3 py-2 text-sm border border-outline-variant rounded-lg" type="text" />
                                </div>
                            </div>
                            <div v-if="filiation.actif" class="space-y-2 pl-1">
                                <div
                                    v-for="(piece, pi) in filiation.pieces"
                                    :key="`${filiation.key}-${piece.key}-${pi}`"
                                    class="flex flex-wrap items-center gap-2"
                                >
                                    <input v-model="piece.label" class="flex-1 min-w-[140px] px-3 py-1.5 text-xs border border-outline-variant rounded-lg" type="text" placeholder="Libellé pièce" />
                                    <label class="flex items-center gap-1 text-[11px] cursor-pointer whitespace-nowrap">
                                        <input v-model="piece.required" type="checkbox" /> Obligatoire
                                    </label>
                                    <button type="button" class="text-error" @click="removeFiliationPiece(fi, pi)">
                                        <span class="material-symbols-outlined text-[18px]">delete</span>
                                    </button>
                                </div>
                                <button type="button" class="text-primary text-xs font-bold flex items-center gap-1" @click="addFiliationPiece(fi)">
                                    <span class="material-symbols-outlined text-[16px]">add</span> Ajouter une pièce
                                </button>
                            </div>
                        </div>
                        <CamaLoadingButton type="submit" :loading="filiationsForm.processing">Enregistrer les filiations</CamaLoadingButton>
                    </form>
                </div>
            </div>

            <!-- FIF signée -->
            <div v-show="activeTab === 'fif'" class="assure-card p-6 max-w-xl">
                <h3 class="font-title-lg text-title-lg mb-1">FIF signée</h3>
                <p class="text-xs text-on-surface-variant mb-4">
                    Activez l'exigence de la <strong>FIF signée</strong> (génération PDF + téléversement du scan) avant la soumission du dossier familial. Désactivée par défaut.
                </p>
                <form @submit.prevent="saveFif">
                    <div class="border border-outline-variant rounded-lg p-4 flex flex-col sm:flex-row sm:items-center gap-3 mb-4">
                        <button type="button" class="toggle-switch shrink-0" :class="fifForm.fif_signee_requise ? 'on' : 'off'" @click="toggleFif">
                            <span class="knob" />
                        </button>
                        <div class="flex-1">
                            <p class="text-sm font-semibold text-on-surface">Exiger la FIF signée à la soumission</p>
                            <p class="text-[11px] text-on-surface-variant">Si activé, l'assuré doit générer la FIF, la faire signer et téléverser le scan. Si désactivé, la section n'apparaît pas.</p>
                        </div>
                        <span
                            class="text-[10px] font-bold px-2 py-1 rounded-full whitespace-nowrap shrink-0"
                            :class="fifForm.fif_signee_requise ? 'bg-primary/15 text-primary' : 'bg-surface-container-high text-on-surface-variant'"
                        >
                            {{ fifForm.fif_signee_requise ? 'Activée' : 'Désactivée' }}
                        </span>
                    </div>
                    <CamaLoadingButton type="submit" :loading="fifForm.processing">Enregistrer</CamaLoadingButton>
                </form>
            </div>

            <!-- Libellés -->
            <div v-show="activeTab === 'libelles'">
                <form @submit.prevent="saveLibelles">
                    <div class="assure-card p-6 mb-4">
                        <h3 class="font-title-lg text-title-lg mb-1">Motifs de refus pré-définis</h3>
                        <p class="text-xs text-on-surface-variant mb-3">Proposés au gestionnaire lors du refus d'un dossier (sélection rapide + personnalisation).</p>
                        <div class="space-y-2 mb-3">
                            <div v-for="(motif, i) in libellesForm.motifs_refus" :key="i" class="flex items-center gap-2">
                                <input v-model="libellesForm.motifs_refus[i]" class="flex-1 px-3 py-2 text-xs border border-outline-variant rounded-lg" type="text" />
                                <button type="button" class="text-error" @click="removeMotif(i)">
                                    <span class="material-symbols-outlined text-[18px]">delete</span>
                                </button>
                            </div>
                        </div>
                        <button type="button" class="text-primary text-xs font-bold flex items-center gap-1" @click="addMotif">
                            <span class="material-symbols-outlined text-[16px]">add</span> Ajouter un motif
                        </button>
                    </div>
                    <div class="assure-card p-6">
                        <h3 class="font-title-lg text-title-lg mb-1">Notifications assuré</h3>
                        <p class="text-xs text-on-surface-variant mb-4">
                            Choisissez pour chaque événement si l'assuré reçoit une alerte dans son espace et/ou un e-mail. Les messages du fil de discussion sont visibles dans l'espace assuré ; l'e-mail est optionnel.
                        </p>
                        <div class="space-y-4 mb-5">
                            <div v-for="row in notifyRows" :key="row.key" class="border border-outline-variant rounded-lg p-4 bg-surface-container-low">
                                <p class="text-xs font-bold mb-2">{{ row.label }}</p>
                                <div class="flex flex-wrap gap-4 text-xs">
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input v-model="libellesForm[`notify_${row.key}_in_app`]" type="checkbox" />
                                        Notification dans l'espace assuré
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input v-model="libellesForm[`notify_${row.key}_email`]" type="checkbox" />
                                        Envoyer un e-mail
                                    </label>
                                </div>
                            </div>
                        </div>
                        <h3 class="font-title-lg text-title-lg mb-1">Modèles de message</h3>
                        <p class="text-xs text-on-surface-variant mb-3">
                            Texte utilisé pour les notifications et e-mails assuré. Variables : <code class="text-[10px] bg-surface-container-high px-1 rounded">{ref}</code>, <code class="text-[10px] bg-surface-container-high px-1 rounded">{beneficiaire}</code>, <code class="text-[10px] bg-surface-container-high px-1 rounded">{piece}</code>, <code class="text-[10px] bg-surface-container-high px-1 rounded">{motif}</code>, <code class="text-[10px] bg-surface-container-high px-1 rounded">{message}</code>.
                        </p>
                        <div class="space-y-3">
                            <div>
                                <label class="text-[11px] font-bold uppercase text-on-surface-variant block mb-1">Validation de dossier</label>
                                <textarea v-model="libellesForm.email_validation" class="w-full px-3 py-2 text-xs border border-outline-variant rounded-lg" rows="2" />
                            </div>
                            <div>
                                <label class="text-[11px] font-bold uppercase text-on-surface-variant block mb-1">Refus de dossier</label>
                                <textarea v-model="libellesForm.email_refus" class="w-full px-3 py-2 text-xs border border-outline-variant rounded-lg" rows="2" />
                            </div>
                            <div>
                                <label class="text-[11px] font-bold uppercase text-on-surface-variant block mb-1">Demande de complément</label>
                                <textarea v-model="libellesForm.email_complement" class="w-full px-3 py-2 text-xs border border-outline-variant rounded-lg" rows="2" />
                            </div>
                            <div>
                                <label class="text-[11px] font-bold uppercase text-on-surface-variant block mb-1">Message du fil de discussion</label>
                                <textarea v-model="libellesForm.email_message" class="w-full px-3 py-2 text-xs border border-outline-variant rounded-lg" rows="2" />
                            </div>
                        </div>
                        <CamaLoadingButton type="submit" button-class="mt-3" :loading="libellesForm.processing">Enregistrer</CamaLoadingButton>
                    </div>
                </form>
            </div>

            <!-- Affectation -->
            <div v-show="activeTab === 'affectation'" class="assure-card p-6 max-w-lg">
                <h3 class="font-title-lg text-title-lg mb-1">Règles d'affectation automatique</h3>
                <p class="text-xs text-on-surface-variant mb-4">Mode d'affectation des nouveaux dossiers aux gestionnaires. Ces règles s'appliquent à chaque nouveau dossier soumis par un assuré ; les dossiers déjà affectés ne sont pas redistribués.</p>
                <form @submit.prevent="saveAffectation">
                    <div class="space-y-3 mb-4">
                        <label
                            class="flex items-start gap-2 text-xs cursor-pointer"
                            title="Aucune distribution automatique : chaque dossier soumis reste « Non affecté » jusqu'à ce que le superviseur l'attribue depuis la page Dossiers."
                        >
                            <input v-model="affectationForm.affectation_mode" type="radio" value="manuelle" class="mt-0.5" />
                            <span>
                                <span class="font-bold block">Manuelle (le superviseur affecte chaque dossier)</span>
                                <span class="text-[11px] text-on-surface-variant">Les nouveaux dossiers restent « Non affecté ». Le superviseur les attribue un à un (ou par lot) depuis la page Dossiers.</span>
                            </span>
                        </label>
                        <label
                            class="flex items-start gap-2 text-xs cursor-pointer"
                            title="Chaque nouveau dossier est attribué au gestionnaire suivant dans la liste, à tour de rôle, indépendamment de leur charge de travail."
                        >
                            <input v-model="affectationForm.affectation_mode" type="radio" value="round_robin" class="mt-0.5" />
                            <span>
                                <span class="font-bold block">Automatique — répartition équilibrée (round-robin)</span>
                                <span class="text-[11px] text-on-surface-variant">Dès sa soumission, chaque dossier est attribué au gestionnaire actif suivant, à tour de rôle. Tous reçoivent le même nombre de dossiers au fil du temps.</span>
                            </span>
                        </label>
                        <label
                            class="flex items-start gap-2 text-xs cursor-pointer"
                            title="Chaque nouveau dossier est attribué au gestionnaire qui a le moins de dossiers ouverts (soumis, en instruction, pièce manquante, en attente supervision) au moment de la soumission."
                        >
                            <input v-model="affectationForm.affectation_mode" type="radio" value="charge_min" class="mt-0.5" />
                            <span>
                                <span class="font-bold block">Automatique — par charge la plus faible</span>
                                <span class="text-[11px] text-on-surface-variant">Dès sa soumission, chaque dossier va au gestionnaire ayant le moins de dossiers en cours de traitement. Équilibre la charge réelle, pas seulement le nombre.</span>
                            </span>
                        </label>
                        <p class="text-[11px] text-on-surface-variant bg-surface-container-low border border-outline-variant rounded-lg px-3 py-2">
                            <span class="font-bold">Note :</span> en mode automatique, les dossiers d'un même assuré (même famille) sont toujours confiés au même gestionnaire. Le superviseur peut toujours réaffecter manuellement un dossier après coup.
                        </p>
                    </div>
                    <div class="flex items-center justify-between gap-3 pt-4 border-t border-outline-variant">
                        <div>
                            <p class="text-xs font-bold">Validation à deux niveaux</p>
                            <p class="text-[11px] text-on-surface-variant">Gestionnaire puis Superviseur avant décision finale</p>
                            <p class="text-[11px] text-on-surface-variant mt-1">
                                <span class="font-bold">Activé :</span> quand un gestionnaire valide un dossier, celui-ci passe « En attente supervision » — seul un superviseur ou administrateur prononce la validation définitive (et la notification à l'assuré).
                                <span class="font-bold">Désactivé :</span> le gestionnaire valide seul et l'assuré est notifié immédiatement. Les refus et demandes de complément restent au niveau du gestionnaire dans les deux cas.
                            </p>
                        </div>
                        <button
                            type="button"
                            class="toggle-switch shrink-0"
                            :class="affectationForm.validation_2niveaux ? 'on' : 'off'"
                            aria-label="Validation à deux niveaux"
                            title="Activé : la validation d'un gestionnaire transmet le dossier à la supervision au lieu de le valider définitivement."
                            @click="toggleValidation2N"
                        >
                            <div class="knob" />
                        </button>
                    </div>
                    <CamaLoadingButton type="submit" button-class="mt-4" :loading="affectationForm.processing">Enregistrer</CamaLoadingButton>
                </form>
            </div>

            <!-- Structure militaire -->
            <div v-show="activeTab === 'structure'">
                <div class="assure-card p-4 mb-4 border border-primary/25 bg-primary/5 flex items-start gap-3 text-xs text-on-surface-variant">
                    <span class="material-symbols-outlined text-primary text-[20px] shrink-0">info</span>
                    <p>
                        Les catégories, grades, armées et groupes sanguins affichés sur la
                        <strong class="text-on-surface">page d'inscription assuré</strong>
                        ne sont mis à jour qu'après clic sur
                        <strong class="text-on-surface">Enregistrer la structure</strong>.
                    </p>
                </div>

                <div class="assure-card p-6 mb-4">
                    <h3 class="font-title-lg text-title-lg mb-1">Listes simples</h3>
                    <p class="text-xs text-on-surface-variant mb-4">Valeurs proposées en liste déroulante à l'inscription (indépendantes de la hiérarchie ci-dessous).</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
                        <div class="border border-outline-variant rounded-lg p-4">
                            <h4 class="text-[11px] font-bold uppercase text-on-surface-variant mb-1 flex items-center gap-1">
                                <span class="material-symbols-outlined text-[16px] text-primary">military_tech</span> Grades
                            </h4>
                            <p class="text-[10px] text-on-surface-variant mb-2">Ex. Lieutenant, Capitaine…</p>
                            <div class="space-y-2 mb-2">
                                <div v-for="(item, i) in structureForm.grades" :key="'gr-' + i" class="flex gap-1">
                                    <input v-model="structureForm.grades[i]" class="flex-1 px-2 py-1.5 text-xs border border-outline-variant rounded-lg" type="text" />
                                    <button type="button" class="text-error" @click="removeSimpleList('grades', i)"><span class="material-symbols-outlined text-[16px]">delete</span></button>
                                </div>
                            </div>
                            <button type="button" class="text-primary text-xs font-bold flex items-center gap-1" @click="addSimpleList('grades')">
                                <span class="material-symbols-outlined text-[16px]">add</span> Ajouter un grade
                            </button>
                        </div>
                        <div class="border border-outline-variant rounded-lg p-4">
                            <h4 class="text-[11px] font-bold uppercase text-on-surface-variant mb-1 flex items-center gap-1">
                                <span class="material-symbols-outlined text-[16px] text-primary">military_tech</span> Armées
                            </h4>
                            <p class="text-[10px] text-on-surface-variant mb-2">Ex. Armée de Terre, Gendarmerie…</p>
                            <div class="space-y-2 mb-2">
                                <div v-for="(item, i) in structureForm.armees" :key="'a-' + i" class="flex gap-1">
                                    <input v-model="structureForm.armees[i]" class="flex-1 px-2 py-1.5 text-xs border border-outline-variant rounded-lg" type="text" />
                                    <button type="button" class="text-error" @click="removeSimpleList('armees', i)"><span class="material-symbols-outlined text-[16px]">delete</span></button>
                                </div>
                            </div>
                            <button type="button" class="text-primary text-xs font-bold flex items-center gap-1" @click="addSimpleList('armees')">
                                <span class="material-symbols-outlined text-[16px]">add</span> Ajouter une armée
                            </button>
                        </div>
                        <div class="border border-outline-variant rounded-lg p-4">
                            <h4 class="text-[11px] font-bold uppercase text-on-surface-variant mb-1 flex items-center gap-1">
                                <span class="material-symbols-outlined text-[16px] text-primary">badge</span> Catégories
                            </h4>
                            <p class="text-[10px] text-on-surface-variant mb-2">Ex. Officier, Sous-officier…</p>
                            <div class="space-y-2 mb-2">
                                <div v-for="(item, i) in structureForm.categories" :key="'c-' + i" class="flex gap-1">
                                    <input v-model="structureForm.categories[i]" class="flex-1 px-2 py-1.5 text-xs border border-outline-variant rounded-lg" type="text" />
                                    <button type="button" class="text-error" @click="removeSimpleList('categories', i)"><span class="material-symbols-outlined text-[16px]">delete</span></button>
                                </div>
                            </div>
                            <button type="button" class="text-primary text-xs font-bold flex items-center gap-1" @click="addSimpleList('categories')">
                                <span class="material-symbols-outlined text-[16px]">add</span> Ajouter une catégorie
                            </button>
                        </div>
                        <div class="border border-outline-variant rounded-lg p-4">
                            <h4 class="text-[11px] font-bold uppercase text-on-surface-variant mb-1 flex items-center gap-1">
                                <span class="material-symbols-outlined text-[16px] text-primary">bloodtype</span> Groupes sanguins
                            </h4>
                            <p class="text-[10px] text-on-surface-variant mb-2">Ex. A+, O-, AB+…</p>
                            <div class="space-y-2 mb-2">
                                <div v-for="(item, i) in structureForm.groupesSanguins" :key="'g-' + i" class="flex gap-1">
                                    <input v-model="structureForm.groupesSanguins[i]" class="flex-1 px-2 py-1.5 text-xs border border-outline-variant rounded-lg" type="text" />
                                    <button type="button" class="text-error" @click="removeSimpleList('groupesSanguins', i)"><span class="material-symbols-outlined text-[16px]">delete</span></button>
                                </div>
                            </div>
                            <button type="button" class="text-primary text-xs font-bold flex items-center gap-1" @click="addSimpleList('groupesSanguins')">
                                <span class="material-symbols-outlined text-[16px]">add</span> Ajouter un groupe
                            </button>
                        </div>
                    </div>
                    <div class="mt-4 pt-4 border-t border-outline-variant flex items-center gap-3 flex-wrap">
                        <CamaLoadingButton type="button" :loading="structureForm.processing" @click="saveStructure">Enregistrer la structure</CamaLoadingButton>
                        <span v-if="structureForm.isDirty" class="inline-flex items-center gap-1 text-[11px] font-bold text-tertiary bg-tertiary/10 border border-tertiary/30 rounded-full px-2.5 py-1">
                            <span class="material-symbols-outlined text-[14px]">warning</span> Modifications non enregistrées
                        </span>
                        <span v-else class="text-[11px] text-on-surface-variant">Applique les listes à l'inscription</span>
                    </div>
                </div>

                <div class="assure-card p-6">
                    <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
                        <div>
                            <h3 class="font-title-lg text-title-lg mb-1">Hiérarchie de rattachement</h3>
                            <p class="text-xs text-on-surface-variant max-w-2xl">
                                Arborescence dans laquelle l'assuré choisit son unité à l'inscription. Chaque niveau débloque le suivant (région → corps → service → section → sous-section).
                            </p>
                        </div>
                    </div>

                    <div class="rounded-xl border border-primary/20 bg-primary/5 p-4 mb-5">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-primary mb-3 flex items-center gap-1">
                            <span class="material-symbols-outlined text-[16px]">route</span> Parcours de l'assuré à l'inscription
                        </p>
                        <div class="flex flex-wrap items-center justify-center gap-1 sm:gap-0">
                            <div class="org-guide-step"><span class="step-num">1</span><span class="material-symbols-outlined text-primary text-[20px]">map</span><span class="text-[10px] font-bold text-on-surface">Région</span></div>
                            <span class="org-guide-arrow hidden sm:inline">›</span>
                            <div class="org-guide-step"><span class="step-num">2</span><span class="material-symbols-outlined text-secondary text-[20px]">shield</span><span class="text-[10px] font-bold text-on-surface">Corps</span></div>
                            <span class="org-guide-arrow hidden sm:inline">›</span>
                            <div class="org-guide-step"><span class="step-num">3</span><span class="material-symbols-outlined text-tertiary text-[20px]">apartment</span><span class="text-[10px] font-bold text-on-surface">Service</span></div>
                            <span class="org-guide-arrow hidden sm:inline">›</span>
                            <div class="org-guide-step"><span class="step-num">4</span><span class="material-symbols-outlined text-on-surface-variant text-[20px]">groups</span><span class="text-[10px] font-bold text-on-surface">Section</span></div>
                            <span class="org-guide-arrow hidden sm:inline">›</span>
                            <div class="org-guide-step"><span class="step-num">5</span><span class="material-symbols-outlined text-on-surface-variant text-[20px]">folder_open</span><span class="text-[10px] font-bold text-on-surface">Sous-section</span></div>
                        </div>
                        <p class="text-[11px] text-on-surface-variant mt-3 text-center sm:text-left">
                            Exemple : <strong class="text-on-surface">2e Région Militaire — Hauts-Bassins</strong> → <strong class="text-on-surface">14e BIC</strong> → <strong class="text-on-surface">Service Administratif</strong> → <strong class="text-on-surface">Section Personnel</strong>
                        </p>
                    </div>

                    <OrgStructureEditor v-model="structureForm.regions" />
                </div>

                <div class="mt-4 flex items-center gap-3 flex-wrap">
                    <CamaLoadingButton type="button" :loading="structureForm.processing" @click="saveStructure">Enregistrer la structure</CamaLoadingButton>
                    <button type="button" class="px-4 py-2 rounded-lg bg-surface-container-high text-on-surface-variant text-xs font-bold" @click="resetStructure">Réinitialiser</button>
                    <span v-if="structureForm.isDirty" class="inline-flex items-center gap-1 text-[11px] font-bold text-tertiary bg-tertiary/10 border border-tertiary/30 rounded-full px-2.5 py-1">
                        <span class="material-symbols-outlined text-[14px]">warning</span> Modifications non enregistrées
                    </span>
                </div>
            </div>

            <!-- Pièces d'inscription -->
            <div v-show="activeTab === 'documents'">
                <div class="assure-card p-4 mb-4 border border-primary/25 bg-primary/5 flex items-start gap-3 text-xs text-on-surface-variant">
                    <span class="material-symbols-outlined text-primary text-[20px] shrink-0">info</span>
                    <p>
                        Activez jusqu'à 3 pièces justificatives à téléverser lors de l'inscription (ex. carte militaire, carte CAMA, CNIB).
                        Seules les pièces <strong class="text-on-surface">activées avec un titre</strong> apparaissent sur le formulaire d'inscription.
                        Si aucune n'est activée, la section n'est pas affichée.
                    </p>
                </div>

                <div class="assure-card p-6 max-w-2xl">
                    <h3 class="font-title-lg text-title-lg mb-1">Pièces demandées à l'inscription</h3>
                    <p class="text-xs text-on-surface-variant mb-4">Les gestionnaires, superviseurs et administrateurs pourront consulter ces documents depuis la fiche d'inscription.</p>

                    <div class="space-y-3">
                        <div
                            v-for="(doc, i) in documentsForm.documents"
                            :key="doc.key"
                            class="border rounded-lg p-4 flex flex-col sm:flex-row sm:items-center gap-3"
                            :class="doc.actif ? 'border-primary/40 bg-primary/5' : 'border-outline-variant'"
                        >
                            <button
                                type="button"
                                class="toggle-switch shrink-0"
                                :class="doc.actif ? 'on' : 'off'"
                                @click="doc.actif = !doc.actif"
                            >
                                <span class="knob" />
                            </button>
                            <div class="flex-1">
                                <label class="text-[10px] uppercase text-on-surface-variant font-bold block mb-1">Pièce {{ i + 1 }} — titre affiché</label>
                                <input
                                    v-model="doc.titre"
                                    type="text"
                                    class="w-full px-3 py-2 text-sm border border-outline-variant rounded-lg"
                                    placeholder="Ex. Carte CAMA"
                                    :disabled="!doc.actif"
                                />
                            </div>
                            <span
                                class="text-[10px] font-bold px-2 py-1 rounded-full whitespace-nowrap shrink-0"
                                :class="doc.actif ? 'bg-secondary/15 text-secondary' : 'bg-surface-container-high text-on-surface-variant'"
                            >
                                {{ doc.actif ? 'Affichée' : 'Masquée' }}
                            </span>
                        </div>
                    </div>

                    <p v-if="documentsForm.errors.documents" class="text-error text-xs mt-3">{{ documentsForm.errors.documents }}</p>

                    <div class="mt-4 pt-4 border-t border-outline-variant flex items-center gap-3 flex-wrap">
                        <CamaLoadingButton type="button" :loading="documentsForm.processing" @click="saveDocuments">Enregistrer les pièces</CamaLoadingButton>
                        <span class="text-[11px] text-on-surface-variant">Applique la section « Pièces justificatives » à l'inscription</span>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>

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
.toggle-switch {
    width: 44px;
    height: 24px;
    border-radius: 9999px;
    position: relative;
    transition: background-color 0.2s;
    cursor: pointer;
    border: none;
}
.toggle-switch .knob {
    position: absolute;
    top: 2px;
    left: 2px;
    width: 20px;
    height: 20px;
    border-radius: 9999px;
    background: white;
    transition: transform 0.2s;
}
.toggle-switch.on {
    background-color: #006e27;
}
.toggle-switch.on .knob {
    transform: translateX(20px);
}
.toggle-switch.off {
    background-color: #e4e2e1;
}
.org-guide-step {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
    min-width: 72px;
    padding: 10px 8px;
    border-radius: 0.5rem;
    background: #fff;
    border: 1px solid #e5bdbb;
    text-align: center;
}
.org-guide-step .step-num {
    width: 22px;
    height: 22px;
    border-radius: 9999px;
    background: #9e001f;
    color: #fff;
    font-size: 11px;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
}
.org-guide-arrow {
    color: #906f6e;
    font-size: 18px;
    align-self: center;
    padding: 0 2px;
}
</style>
