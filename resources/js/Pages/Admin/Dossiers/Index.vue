<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { exportFamilleFif, exportFamilleZip, exportMembreFif } from '@/Composables/useCamaExport';
import { formatPhonesDisplay } from '@/Utils/camaPhone';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useAdminDossierBadge } from '@/Composables/useAdminDossierBadge';

const props = defineProps({
    families: Array,
    queueCounts: Object,
    gestionnaires: Array,
    adminRole: String,
    gestionnaireNom: String,
    isGestionnaire: Boolean,
    validation2Niveaux: Boolean,
    fifRequise: { type: Boolean, default: false },
    affectationMode: String,
    mesStats: Object,
    statutOptions: Array,
    liensOptions: Array,
    piecesRequetables: Array,
    motifsRefus: { type: Array, default: () => [] },
});

const GROUP_PAGE_SIZE = 10;

const { markFamilyViewed } = useAdminDossierBadge();

const page = usePage();
const queueTab = ref(localStorage.getItem('cama_admin_queue_tab') || 'a_traiter');
const affectTab = ref(localStorage.getItem('cama_admin_affect_tab') || 'tous');
const currentPage = ref(1);
const selectedAssureIds = ref(new Set());
const detailFamily = ref(null);
const detailDossier = ref(null);
const messageText = ref('');
const actionModal = ref(null);
const rejectMotif = ref('');
const rejectMotifPreset = ref('');
const retraitType = ref('devenu_militaire');
const retraitMatricule = ref('');
const retraitAutre = ref('');
const complementPieces = ref([]);
const complementMessage = ref('');
const batchGestionnaire = ref('');
const toast = ref(null);
const showAssureProfile = ref(false);
const exportBusy = ref(false);

const filterStatut = ref('Tous');
const filterGestionnaire = ref('Tous');
const filterLien = ref('Tous');
const filterLot = ref('Tous');
const filterDateFrom = ref('');
const filterDateTo = ref('');
const filterSearch = ref('');

function applyRejectPreset() {
    if (rejectMotifPreset.value && rejectMotifPreset.value !== '__custom__') {
        rejectMotif.value = rejectMotifPreset.value;
    }
}

const canBatchAssign = computed(() => !props.isGestionnaire && ['superviseur', 'administrateur'].includes(props.adminRole));
const canActOnDossier = computed(() => ['gestionnaire', 'superviseur', 'administrateur'].includes(props.adminRole));

const gestionnaireOptions = computed(() => {
    const names = props.gestionnaires.map((g) => g.nom);
    return ['Non affecté', ...names];
});

const STATUS_STYLES = {
    Brouillon: 'bg-surface-container-high text-on-surface-variant',
    Soumis: 'bg-primary/10 text-primary',
    'En instruction': 'bg-tertiary/10 text-tertiary',
    'En attente supervision': 'bg-primary-fixed text-on-primary-fixed-variant',
    'Pièce manquante demandée': 'bg-tertiary text-on-tertiary',
    Validé: 'bg-secondary text-on-secondary',
    Refusé: 'bg-error text-on-error',
};

const PIECE_STYLES = {
    Validée: 'bg-secondary/10 text-secondary',
    Manquante: 'bg-tertiary text-on-tertiary',
    Refusée: 'bg-error/10 text-error',
    Soumise: 'bg-primary/10 text-primary',
};

const QUEUE_TABS = [
    { key: 'tous', label: 'Tous' },
    { key: 'a_traiter', label: 'À traiter' },
    { key: 'ajout', label: 'Ajout de membre(s)' },
    { key: 'valides', label: 'Validés' },
    { key: 'refuses', label: 'Refusés' },
    { key: 'complement', label: 'À compléter' },
];

const queueCountsWithTotal = computed(() => ({
    ...props.queueCounts,
    tous: props.families.length,
}));

const AFFECT_TABS = [
    { key: 'tous', label: 'Tous' },
    { key: 'non_affecte', label: 'Non affectés' },
    { key: 'affecte', label: 'Affectés' },
];

watch(queueTab, (v) => localStorage.setItem('cama_admin_queue_tab', v));
watch(affectTab, (v) => localStorage.setItem('cama_admin_affect_tab', v));

function showToast(message) {
    toast.value = message;
    setTimeout(() => {
        toast.value = null;
    }, 3000);
}

function affectationLabel(g) {
    if (!g || g === 'Non affecté') return 'Non affecté';
    return g;
}

function affectationBadgeClass(g) {
    if (!g || g === 'Non affecté') return 'bg-tertiary/10 text-tertiary border-tertiary/30';
    return 'bg-secondary/10 text-secondary border-secondary/30';
}

function formatDateIso(iso) {
    if (!iso) return '—';
    const [y, m, d] = iso.split('-');
    return `${d}/${m}/${y}`;
}

function matchesFilters(family) {
    const dossiers = family.dossiers.filter((d) => d.statut !== 'Brouillon');
    if (!dossiers.length && queueTab.value !== 'tous' && family.queue !== queueTab.value) return false;

    if (filterStatut.value !== 'Tous' && !dossiers.some((d) => d.statut === filterStatut.value)) return false;
    if (filterGestionnaire.value !== 'Tous' && family.gestionnaire !== filterGestionnaire.value) return false;
    if (filterLien.value !== 'Tous' && !dossiers.some((d) => d.lien === filterLien.value)) return false;
    if (filterLot.value === 'complementaire' && !dossiers.some((d) => d.isComplementFamilial || d.lotType === 'complementaire')) return false;
    if (filterLot.value === 'initial' && !dossiers.some((d) => !d.isComplementFamilial && (d.lotType || 'initial') === 'initial')) return false;
    if (filterDateFrom.value || filterDateTo.value) {
        const inRange = dossiers.some((d) => {
            if (!d.dateSoumission) return false;
            if (filterDateFrom.value && d.dateSoumission < filterDateFrom.value) return false;
            if (filterDateTo.value && d.dateSoumission > filterDateTo.value) return false;
            return true;
        });
        if (!inRange) return false;
    }

    const q = filterSearch.value.trim().toLowerCase();
    if (q) {
        const inFamily =
            family.assureNom.toLowerCase().includes(q) ||
            family.matricule?.toLowerCase().includes(q) ||
            family.numeroCama?.toLowerCase().includes(q);
        const inDossier = dossiers.some(
            (d) =>
                d.ref?.toLowerCase().includes(q) ||
                d.beneficiaire?.toLowerCase().includes(q) ||
                d.nom?.toLowerCase().includes(q),
        );
        if (!inFamily && !inDossier) return false;
    }

    return true;
}

const filteredFamilies = computed(() => {
    let list = queueTab.value === 'tous'
        ? [...props.families]
        : props.families.filter((f) => f.queue === queueTab.value);

    if (canBatchAssign.value) {
        if (affectTab.value === 'non_affecte') list = list.filter((f) => !f.gestionnaire || f.gestionnaire === 'Non affecté');
        if (affectTab.value === 'affecte') list = list.filter((f) => f.gestionnaire && f.gestionnaire !== 'Non affecté');
    }

    return list.filter(matchesFilters);
});

const affectCounts = computed(() => {
    const base = queueTab.value === 'tous'
        ? props.families
        : props.families.filter((f) => f.queue === queueTab.value);
    return {
        tous: base.length,
        non_affecte: base.filter((f) => !f.gestionnaire || f.gestionnaire === 'Non affecté').length,
        affecte: base.filter((f) => f.gestionnaire && f.gestionnaire !== 'Non affecté').length,
    };
});

const totalPages = computed(() => Math.max(1, Math.ceil(filteredFamilies.value.length / GROUP_PAGE_SIZE)));

const pageFamilies = computed(() => {
    const page = Math.min(currentPage.value, totalPages.value);
    const start = (page - 1) * GROUP_PAGE_SIZE;
    return filteredFamilies.value.slice(start, start + GROUP_PAGE_SIZE);
});

watch([queueTab, affectTab, filterStatut, filterGestionnaire, filterLien, filterLot, filterDateFrom, filterDateTo, filterSearch], () => {
    currentPage.value = 1;
});

function resetFilters() {
    filterStatut.value = 'Tous';
    filterGestionnaire.value = 'Tous';
    filterLien.value = 'Tous';
    filterLot.value = 'Tous';
    filterDateFrom.value = '';
    filterDateTo.value = '';
    filterSearch.value = '';
    affectTab.value = 'tous';
}

function toggleSelect(assureId, checked) {
    const next = new Set(selectedAssureIds.value);
    if (checked) next.add(assureId);
    else next.delete(assureId);
    selectedAssureIds.value = next;
}

function assignFamily(assureId, gestionnaire) {
    router.post(
        route('admin.dossiers.assign'),
        { assure_id: assureId, gestionnaire },
        {
            preserveScroll: true,
            onSuccess: () => {
                const msg = gestionnaire === 'Non affecté' ? 'Dossier familial marqué comme non affecté.' : `Dossier familial — Affecté à ${gestionnaire}.`;
                showToast(msg);
                if (detailFamily.value?.assureId === assureId) {
                    detailFamily.value = { ...detailFamily.value, gestionnaire };
                }
            },
        },
    );
}

function batchAssign() {
    if (!selectedAssureIds.value.size) return;
    if (!batchGestionnaire.value) {
        showToast('Veuillez choisir un gestionnaire.');
        return;
    }
    const n = selectedAssureIds.value.size;
    router.post(
        route('admin.dossiers.batch-assign'),
        { assure_ids: [...selectedAssureIds.value], gestionnaire: batchGestionnaire.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                showToast(`${n} dossier(s) affecté(s) — Affecté à ${batchGestionnaire.value}.`);
                selectedAssureIds.value = new Set();
            },
        },
    );
}

function openFamily(family) {
    detailDossier.value = null;
    detailFamily.value = family;
    showAssureProfile.value = false;
    markFamilyViewed(family.assureId);
}

function openMember(dossier, family) {
    detailFamily.value = family;
    detailDossier.value = dossier;
    messageText.value = '';
    showAssureProfile.value = false;
    markFamilyViewed(family.assureId);
}

function submittedDossiers(family) {
    return (family?.dossiers || []).filter((d) => d.statut !== 'Brouillon');
}

const familyProgress = computed(() => {
    const list = submittedDossiers(detailFamily.value).filter((d) => d.statut !== 'Retiré');
    const total = list.length;
    const validated = list.filter((d) => d.statut === 'Validé').length;
    const refused = list.filter((d) => d.statut === 'Refusé').length;
    const pending = total - validated - refused;
    return {
        total,
        validated,
        refused,
        pending,
        allValidated: total > 0 && validated === total,
        pct: total ? Math.round((validated / total) * 100) : 0,
    };
});

function openAssureProfile() {
    if (detailFamily.value?.assureProfile) showAssureProfile.value = true;
}

function closeAssureProfile() {
    showAssureProfile.value = false;
}

function assureProfileRows(profile) {
    if (!profile) return [];
    return [
        ['Matricule', profile.matricule],
        ['N° CAMA', profile.numeroCama],
        ['Statut compte', profile.statut],
        ['E-mail', profile.email],
        ['Sexe', profile.sexe],
        ['Grade', profile.grade],
        ['Catégorie', profile.categorie],
        ['N° informatique', profile.numeroInformatique],
        ['N° CIM', profile.numeroCim],
        ['N° IUP', profile.numeroIup],
        ['Armée', profile.armee],
        ['Région', profile.region],
        ['Corps', profile.corps],
        ['Service', profile.service],
        ['Section', profile.section],
        ['Sous-section', profile.sousSection],
        ['Téléphones', formatPhonesDisplay(profile.telephone)],
        ['Personne à prévenir', profile.personneAPrevenir],
        ['Tél. à prévenir', formatPhonesDisplay(profile.telPersonneAPrevenir)],
        ['Compte créé le', profile.dateCreation],
    ].filter(([, v]) => v && String(v).trim() && v !== '—');
}

function pieceViewUrl(dossierId, type) {
    return `${route('admin.dossiers.piece.download', { dossier: dossierId })}?type=${encodeURIComponent(type)}&inline=1`;
}

function pieceDownloadUrl(dossierId, type) {
    return `${route('admin.dossiers.piece.download', { dossier: dossierId })}?type=${encodeURIComponent(type)}`;
}

function isImagePiece(p) {
    return /\.(jpe?g|png|webp|gif|bmp|heic)$/i.test(p?.filename || '');
}

function identityDocumentUrl(assureId, key) {
    return route('admin.inscriptions.document', { assure: assureId, key });
}

function memberExportIndex(dossier) {
    const list = submittedDossiers(detailFamily.value);
    const idx = list.findIndex((d) => d.id === dossier.id);
    return idx >= 0 ? idx + 1 : 1;
}

function openAssureProfileFromList(family) {
    detailFamily.value = family;
    detailDossier.value = null;
    if (family?.assureProfile) showAssureProfile.value = true;
}

async function exportFamilyFifFromList(family) {
    if (!family?.membresExport?.length) {
        showToast('Aucun membre soumis pour générer la FIF.');
        return;
    }
    exportBusy.value = true;
    try {
        await exportFamilleFif(family.membresExport, family.exportProfile);
        showToast('FIF familiale générée.');
    } catch {
        showToast('Export FIF indisponible.');
    } finally {
        exportBusy.value = false;
    }
}

async function exportFamilyFif() {
    if (!detailFamily.value?.membresExport?.length) {
        showToast('Aucun membre soumis pour générer la FIF.');
        return;
    }
    exportBusy.value = true;
    try {
        await exportFamilleFif(detailFamily.value.membresExport, detailFamily.value.exportProfile);
        showToast('FIF familiale générée.');
    } catch {
        showToast('Export FIF indisponible.');
    } finally {
        exportBusy.value = false;
    }
}

async function exportFamilyZip() {
    if (!detailFamily.value?.membresExport?.length) {
        showToast('Aucun membre soumis pour l\'archive.');
        return;
    }
    exportBusy.value = true;
    try {
        await exportFamilleZip(detailFamily.value.membresExport, detailFamily.value.exportProfile);
        showToast('Archive familiale générée.');
    } catch {
        showToast('Export ZIP indisponible.');
    } finally {
        exportBusy.value = false;
    }
}

async function exportMemberFif(dossier, index = 1) {
    if (!detailFamily.value?.exportProfile || !dossier) return;
    exportBusy.value = true;
    try {
        await exportMembreFif(dossier, detailFamily.value.exportProfile, index);
        showToast(`FIF de ${dossier.beneficiaire} générée.`);
    } catch {
        showToast('Export FIF indisponible.');
    } finally {
        exportBusy.value = false;
    }
}

function closeDetail() {
    detailFamily.value = null;
    detailDossier.value = null;
    actionModal.value = null;
    showAssureProfile.value = false;
}

function backToFamily() {
    detailDossier.value = null;
    actionModal.value = null;
}

function getValidationUi(d) {
    if (['Validé', 'Refusé', 'Brouillon'].includes(d.statut)) return { label: 'Valider', show: false };
    if (props.fifRequise && !d.hasLotFif) {
        return {
            label: 'Valider',
            show: false,
            fifMissing: true,
            sub: 'La FIF signée du lot familial est absente. Demandez un complément à l\'assuré avant toute validation.',
        };
    }
    if (d.statut === 'En attente supervision' && props.isGestionnaire) return { label: 'Valider', show: false };
    if (d.statut === 'En attente supervision' && canBatchAssign.value) {
        return {
            label: props.validation2Niveaux ? 'Valider (niveau 2)' : 'Valider',
            sub: 'Validation finale — le dossier quittera la file active.',
            show: true,
        };
    }
    if (props.adminRole === 'superviseur' && d.statut === 'En attente supervision') {
        return {
            label: props.validation2Niveaux ? 'Valider (supervision)' : 'Valider',
            sub: 'Validation finale par le superviseur.',
            show: true,
        };
    }
    if (props.validation2Niveaux && props.isGestionnaire) {
        return {
            label: 'Valider (niveau 1)',
            sub: 'Validation à deux niveaux activée : le dossier passera « En attente supervision » et sera validé définitivement par le superviseur.',
            show: true,
        };
    }
    return {
        label: 'Valider',
        sub: props.adminRole === 'superviseur' ? 'Le superviseur peut traiter et valider ce dossier.' : "Le gestionnaire peut valider directement. L'assuré sera notifié.",
        show: true,
    };
}

function openAction(type) {
    actionModal.value = type;
    rejectMotif.value = '';
    rejectMotifPreset.value = '';
    complementPieces.value = [];
    complementMessage.value = '';
    retraitType.value = 'devenu_militaire';
    retraitMatricule.value = '';
    retraitAutre.value = '';
}

function confirmAction() {
    if (!detailDossier.value) return;
    const id = detailDossier.value.id;

    if (actionModal.value === 'valider') {
        router.post(route('admin.dossiers.validate', id), {}, { preserveScroll: true, onSuccess: () => { actionModal.value = null; closeDetail(); } });
    } else if (actionModal.value === 'refuser') {
        if (!rejectMotif.value.trim()) return;
        router.post(route('admin.dossiers.reject', id), { motif: rejectMotif.value }, { preserveScroll: true, onSuccess: () => { actionModal.value = null; closeDetail(); } });
    } else if (actionModal.value === 'complement') {
        if (!complementPieces.value.length && !complementMessage.value.trim()) return;
        router.post(
            route('admin.dossiers.complement', id),
            { pieces: complementPieces.value, message: complementMessage.value.trim() || null },
            { preserveScroll: true, onSuccess: () => { actionModal.value = null; closeDetail(); } },
        );
    } else if (actionModal.value === 'attente') {
        router.post(route('admin.dossiers.en-attente', id), {}, { preserveScroll: true, onSuccess: () => { actionModal.value = null; closeDetail(); } });
    } else if (actionModal.value === 'retrait') {
        let motif;
        if (retraitType.value === 'devenu_militaire') {
            motif = 'Devenu militaire — assuré à part entière';
            if (retraitMatricule.value.trim()) motif += ` (nouveau matricule : ${retraitMatricule.value.trim()})`;
        } else {
            motif = retraitAutre.value.trim();
            if (!motif) return;
        }
        router.post(route('admin.dossiers.retrait', id), { motif }, { preserveScroll: true, onSuccess: () => { actionModal.value = null; closeDetail(); } });
    }
}

function sendMessage() {
    if (!detailDossier.value || !messageText.value.trim()) return;
    const text = messageText.value.trim();
    router.post(
        route('admin.dossiers.message', detailDossier.value.id),
        { texte: text },
        {
            preserveScroll: true,
            onSuccess: () => {
                messageText.value = '';
                syncDetailFromProps();
                if (detailDossier.value) {
                    if (!detailDossier.value.messages) detailDossier.value.messages = [];
                    const already = detailDossier.value.messages.some((m) => m.texte === text && m.auteur === 'gestionnaire');
                    if (!already) {
                        detailDossier.value.messages.push({
                            auteur: 'gestionnaire',
                            texte: text,
                            date: new Date().toLocaleString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }).replace(',', ''),
                        });
                    }
                }
            },
        },
    );
}

function beneficiaireInfo(d) {
    const rows = [
        ['Sexe', d.sexe],
        ['Date de naissance', d.dateNaissance],
        ['Lieu de naissance', d.lieuNaissance],
        ['Groupe sanguin', d.groupeSanguin],
        ['N° Carte CAMA', d.numeroCama],
        ['Téléphone', d.telephone],
        ["Réf. document d'identité", d.refIdentite],
    ];
    if ((d.lien || '').startsWith('Enfant')) {
        rows.push(["Réf. acte scolarité / état civil", d.refActeScolariteEtatCivil]);
        rows.push(['Nom et prénoms du parent', d.nomPrenomsParent]);
    } else if (d.lien === 'Conjoint(e)') {
        rows.push(['Réf. acte de mariage', d.refActeMariage]);
        rows.push(['Profession', d.profession]);
        rows.push(['Lieu de résidence', d.lieuResidence]);
    }
    return rows.filter(([, v]) => v && String(v).trim() && v !== '—');
}

function toggleComplementPiece(piece) {
    const idx = complementPieces.value.indexOf(piece);
    if (idx >= 0) complementPieces.value.splice(idx, 1);
    else complementPieces.value.push(piece);
}

function syncDetailFromProps() {
    if (!detailFamily.value) return;
    const fam = (page.props.families || []).find((f) => f.assureId === detailFamily.value.assureId);
    if (!fam) return;
    detailFamily.value = fam;
    if (detailDossier.value) {
        const dossier = fam.dossiers.find((d) => d.id === detailDossier.value.id);
        if (dossier) detailDossier.value = dossier;
    }
}

watch(() => page.props.families, syncDetailFromProps, { deep: true });

const showDetail = computed(() => detailFamily.value || detailDossier.value);
const validationUi = computed(() => (detailDossier.value ? getValidationUi(detailDossier.value) : { show: false }));

const canSendComplement = computed(() =>
    complementPieces.value.length > 0 || complementMessage.value.trim().length > 0,
);

const detailLotFifPiece = computed(() => {
    const d = detailDossier.value;
    if (!d?.pieces?.length) return null;
    return d.pieces.find((p) => p.lot && p.hasFile) ?? null;
});
</script>

<template>
    <Head title="Dossiers" />

    <AdminLayout
        active-nav="dossiers"
        title="Dossiers"
        subtitle="Traitement par dossier familial (assuré principal) · affectation et validation au niveau du dossier"
    >
        <details class="info-banner-details mb-4 md:mb-5 rounded-xl border border-primary/20 bg-primary/5 overflow-hidden">
            <summary class="flex items-center gap-3 p-4 cursor-pointer list-none select-none">
                <span class="material-symbols-outlined text-primary text-[22px] shrink-0">info</span>
                <span class="font-bold text-sm text-on-surface flex-1">Comment lire cette page</span>
                <span class="material-symbols-outlined text-on-surface-variant info-chevron text-[20px]">expand_more</span>
            </summary>
            <div class="px-4 pb-4 pt-0 text-xs text-on-surface-variant leading-relaxed border-t border-primary/10">
                <p class="md:hidden mb-2"><strong class="text-on-surface">Assuré</strong> = le militaire. <strong class="text-on-surface">Dossier familial</strong> = l'ensemble des membres déclarés (conjoints, enfants…).</p>
                <p class="hidden md:block"><strong class="text-on-surface">Assuré principal</strong> = le militaire. Le <strong class="text-on-surface">dossier familial</strong> regroupe toutes les demandes d'enrôlement de ses ayants droit. L'<strong class="text-on-surface">affectation</strong> et le <strong class="text-on-surface">traitement</strong> se font au niveau du dossier assuré.</p>
                <p class="mt-2">Utilisez les onglets pour séparer les dossiers <strong>à traiter</strong>, <strong>validés</strong>, <strong>refusés</strong> et <strong>à compléter</strong>.</p>
            </div>
        </details>

        <div class="mb-4">
            <p class="text-[11px] font-bold uppercase tracking-wide text-on-surface-variant mb-2">File de traitement</p>
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="tab in QUEUE_TABS"
                    :key="tab.key"
                    type="button"
                    class="px-3 py-2 rounded-full text-xs font-bold border border-outline-variant"
                    :class="queueTab === tab.key ? 'bg-primary text-on-primary border-primary' : 'bg-white text-on-surface-variant'"
                    @click="queueTab = tab.key"
                >
                    {{ tab.label }} <span class="inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 rounded-full text-[10px] ml-1" :class="queueTab === tab.key ? 'bg-white/25' : 'bg-black/10'">{{ queueCountsWithTotal[tab.key] ?? 0 }}</span>
                </button>
            </div>
        </div>

        <div v-if="canBatchAssign" class="mb-4">
            <p class="text-[11px] font-bold uppercase tracking-wide text-on-surface-variant mb-2">Affectation des dossiers</p>
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="tab in AFFECT_TABS"
                    :key="tab.key"
                    type="button"
                    class="px-3 py-2 rounded-full text-xs font-bold border border-outline-variant"
                    :class="[
                        affectTab === tab.key
                            ? tab.key === 'non_affecte'
                                ? 'bg-tertiary text-on-tertiary border-tertiary'
                                : tab.key === 'affecte'
                                  ? 'bg-secondary text-on-secondary border-secondary'
                                  : 'bg-primary text-on-primary border-primary'
                            : 'bg-white text-on-surface-variant',
                    ]"
                    @click="affectTab = tab.key"
                >
                    {{ tab.label }} <span class="inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 rounded-full text-[10px] ml-1" :class="affectTab === tab.key ? 'bg-white/25' : 'bg-black/10'">{{ affectCounts[tab.key] }}</span>
                </button>
            </div>
        </div>

        <div v-if="isGestionnaire" class="assure-card p-4 mb-4 border border-secondary/25 bg-secondary/5">
            <div class="flex items-start gap-3">
                <span class="material-symbols-outlined text-secondary text-[22px] shrink-0">assignment_ind</span>
                <div class="text-xs text-on-surface-variant leading-relaxed flex-1">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <p class="font-bold text-on-surface text-sm mb-0.5">Mes dossiers affectés</p>
                        <p v-if="mesStats?.derniereAffectation" class="text-[11px]">Dernière affectation reçue le <strong class="text-on-surface">{{ mesStats.derniereAffectation }}</strong></p>
                    </div>
                    <p>Vous ne voyez que les dossiers familiaux qui vous ont été <strong class="text-on-surface">affectés</strong> (par le superviseur ou automatiquement).</p>
                    <div v-if="mesStats" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2 mt-3">
                        <div class="bg-white rounded-lg border border-outline-variant px-3 py-2">
                            <p class="text-lg font-bold text-on-surface leading-tight">{{ mesStats.total }}</p>
                            <p class="text-[10px] uppercase tracking-wide">Affectés au total</p>
                        </div>
                        <div class="bg-white rounded-lg border border-outline-variant px-3 py-2">
                            <p class="text-lg font-bold text-primary leading-tight">{{ mesStats.aTraiter }}</p>
                            <p class="text-[10px] uppercase tracking-wide">À traiter</p>
                        </div>
                        <div class="bg-white rounded-lg border border-outline-variant px-3 py-2">
                            <p class="text-lg font-bold text-on-surface leading-tight">{{ mesStats.complement }}</p>
                            <p class="text-[10px] uppercase tracking-wide">Complément demandé</p>
                        </div>
                        <div class="bg-white rounded-lg border border-outline-variant px-3 py-2">
                            <p class="text-lg font-bold text-on-surface leading-tight">{{ mesStats.enSupervision }}</p>
                            <p class="text-[10px] uppercase tracking-wide">En supervision</p>
                        </div>
                        <div class="bg-white rounded-lg border border-outline-variant px-3 py-2">
                            <p class="text-lg font-bold text-on-surface leading-tight">{{ mesStats.valides }}</p>
                            <p class="text-[10px] uppercase tracking-wide">Validés</p>
                        </div>
                        <div class="bg-white rounded-lg border border-outline-variant px-3 py-2">
                            <p class="text-lg font-bold text-on-surface leading-tight">{{ mesStats.refuses }}</p>
                            <p class="text-[10px] uppercase tracking-wide">Refusés</p>
                        </div>
                    </div>
                    <p v-if="gestionnaireNom" class="mt-2 text-[11px]">Gestionnaire : <strong class="text-on-surface">{{ gestionnaireNom }}</strong></p>
                </div>
            </div>
        </div>

        <div v-if="canBatchAssign && affectationMode !== 'manuelle'" class="assure-card p-3 mb-4 border border-secondary/25 bg-secondary/5 flex items-center gap-2 text-xs text-on-surface-variant">
            <span class="material-symbols-outlined text-secondary text-[20px] shrink-0">smart_toy</span>
            <p>
                Affectation automatique active — <strong class="text-on-surface">{{ affectationMode === 'round_robin' ? 'répartition équilibrée (round-robin)' : 'charge la plus faible' }}</strong> :
                les nouveaux dossiers soumis sont attribués automatiquement aux gestionnaires actifs. Vous pouvez toujours réaffecter manuellement.
            </p>
        </div>

        <div v-if="canBatchAssign && selectedAssureIds.size" class="assure-card p-4 mb-4">
            <div class="flex flex-col gap-3">
                <p class="text-xs text-on-surface-variant"><span class="font-bold text-on-surface">{{ selectedAssureIds.size }}</span> dossier(s) familial(aux) sélectionné(s).</p>
                <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                    <select v-model="batchGestionnaire" class="flex-1 px-3 py-2 text-xs border border-outline-variant rounded-lg bg-white min-w-0">
                        <option value="">Choisir un gestionnaire…</option>
                        <option v-for="g in gestionnaireOptions" :key="g" :value="g">{{ g }}</option>
                    </select>
                    <button type="button" class="px-4 py-2 rounded-lg bg-primary text-on-primary text-xs font-bold shrink-0" @click="batchAssign">Affecter la sélection</button>
                </div>
            </div>
        </div>

        <div class="assure-card p-4 mb-4 grid grid-cols-1 sm:grid-cols-2 lg:flex lg:flex-wrap gap-3 items-stretch lg:items-center">
            <select v-model="filterStatut" class="filter-field px-3 py-3 sm:py-2 text-sm sm:text-xs border border-outline-variant rounded-lg bg-white">
                <option value="Tous">Tous les statuts</option>
                <option v-for="s in statutOptions" :key="s" :value="s">{{ s }}</option>
            </select>
            <select v-if="canBatchAssign" v-model="filterGestionnaire" class="filter-field px-3 py-3 sm:py-2 text-sm sm:text-xs border border-outline-variant rounded-lg bg-white">
                <option value="Tous">Tous les gestionnaires affectés</option>
                <option v-for="g in gestionnaireOptions" :key="g" :value="g">{{ g }}</option>
            </select>
            <select v-model="filterLien" class="filter-field px-3 py-3 sm:py-2 text-sm sm:text-xs border border-outline-variant rounded-lg bg-white">
                <option value="Tous">Tous les liens</option>
                <option v-for="l in liensOptions" :key="l" :value="l">{{ l }}</option>
            </select>
            <select v-model="filterLot" class="filter-field px-3 py-3 sm:py-2 text-sm sm:text-xs border border-outline-variant rounded-lg bg-white">
                <option value="Tous">Tous les lots</option>
                <option value="initial">Lot initial</option>
                <option value="complementaire">Complément familial</option>
            </select>
            <div class="filter-field flex items-center gap-1.5 px-2 py-1.5 sm:py-1 border border-outline-variant rounded-lg bg-white sm:col-span-2 lg:col-span-1">
                <span class="material-symbols-outlined text-on-surface-variant text-[16px] shrink-0" title="Filtrer par date de soumission">calendar_month</span>
                <input v-model="filterDateFrom" class="min-w-0 flex-1 text-sm sm:text-xs bg-transparent outline-none" type="date" :max="filterDateTo || undefined" title="Du" />
                <span class="text-[11px] text-on-surface-variant shrink-0">→</span>
                <input v-model="filterDateTo" class="min-w-0 flex-1 text-sm sm:text-xs bg-transparent outline-none" type="date" :min="filterDateFrom || undefined" title="Au" />
                <button v-if="filterDateFrom || filterDateTo" type="button" class="text-on-surface-variant hover:text-error shrink-0" title="Effacer les dates" @click="filterDateFrom = ''; filterDateTo = ''">
                    <span class="material-symbols-outlined text-[16px]">close</span>
                </button>
            </div>
            <div class="relative filter-field lg:flex-1 lg:min-w-[180px] sm:col-span-2">
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant text-[18px]">search</span>
                <input v-model="filterSearch" class="w-full pl-10 pr-3 py-3 sm:py-2 text-sm sm:text-xs border border-outline-variant rounded-lg bg-white" placeholder="Réf., membre ou assuré…" type="search" />
            </div>
            <button type="button" class="filter-field w-full sm:w-auto py-3 sm:py-2 px-4 rounded-lg border border-primary/30 bg-primary/5 text-primary font-bold text-sm sm:text-xs hover:bg-primary/10 sm:border-0 sm:bg-transparent sm:hover:underline" @click="resetFilters">Réinitialiser les filtres</button>
        </div>

        <div class="space-y-3">
            <div v-if="!pageFamilies.length" class="assure-card p-10 text-center text-on-surface-variant text-sm">
                Aucun dossier familial ne correspond à ces filtres.
            </div>

            <details
                v-for="family in pageFamilies"
                :key="family.assureId"
                class="assure-card assure-accordion overflow-hidden group"
            >
                <summary class="flex flex-col sm:flex-row sm:flex-wrap sm:items-center gap-3 px-4 py-4 sm:px-5 cursor-pointer hover:bg-surface-container-low transition-colors list-none select-none">
                    <div class="flex items-center gap-3 w-full sm:w-auto sm:flex-1 min-w-0">
                        <label v-if="canBatchAssign" class="flex items-center shrink-0" @click.stop>
                            <input
                                type="checkbox"
                                class="w-4 h-4 rounded border-outline-variant"
                                :checked="selectedAssureIds.has(family.assureId)"
                                @change="toggleSelect(family.assureId, $event.target.checked)"
                            />
                        </label>
                        <div class="w-11 h-11 rounded-full bg-primary/10 text-primary flex items-center justify-center font-bold text-sm shrink-0">{{ family.initiales }}</div>
                        <div class="min-w-0 flex-1">
                            <p class="font-bold text-base sm:text-sm text-on-surface truncate">{{ family.assureNom }}</p>
                            <p class="text-xs text-on-surface-variant">{{ family.matricule }} · {{ family.numeroCama || '—' }}</p>
                            <p v-if="family.lastTraitement" class="text-[10px] text-on-surface-variant mt-0.5">Dernier traitement : {{ family.lastTraitement }}</p>
                        </div>
                        <span class="material-symbols-outlined text-on-surface-variant accordion-chevron sm:hidden shrink-0">expand_more</span>
                    </div>
                    <div class="flex flex-wrap gap-2 items-center w-full sm:w-auto">
                        <span
                            v-if="family.ajoutEnCours"
                            class="px-3 py-1.5 rounded-full text-xs font-bold bg-tertiary/15 text-tertiary inline-flex items-center gap-1"
                            title="Assuré déjà validé qui ajoute de nouveaux membres (complément familial)"
                        >
                            <span class="material-symbols-outlined text-[15px]">group_add</span>
                            Ajout de membre{{ family.ajoutEnCoursCount > 1 ? 's' : '' }}
                        </span>
                        <span class="px-3 py-1.5 rounded-full text-xs font-bold bg-surface-container-high text-on-surface-variant">{{ family.total }} demande{{ family.total > 1 ? 's' : '' }}</span>
                        <span v-if="family.pending > 0" class="px-3 py-1.5 rounded-full text-xs font-bold bg-primary/10 text-primary">{{ family.pending }} à traiter</span>
                        <span v-else class="px-3 py-1.5 rounded-full text-xs font-bold bg-secondary/10 text-secondary">À jour</span>
                        <span class="hidden sm:inline px-2.5 py-1 rounded-full text-[10px] font-bold border" :class="affectationBadgeClass(family.gestionnaire)">{{ affectationLabel(family.gestionnaire) }}</span>
                    </div>
                    <span class="material-symbols-outlined text-on-surface-variant accordion-chevron hidden sm:block shrink-0">expand_more</span>
                </summary>

                <div class="px-4 pb-4 sm:px-5 border-t border-outline-variant">
                    <div class="sm:hidden mb-2">
                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold border" :class="affectationBadgeClass(family.gestionnaire)">{{ affectationLabel(family.gestionnaire) }}</span>
                    </div>
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-3 mt-3">
                        <p class="text-[10px] uppercase tracking-wide text-on-surface-variant font-bold">Demandes d'enrôlement des membres</p>
                        <div class="flex flex-wrap items-center gap-2">
                            <button type="button" class="inline-flex items-center gap-1 text-[11px] font-bold text-primary hover:underline" title="Profil complet de l'assuré" @click.stop="openAssureProfileFromList(family)">
                                <span class="material-symbols-outlined text-[14px]">person</span> Profil
                            </button>
                            <button type="button" class="inline-flex items-center gap-1 text-[11px] font-bold text-primary hover:underline disabled:opacity-50" :disabled="exportBusy || !submittedDossiers(family).length" title="Télécharger la FIF de toute la famille" @click.stop="exportFamilyFifFromList(family)">
                                <span class="material-symbols-outlined text-[14px]">picture_as_pdf</span> FIF familiale
                            </button>
                            <div v-if="canBatchAssign" class="flex items-center gap-2">
                                <label class="text-[10px] text-on-surface-variant whitespace-nowrap">Affecter à</label>
                                <select class="text-[11px] px-2 py-1.5 border border-outline-variant rounded-lg bg-white max-w-[280px]" :value="family.gestionnaire" @change="assignFamily(family.assureId, $event.target.value)">
                                    <option v-for="g in gestionnaireOptions" :key="g" :value="g">{{ g }}</option>
                                </select>
                            </div>
                            <button type="button" class="text-[11px] font-bold text-primary hover:underline" @click="openFamily(family)">Traiter le dossier familial</button>
                        </div>
                    </div>

                    <div class="md:hidden space-y-2 mb-3">
                        <button
                            v-for="d in family.dossiers.filter((x) => x.statut !== 'Brouillon')"
                            :key="d.id"
                            type="button"
                            class="w-full text-left p-3 rounded-lg border border-outline-variant bg-white hover:bg-surface-container-low"
                            @click="openMember(d, family)"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="text-xs font-bold">{{ d.beneficiaire }}</p>
                                    <p class="text-[10px] text-on-surface-variant">{{ d.ref }} · {{ d.lien }}</p>
                                    <span
                                        v-if="d.isComplementFamilial"
                                        class="inline-block mt-1 px-2 py-0.5 rounded-full text-[9px] font-bold bg-tertiary/15 text-tertiary"
                                    >Complément familial</span>
                                </div>
                                <span class="px-2.5 py-1 rounded-full text-[11px] font-bold whitespace-nowrap" :class="STATUS_STYLES[d.statut] || ''">{{ d.statut }}</span>
                            </div>
                        </button>
                    </div>

                    <div class="hidden md:block overflow-x-auto rounded-lg border border-outline-variant bg-white">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-surface-container-low text-[10px] uppercase text-on-surface-variant">
                                <tr>
                                    <th class="px-3 py-2">Référence</th>
                                    <th class="px-3 py-2">Membre</th>
                                    <th class="px-3 py-2">Lien</th>
                                    <th class="px-3 py-2">Date</th>
                                    <th class="px-3 py-2">Affectation</th>
                                    <th class="px-3 py-2">Statut</th>
                                    <th class="px-3 py-2 text-right" />
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-outline-variant">
                                <tr
                                    v-for="d in family.dossiers.filter((x) => x.statut !== 'Brouillon')"
                                    :key="d.id"
                                    class="hover:bg-surface-container-low cursor-pointer"
                                    @click="openMember(d, family)"
                                >
                                    <td class="px-3 py-2.5 font-bold">{{ d.ref }}</td>
                                    <td class="px-3 py-2.5">
                                        <div class="flex flex-col gap-1">
                                            <span>{{ d.beneficiaire }}</span>
                                            <span
                                                v-if="d.isComplementFamilial"
                                                class="self-start px-2 py-0.5 rounded-full text-[9px] font-bold bg-tertiary/15 text-tertiary"
                                            >Complément familial</span>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2.5 text-on-surface-variant">{{ d.lien }}</td>
                                    <td class="px-3 py-2.5 text-on-surface-variant">{{ formatDateIso(d.dateSoumission) }}</td>
                                    <td class="px-3 py-2.5 text-on-surface-variant">{{ affectationLabel(family.gestionnaire) }}</td>
                                    <td class="px-3 py-2.5">
                                        <span class="px-2.5 py-1 rounded-full text-[11px] font-bold whitespace-nowrap" :class="STATUS_STYLES[d.statut] || ''">{{ d.statut }}</span>
                                    </td>
                                    <td class="px-3 py-2.5 text-right"><span class="material-symbols-outlined text-[18px] text-on-surface-variant">chevron_right</span></td>
                                </tr>
                                <tr v-if="!family.dossiers.filter((x) => x.statut !== 'Brouillon').length">
                                    <td colspan="7" class="px-3 py-6 text-center text-on-surface-variant italic">Aucun membre soumis.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </details>
        </div>

        <div v-if="filteredFamilies.length > GROUP_PAGE_SIZE" class="px-1 py-4 flex items-center justify-between flex-wrap gap-3">
            <p class="text-[11px] text-on-surface-variant">{{ filteredFamilies.length }} assurés</p>
            <div class="flex gap-2">
                <button
                    v-for="p in totalPages"
                    :key="p"
                    type="button"
                    class="w-8 h-8 rounded-lg text-xs font-bold"
                    :class="p === currentPage ? 'bg-primary text-on-primary' : 'border border-outline text-on-surface-variant hover:bg-surface-container-low'"
                    @click="currentPage = p"
                >
                    {{ p }}
                </button>
            </div>
        </div>
        <p v-else-if="filteredFamilies.length" class="text-[11px] text-on-surface-variant px-1 py-4">{{ filteredFamilies.length }} assuré{{ filteredFamilies.length > 1 ? 's' : '' }}</p>

        <!-- Panneau détail familial ou membre -->
        <div v-if="showDetail" class="fixed inset-0 bg-black/50 z-50 flex items-stretch justify-end" @click.self="closeDetail">
            <div class="bg-surface-container-low w-full sm:max-w-2xl h-full overflow-y-auto shadow-2xl">
                <!-- Vue dossier familial -->
                <template v-if="detailFamily && !detailDossier">
                    <div class="sticky top-0 bg-white border-b border-outline-variant px-4 py-4 sm:px-5 z-10">
                        <div class="flex items-start gap-3 mb-3">
                            <button type="button" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg border border-outline-variant bg-surface-container-low text-xs font-bold" @click="closeDetail">
                                <span class="material-symbols-outlined text-primary text-[20px]">arrow_back</span> Retour à la liste des dossiers
                            </button>
                        </div>
                        <div class="min-w-0">
                            <p class="text-[10px] uppercase tracking-wide text-primary font-bold mb-0.5">Dossier familial</p>
                            <h2 class="font-title-lg text-lg text-on-surface leading-tight">{{ detailFamily.assureNom }}</h2>
                            <p class="text-xs text-on-surface-variant mt-1">{{ detailFamily.matricule }} · {{ detailFamily.numeroCama || '—' }} · {{ detailFamily.total }} membre(s)</p>
                        </div>
                    </div>
                    <div class="p-4 sm:p-5 space-y-5 pb-24">
                        <div class="flex flex-wrap gap-2">
                            <button type="button" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-outline-variant bg-white text-xs font-bold text-primary hover:bg-surface-container-low" @click="openAssureProfile">
                                <span class="material-symbols-outlined text-[16px]">person</span>
                                Profil de l'assuré
                            </button>
                            <button type="button" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-primary/30 bg-primary/5 text-xs font-bold text-primary hover:bg-primary/10 disabled:opacity-50" :disabled="exportBusy || !submittedDossiers(detailFamily).length" @click="exportFamilyFif">
                                <span class="material-symbols-outlined text-[16px]">picture_as_pdf</span>
                                FIF familiale
                            </button>
                            <button type="button" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-outline-variant bg-white text-xs font-bold text-on-surface hover:bg-surface-container-low disabled:opacity-50" :disabled="exportBusy || !submittedDossiers(detailFamily).length" @click="exportFamilyZip">
                                <span class="material-symbols-outlined text-[16px]">folder_zip</span>
                                Archive ZIP
                            </button>
                        </div>

                        <div v-if="canBatchAssign">
                            <label class="text-[11px] font-bold uppercase tracking-wide text-on-surface-variant block mb-1.5">Affectation du dossier familial</label>
                            <select class="w-full px-3 py-2 text-sm border border-outline-variant rounded-lg bg-white" :value="detailFamily.gestionnaire" @change="assignFamily(detailFamily.assureId, $event.target.value)">
                                <option v-for="g in gestionnaireOptions" :key="g" :value="g">{{ g }}</option>
                            </select>
                        </div>
                        <div v-else class="bg-surface-container-low rounded-lg p-3 text-xs">
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold border" :class="affectationBadgeClass(detailFamily.gestionnaire)">{{ affectationLabel(detailFamily.gestionnaire) }}</span>
                        </div>
                        <div v-if="detailFamily.lastTraitement" class="bg-surface-container-low rounded-lg p-3 text-xs">
                            <strong>Dernier traitement :</strong> {{ detailFamily.lastTraitement }}
                            <span v-if="detailFamily.lastTraitementLibelle"> — {{ detailFamily.lastTraitementLibelle }}</span>
                        </div>
                        <div>
                            <div v-if="detailFamily.ajoutEnCours" class="mb-3 rounded-lg border border-tertiary/30 bg-tertiary/5 p-3 flex items-start gap-2">
                                <span class="material-symbols-outlined text-tertiary text-[18px] shrink-0">group_add</span>
                                <p class="text-xs text-on-surface-variant">
                                    <strong class="text-on-surface">Ajout de membre(s) sur un dossier déjà validé.</strong>
                                    Cet assuré est déjà pris en charge ({{ detailFamily.validesCount }} membre(s) validé(s)) et ajoute
                                    <strong class="text-on-surface">{{ detailFamily.ajoutEnCoursCount }} nouveau(x) membre(s)</strong> à instruire. Les membres déjà validés restent acquis.
                                </p>
                            </div>

                            <h3 class="text-xs font-bold uppercase text-on-surface-variant mb-2">Membres du dossier</h3>

                            <div
                                v-if="familyProgress.total"
                                class="mb-3 rounded-lg border p-3"
                                :class="familyProgress.allValidated ? 'border-secondary/40 bg-secondary/5' : 'border-outline-variant bg-surface-container-low'"
                            >
                                <div class="flex items-center justify-between gap-2 mb-2">
                                    <p class="text-xs font-bold" :class="familyProgress.allValidated ? 'text-secondary' : 'text-on-surface'">
                                        <span v-if="familyProgress.allValidated" class="inline-flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">verified</span> Dossier familial complet — tous les membres validés</span>
                                        <span v-else>{{ familyProgress.validated }} / {{ familyProgress.total }} membre(s) validé(s)</span>
                                    </p>
                                    <span class="text-[11px] font-semibold text-on-surface-variant tabular-nums">{{ familyProgress.pct }}%</span>
                                </div>
                                <div class="h-2 rounded-full bg-outline-variant/40 overflow-hidden">
                                    <div class="h-full rounded-full transition-all duration-300" :class="familyProgress.allValidated ? 'bg-secondary' : 'bg-primary'" :style="{ width: familyProgress.pct + '%' }" />
                                </div>
                                <p v-if="!familyProgress.allValidated" class="text-[11px] text-on-surface-variant mt-2">
                                    Cliquez sur chaque membre pour le consulter puis le valider. Le dossier familial passe en « Validé » une fois tous les membres validés.
                                </p>
                                <p v-if="familyProgress.refused" class="text-[11px] text-error mt-1">{{ familyProgress.refused }} membre(s) refusé(s).</p>
                            </div>

                            <div class="space-y-2">
                                <button
                                    v-for="(d, idx) in submittedDossiers(detailFamily)"
                                    :key="d.id"
                                    type="button"
                                    class="w-full text-left p-3 rounded-lg border border-outline-variant bg-white hover:bg-surface-container-low flex items-center justify-between gap-2"
                                    @click="openMember(d, detailFamily)"
                                >
                                    <div class="min-w-0">
                                        <p class="text-xs font-bold text-on-surface flex items-center gap-1.5">
                                            {{ d.beneficiaire }}
                                            <span v-if="d.isComplementFamilial && d.statut !== 'Validé'" class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-tertiary/15 text-tertiary uppercase tracking-wide">Nouveau</span>
                                        </p>
                                        <p class="text-[10px] text-on-surface-variant">{{ d.ref }} · {{ d.lien }} · soumis {{ formatDateIso(d.dateSoumission) }}</p>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        <button
                                            type="button"
                                            class="w-8 h-8 rounded-lg border border-outline-variant flex items-center justify-center text-primary hover:bg-primary/5"
                                            title="Télécharger la FIF du membre"
                                            :disabled="exportBusy"
                                            @click.stop="exportMemberFif(d, idx + 1)"
                                        >
                                            <span class="material-symbols-outlined text-[16px]">picture_as_pdf</span>
                                        </button>
                                        <span class="px-2.5 py-1 rounded-full text-[11px] font-bold whitespace-nowrap" :class="STATUS_STYLES[d.statut] || ''">{{ d.statut }}</span>
                                    </div>
                                </button>
                                <p v-if="!submittedDossiers(detailFamily).length" class="text-xs text-on-surface-variant italic">Aucun membre soumis.</p>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- Vue membre -->
                <template v-if="detailDossier">
                    <div class="sticky top-0 bg-white border-b border-outline-variant px-4 py-4 sm:px-5 z-10">
                        <div class="flex items-start gap-3 mb-3">
                            <button type="button" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg border border-outline-variant bg-surface-container-low text-xs font-bold" @click="backToFamily">
                                <span class="material-symbols-outlined text-primary text-[20px]">arrow_back</span> Retour au dossier familial
                            </button>
                        </div>
                        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-[10px] uppercase tracking-wide text-primary font-bold mb-0.5">Demande — membre de famille</p>
                                <p class="text-[11px] text-on-surface-variant break-all">{{ detailDossier.ref }}</p>
                                <h2 class="font-title-lg text-lg sm:text-title-lg text-on-surface leading-tight mt-1">{{ detailDossier.beneficiaire }}</h2>
                                <p class="text-xs text-on-surface-variant mt-1">Assuré : <strong class="text-on-surface">{{ detailFamily?.assureNom }}</strong></p>
                            </div>
                            <div class="flex flex-wrap gap-2 shrink-0">
                                <button type="button" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg border border-outline-variant bg-white text-[11px] font-bold text-primary" @click="openAssureProfile">
                                    <span class="material-symbols-outlined text-[14px]">person</span> Profil assuré
                                </button>
                                <button type="button" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg border border-primary/30 bg-primary/5 text-[11px] font-bold text-primary disabled:opacity-50" :disabled="exportBusy" @click="exportMemberFif(detailDossier, memberExportIndex(detailDossier))">
                                    <span class="material-symbols-outlined text-[14px]">picture_as_pdf</span> FIF membre
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="p-4 sm:p-5 space-y-5 pb-36 sm:pb-5">
                        <div v-if="detailDossier.motifRefus" class="bg-error/5 border border-error/20 rounded-lg p-3 flex gap-2">
                            <span class="material-symbols-outlined text-error text-[18px]">error</span>
                            <p class="text-xs"><strong class="text-error">Motif du refus :</strong> {{ detailDossier.motifRefus }}</p>
                        </div>

                        <div
                            v-if="detailLotFifPiece"
                            class="bg-secondary/10 border border-secondary/30 rounded-lg p-3 flex gap-2"
                        >
                            <span class="material-symbols-outlined text-secondary text-[20px] shrink-0">task</span>
                            <div class="min-w-0">
                                <p class="text-xs font-bold text-on-surface">FIF signée du lot familial</p>
                                <p class="text-[11px] text-on-surface-variant truncate">{{ detailLotFifPiece.filename || 'Document archivé' }}</p>
                                <div class="flex flex-wrap gap-2 mt-2">
                                    <a
                                        class="inline-flex items-center gap-1 text-[11px] font-bold text-primary hover:underline"
                                        :href="pieceViewUrl(detailDossier.id, detailLotFifPiece.type)"
                                        target="_blank"
                                        rel="noopener"
                                    >
                                        <span class="material-symbols-outlined text-[14px]">visibility</span> Voir la FIF
                                    </a>
                                    <a
                                        class="inline-flex items-center gap-1 text-[11px] font-bold text-primary hover:underline"
                                        :href="pieceDownloadUrl(detailDossier.id, detailLotFifPiece.type)"
                                    >
                                        <span class="material-symbols-outlined text-[14px]">download</span> Télécharger
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div
                            v-else-if="validationUi.fifMissing"
                            class="bg-error/5 border border-error/20 rounded-lg p-3 flex gap-2"
                        >
                            <span class="material-symbols-outlined text-error text-[18px] shrink-0">warning</span>
                            <p class="text-xs text-on-surface-variant">
                                <strong class="text-error">FIF signée absente.</strong>
                                Ce dossier a été soumis sans scan de FIF — demandez un complément à l'assuré avant d'instruire ou de valider.
                            </p>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                            <div class="bg-white rounded-lg p-3 border border-outline-variant"><p class="text-[10px] text-on-surface-variant uppercase">Membre (bénéficiaire)</p><p class="text-xs font-bold">{{ detailDossier.beneficiaire }}</p></div>
                            <div class="bg-white rounded-lg p-3 border border-outline-variant"><p class="text-[10px] text-on-surface-variant uppercase">Assuré principal</p><p class="text-xs font-bold">{{ detailFamily?.assureNom }}</p></div>
                            <div class="bg-white rounded-lg p-3 border border-outline-variant"><p class="text-[10px] text-on-surface-variant uppercase">Lien de parenté</p><p class="text-xs font-bold">{{ detailDossier.lien }}</p></div>
                            <div class="bg-white rounded-lg p-3 border border-outline-variant"><p class="text-[10px] text-on-surface-variant uppercase">Soumis le</p><p class="text-xs font-bold">{{ formatDateIso(detailDossier.dateSoumission) }}</p></div>
                            <div class="bg-white rounded-lg p-3 border border-outline-variant"><p class="text-[10px] text-on-surface-variant uppercase">Gestionnaire affecté</p><p class="text-xs font-bold">{{ detailDossier.gestionnaire || 'Non affecté' }}</p></div>
                            <div class="bg-white rounded-lg p-3 border border-outline-variant"><p class="text-[10px] text-on-surface-variant uppercase">Affecté le</p><p class="text-xs font-bold">{{ detailDossier.affecteLe || '—' }}</p></div>
                            <div class="bg-white rounded-lg p-3 border border-outline-variant sm:col-span-2">
                                <p class="text-[10px] text-on-surface-variant uppercase">Statut de cette demande</p>
                                <div class="mt-1">
                                    <span class="px-2.5 py-1 rounded-full text-[11px] font-bold whitespace-nowrap" :class="STATUS_STYLES[detailDossier.statut] || ''">{{ detailDossier.statut }}</span>
                                </div>
                            </div>
                        </div>

                        <div>
                            <h3 class="text-xs font-bold uppercase tracking-wide text-on-surface-variant mb-2 flex items-center gap-2">
                                <span class="material-symbols-outlined text-[16px] text-primary">badge</span> État civil du bénéficiaire
                            </h3>
                            <div class="grid grid-cols-2 gap-2">
                                <div v-for="([label, value], i) in beneficiaireInfo(detailDossier)" :key="i" class="bg-white rounded-lg p-3 border border-outline-variant">
                                    <p class="text-[10px] text-on-surface-variant uppercase">{{ label }}</p>
                                    <p class="text-xs font-bold break-words">{{ value }}</p>
                                </div>
                                <p v-if="!beneficiaireInfo(detailDossier).length" class="text-xs text-on-surface-variant italic col-span-2">Informations complémentaires non renseignées.</p>
                            </div>
                        </div>

                        <div>
                            <h3 class="text-xs font-bold uppercase tracking-wide text-on-surface-variant mb-2 flex items-center gap-2">
                                <span class="material-symbols-outlined text-[16px] text-primary">folder</span> Pièces justificatives
                            </h3>
                            <div class="bg-surface-container-low rounded-lg px-3 divide-y divide-outline-variant">
                                <div v-for="p in detailDossier.pieces" :key="p.type + (p.key || '')" class="flex items-center justify-between py-2.5 gap-2">
                                    <span class="text-xs flex items-center gap-2 min-w-0">
                                        <a
                                            v-if="p.hasFile && isImagePiece(p)"
                                            :href="pieceViewUrl(detailDossier.id, p.type)"
                                            target="_blank"
                                            rel="noopener"
                                            class="shrink-0"
                                            title="Agrandir l'image"
                                        >
                                            <img :src="pieceViewUrl(detailDossier.id, p.type)" alt="" loading="lazy" class="w-14 h-14 object-cover rounded-lg border border-outline-variant bg-white" />
                                        </a>
                                        <span v-else class="material-symbols-outlined text-[16px] shrink-0" :class="p.lot ? 'text-primary' : 'text-on-surface-variant'">description</span>
                                        <span class="break-words">
                                            {{ p.type }}
                                            <span v-if="p.lot" class="text-[10px] text-primary font-semibold ml-1">(lot familial)</span>
                                            <span v-if="p.filename" class="block text-[10px] text-on-surface-variant font-normal">{{ p.filename }}</span>
                                        </span>
                                    </span>
                                    <div class="flex items-center gap-1.5 shrink-0">
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold whitespace-nowrap" :class="PIECE_STYLES[p.statut] || ''">{{ p.statut }}</span>
                                        <template v-if="p.hasFile">
                                            <a
                                                v-if="!isImagePiece(p)"
                                                class="w-9 h-9 flex items-center justify-center rounded-lg hover:bg-white text-primary"
                                                :href="pieceViewUrl(detailDossier.id, p.type)"
                                                target="_blank"
                                                rel="noopener"
                                                title="Visualiser"
                                            >
                                                <span class="material-symbols-outlined text-[18px]">visibility</span>
                                            </a>
                                            <a
                                                class="w-9 h-9 flex items-center justify-center rounded-lg hover:bg-white text-on-surface-variant"
                                                :href="pieceDownloadUrl(detailDossier.id, p.type)"
                                                title="Télécharger"
                                            >
                                                <span class="material-symbols-outlined text-[18px]">download</span>
                                            </a>
                                        </template>
                                        <span v-else class="text-[10px] text-on-surface-variant italic px-1">Fichier non stocké</span>
                                    </div>
                                </div>
                                <p v-if="!detailDossier.pieces?.length" class="text-xs text-on-surface-variant italic py-3">Aucune pièce téléversée.</p>
                            </div>
                        </div>

                        <div>
                            <h3 class="text-xs font-bold uppercase tracking-wide text-on-surface-variant mb-2 flex items-center gap-2">
                                <span class="material-symbols-outlined text-[16px] text-primary">forum</span> Fil de discussion avec l'assuré
                            </h3>
                            <div class="bg-surface-container-low rounded-lg p-3 space-y-2 mb-2">
                                <div v-for="(m, i) in detailDossier.messages || []" :key="i" class="flex" :class="m.auteur === 'gestionnaire' ? 'justify-end' : 'justify-start'">
                                    <div class="max-w-[80%] rounded-xl px-3 py-2" :class="m.auteur === 'gestionnaire' ? 'bg-primary text-on-primary' : 'bg-surface-container-high text-on-surface'">
                                        <p class="text-xs">{{ m.texte }}</p>
                                        <p class="text-[10px] opacity-70 mt-1">{{ m.date }}</p>
                                    </div>
                                </div>
                                <p v-if="!detailDossier.messages?.length" class="text-xs text-on-surface-variant italic">Aucun message échangé.</p>
                            </div>
                            <div class="flex gap-2">
                                <input v-model="messageText" class="flex-1 min-w-0 px-3 py-3 sm:py-2 text-sm sm:text-xs border border-outline-variant rounded-lg bg-white" placeholder="Message à l'assuré…" type="text" @keyup.enter="sendMessage" />
                                <button type="button" class="bg-primary text-on-primary w-11 h-11 sm:px-3 sm:w-auto rounded-lg shrink-0 flex items-center justify-center" aria-label="Envoyer" @click="sendMessage">
                                    <span class="material-symbols-outlined text-[20px]">send</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div v-if="canActOnDossier" class="sticky bottom-0 bg-white border-t border-outline-variant p-3 sm:p-4 flex flex-wrap gap-2 shadow-[0_-4px_12px_rgba(0,0,0,0.06)]">
                        <button
                            v-if="validationUi.show"
                            type="button"
                            class="detail-action-btn detail-action-btn--wide px-4 py-3 rounded-lg bg-secondary text-on-secondary text-sm font-bold flex items-center gap-2"
                            @click="openAction('valider')"
                        >
                            <span class="material-symbols-outlined text-[18px]">check_circle</span> {{ validationUi.label }}
                        </button>
                        <span v-else-if="detailDossier.statut === 'En attente supervision'" class="detail-action-btn detail-action-btn--wide px-4 py-3 rounded-lg bg-primary-fixed/30 text-on-surface-variant text-sm font-bold flex items-center justify-center gap-2">
                            <span class="material-symbols-outlined text-[18px]">hourglass_top</span> En attente supervision
                        </span>
                        <button
                            v-if="detailDossier.statut === 'Validé'"
                            type="button"
                            class="detail-action-btn px-4 py-3 rounded-lg bg-on-background text-white text-sm font-bold flex items-center gap-2"
                            @click="openAction('retrait')"
                        >
                            <span class="material-symbols-outlined text-[18px]">person_remove</span> Retirer le membre
                        </button>
                        <button type="button" class="detail-action-btn px-4 py-3 rounded-lg bg-error text-on-error text-sm font-bold flex items-center gap-2" @click="openAction('refuser')">
                            <span class="material-symbols-outlined text-[18px]">cancel</span> Refuser
                        </button>
                        <button type="button" class="detail-action-btn px-4 py-3 rounded-lg bg-tertiary text-on-tertiary text-sm font-bold flex items-center gap-2" @click="openAction('complement')">
                            <span class="material-symbols-outlined text-[18px]">description</span> Complément
                        </button>
                        <button type="button" class="detail-action-btn px-4 py-3 rounded-lg border border-outline text-on-surface text-sm font-bold flex items-center gap-2" @click="openAction('attente')">
                            <span class="material-symbols-outlined text-[18px]">pause_circle</span> En attente
                        </button>
                    </div>
                </template>
            </div>
        </div>

        <!-- Modale profil assuré -->
        <div v-if="showAssureProfile && detailFamily?.assureProfile" class="fixed inset-0 bg-black/50 z-[65] flex items-center justify-center p-4" @click.self="closeAssureProfile">
            <div class="bg-white rounded-xl w-full max-w-lg max-h-[90vh] overflow-y-auto shadow-2xl">
                <div class="sticky top-0 bg-white border-b border-outline-variant px-5 py-4 flex items-start justify-between gap-3 z-10">
                    <div class="min-w-0">
                        <p class="text-[10px] uppercase tracking-wide text-primary font-bold mb-0.5">Profil assuré</p>
                        <h3 class="font-title-lg text-title-lg leading-tight">{{ detailFamily.assureProfile.fullName || detailFamily.assureNom }}</h3>
                        <p class="text-xs text-on-surface-variant mt-1">{{ detailFamily.assureProfile.matricule }} · {{ detailFamily.assureProfile.numeroCama || '—' }}</p>
                    </div>
                    <button type="button" class="w-9 h-9 rounded-full hover:bg-surface-container-low flex items-center justify-center shrink-0" @click="closeAssureProfile">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <div class="p-5 space-y-5">
                    <div class="grid grid-cols-2 gap-2">
                        <div v-for="([label, value], i) in assureProfileRows(detailFamily.assureProfile)" :key="i" class="bg-surface-container-low rounded-lg p-3 border border-outline-variant">
                            <p class="text-[10px] text-on-surface-variant uppercase">{{ label }}</p>
                            <p class="text-xs font-bold break-words">{{ value }}</p>
                        </div>
                    </div>
                    <div v-if="detailFamily.assureProfile.documentsIdentite?.length">
                        <h4 class="text-xs font-bold uppercase tracking-wide text-on-surface-variant mb-2 flex items-center gap-2">
                            <span class="material-symbols-outlined text-[16px] text-primary">badge</span>
                            Documents d'identité (inscription)
                        </h4>
                        <div class="bg-surface-container-low rounded-lg px-3 divide-y divide-outline-variant">
                            <div v-for="doc in detailFamily.assureProfile.documentsIdentite" :key="doc.key || doc.label" class="flex items-center justify-between py-2.5 gap-2">
                                <span class="text-xs min-w-0 break-words">
                                    {{ doc.label || 'Document' }}
                                    <span v-if="doc.filename" class="block text-[10px] text-on-surface-variant font-normal">{{ doc.filename }}</span>
                                </span>
                                <div v-if="doc.key" class="flex items-center gap-1 shrink-0">
                                    <a
                                        class="w-9 h-9 flex items-center justify-center rounded-lg hover:bg-white text-primary"
                                        :href="identityDocumentUrl(detailFamily.assureProfile.id, doc.key)"
                                        target="_blank"
                                        rel="noopener"
                                        title="Visualiser"
                                    >
                                        <span class="material-symbols-outlined text-[18px]">visibility</span>
                                    </a>
                                    <a
                                        class="w-9 h-9 flex items-center justify-center rounded-lg hover:bg-white text-on-surface-variant"
                                        :href="identityDocumentUrl(detailFamily.assureProfile.id, doc.key)"
                                        title="Télécharger"
                                    >
                                        <span class="material-symbols-outlined text-[18px]">download</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modale d'action -->
        <div v-if="actionModal" class="fixed inset-0 bg-black/50 z-[60] flex items-center justify-center p-4" @click.self="actionModal = null">
            <div class="bg-white rounded-xl w-full max-w-md max-h-[90vh] overflow-y-auto p-5 sm:p-6">
                <template v-if="actionModal === 'valider'">
                    <h3 class="font-title-lg text-title-lg mb-2">Confirmer la validation</h3>
                    <p class="text-xs text-on-surface-variant mb-4">{{ validationUi.sub }}</p>
                    <div class="flex justify-end gap-2">
                        <button type="button" class="px-4 py-2 rounded-lg border border-outline text-xs" @click="actionModal = null">Annuler</button>
                        <button type="button" class="px-4 py-2 rounded-lg bg-secondary text-on-secondary text-xs font-bold" @click="confirmAction">Valider le dossier</button>
                    </div>
                </template>

                <template v-else-if="actionModal === 'refuser'">
                    <h3 class="font-title-lg text-title-lg mb-2">Refuser le dossier</h3>
                    <label class="text-[10px] font-bold uppercase text-on-surface-variant">Motif pré-défini</label>
                    <select v-model="rejectMotifPreset" class="w-full mt-1 mb-3 px-3 py-2 text-xs border border-outline-variant rounded-lg" @change="applyRejectPreset">
                        <option value="">— Choisir un motif —</option>
                        <option v-for="motif in motifsRefus" :key="motif" :value="motif">{{ motif }}</option>
                        <option value="__custom__">Autre (saisie libre)</option>
                    </select>
                    <label class="text-[10px] font-bold uppercase text-on-surface-variant">Motif du refus</label>
                    <textarea v-model="rejectMotif" class="w-full mt-1 px-3 py-2 text-xs border border-outline-variant rounded-lg" rows="4" placeholder="Motif obligatoire…" />
                    <div class="flex justify-end gap-2 mt-4">
                        <button type="button" class="px-4 py-2 rounded-lg border border-outline text-xs" @click="actionModal = null">Annuler</button>
                        <button type="button" class="px-4 py-2 rounded-lg bg-error text-on-error text-xs font-bold" :disabled="!rejectMotif.trim()" @click="confirmAction">Confirmer le refus</button>
                    </div>
                </template>

                <template v-else-if="actionModal === 'retrait'">
                    <h3 class="font-title-lg text-title-lg mb-2">Retirer le membre</h3>
                    <p class="text-xs text-on-surface-variant mb-3">Ce membre passera en « Retiré » et ne sera plus couvert comme ayant droit. L'assuré sera notifié.</p>
                    <div class="space-y-2">
                        <label class="flex items-start gap-2.5 p-3 rounded-lg border cursor-pointer" :class="retraitType === 'devenu_militaire' ? 'border-primary bg-primary/5' : 'border-outline-variant'">
                            <input v-model="retraitType" type="radio" value="devenu_militaire" class="mt-0.5" />
                            <span>
                                <span class="block text-xs font-bold text-on-surface">Devenu militaire</span>
                                <span class="block text-[11px] text-on-surface-variant">S'est engagé dans l'armée : devient assuré à part entière, ne peut plus être ayant droit.</span>
                            </span>
                        </label>
                        <div v-if="retraitType === 'devenu_militaire'" class="pl-8">
                            <input v-model="retraitMatricule" type="text" class="w-full px-3 py-2 text-xs border border-outline-variant rounded-lg" placeholder="Nouveau matricule (facultatif)" />
                        </div>
                        <label class="flex items-start gap-2.5 p-3 rounded-lg border cursor-pointer" :class="retraitType === 'autre' ? 'border-primary bg-primary/5' : 'border-outline-variant'">
                            <input v-model="retraitType" type="radio" value="autre" class="mt-0.5" />
                            <span class="block text-xs font-bold text-on-surface">Autre motif</span>
                        </label>
                        <div v-if="retraitType === 'autre'" class="pl-8">
                            <input v-model="retraitAutre" type="text" class="w-full px-3 py-2 text-xs border border-outline-variant rounded-lg" placeholder="Précisez le motif" />
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 mt-4">
                        <button type="button" class="px-4 py-2 rounded-lg border border-outline text-xs" @click="actionModal = null">Annuler</button>
                        <button type="button" class="px-4 py-2 rounded-lg bg-on-background text-white text-xs font-bold" :disabled="retraitType === 'autre' && !retraitAutre.trim()" @click="confirmAction">Confirmer le retrait</button>
                    </div>
                </template>

                <template v-else-if="actionModal === 'complement'">
                    <h3 class="font-title-lg text-title-lg mb-2">Demander un complément</h3>
                    <p class="text-xs text-on-surface-variant mb-3">
                        Cochez les pièces à redemander <span class="italic">(optionnel)</span> et/ou rédigez un message à l'assuré.
                    </p>
                    <div class="space-y-2 mb-3 max-h-40 overflow-y-auto border border-outline-variant rounded-lg p-2">
                        <label v-for="piece in piecesRequetables" :key="piece" class="flex items-center gap-2 text-xs cursor-pointer">
                            <input type="checkbox" :checked="complementPieces.includes(piece)" @change="toggleComplementPiece(piece)" />
                            {{ piece }}
                        </label>
                    </div>
                    <label class="text-[10px] font-bold uppercase text-on-surface-variant">Message à l'assuré</label>
                    <textarea
                        v-model="complementMessage"
                        class="w-full mt-1 px-3 py-2 text-xs border border-outline-variant rounded-lg"
                        rows="3"
                        placeholder="Ex. : merci de téléverser la FIF signée par le chef de corps et le GRH…"
                    />
                    <p class="text-[11px] text-on-surface-variant mt-2 italic">Au moins une pièce cochée ou un message est requis.</p>
                    <div class="flex justify-end gap-2 mt-4">
                        <button type="button" class="px-4 py-2 rounded-lg border border-outline text-xs" @click="actionModal = null">Annuler</button>
                        <button type="button" class="px-4 py-2 rounded-lg bg-tertiary text-on-tertiary text-xs font-bold" :disabled="!canSendComplement" @click="confirmAction">Envoyer la demande</button>
                    </div>
                </template>

                <template v-else-if="actionModal === 'attente'">
                    <h3 class="font-title-lg text-title-lg mb-2">Mettre en attente</h3>
                    <p class="text-xs text-on-surface-variant mb-4">Le dossier passera en attente de supervision.</p>
                    <div class="flex justify-end gap-2">
                        <button type="button" class="px-4 py-2 rounded-lg border border-outline text-xs" @click="actionModal = null">Annuler</button>
                        <button type="button" class="px-4 py-2 rounded-lg bg-primary text-on-primary text-xs font-bold" @click="confirmAction">Confirmer</button>
                    </div>
                </template>
            </div>
        </div>

        <div v-if="toast" class="fixed bottom-6 right-6 z-[70]">
            <div class="bg-on-background text-white px-5 py-3 rounded-lg shadow-xl flex items-center gap-2 font-label-md text-label-md">
                <span class="material-symbols-outlined">check_circle</span> {{ toast }}
            </div>
        </div>
    </AdminLayout>
</template>

<style scoped>
details.assure-accordion > summary .accordion-chevron {
    transition: transform 0.2s ease;
}
details.assure-accordion[open] > summary .accordion-chevron {
    transform: rotate(180deg);
}
summary::-webkit-details-marker {
    display: none;
}
summary {
    list-style: none;
}
@media (max-width: 767px) {
    .detail-action-btn {
        width: 100%;
        min-height: 44px;
        justify-content: center;
    }
    #detail-actions {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px;
    }
    .detail-action-btn--wide {
        grid-column: 1 / -1;
    }
}
</style>
