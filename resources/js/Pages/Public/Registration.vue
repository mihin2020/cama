<script setup>
import CamaLoadingButton from '@/Components/CamaLoadingButton.vue';
import CamaPhoneInput from '@/Components/CamaPhoneInput.vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { formatPhonesDisplay } from '@/Utils/camaPhone';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, h, nextTick, onMounted, reactive, ref, watch } from 'vue';

const props = defineProps({
    orgStructure: Object,
    inscriptionDocuments: { type: Array, default: () => [] },
    inscriptionFields: { type: Object, default: () => ({}) },
});

// --- Champs paramétrables (Admin › Paramètres › Champs d'inscription) ---
const FIELD_LABELS = {
    matricule: 'Matricule militaire', sexe: 'Sexe', grade: 'Grade', categorie: 'Catégorie', numero_informatique: 'N° informatique',
    numero_cim: 'N° CIM', numero_cama: 'N° Carte CAMA', numero_iup: 'N° IUP',
    armee: 'Armée', region: 'Région', corps: 'Corps', service: 'Service', section: 'Section', sous_section: 'Sous-section',
    telephone: 'Téléphone', personne_a_prevenir: 'Personne à prévenir', tel_personne_a_prevenir: 'Téléphone (personne à prévenir)',
};

function isOn(key) {
    return props.inscriptionFields[key]?.actif ?? true;
}

function isReq(key) {
    if (!isOn(key)) return false;
    // Un niveau de rattachement sans option disponible ne peut pas être exigé.
    if (key === 'corps' && !corpsOptions.value.length) return false;
    if (key === 'service' && !serviceOptions.value.length) return false;
    if (key === 'section' && !sectionOptions.value.length) return false;
    if (key === 'sous_section' && !sousSectionOptions.value.length) return false;
    return props.inscriptionFields[key]?.required ?? false;
}

function star(key) {
    return isReq(key) ? ' *' : '';
}

const FieldError = (p) => (p.msg ? h('p', { class: 'text-error text-xs mt-1' }, p.msg) : null);
FieldError.props = ['msg'];

const MAX_DOC_SIZE = 5 * 1024 * 1024;
const ACCEPTED_DOC_TYPES = ['application/pdf', 'image/jpeg', 'image/png'];

const documentFiles = reactive({});
const documentErrors = reactive({});

const STEPS = [
    { id: 'identite', title: 'Identité', short: 'Identité', fields: ['nom', 'prenom', 'sexe', 'matricule'] },
    { id: 'administratif', title: 'Situation administrative', short: 'Grade & catégorie', fields: ['grade', 'categorie', 'numero_informatique', 'numero_cim', 'numero_cama', 'numero_iup'] },
    { id: 'rattachement', title: 'Rattachement', short: 'Structure militaire', fields: ['armee', 'region', 'corps', 'service', 'section', 'sous_section'] },
    { id: 'coordonnees', title: 'Coordonnées', short: 'Contact', fields: ['telephone', 'email', 'personne_a_prevenir', 'tel_personne_a_prevenir'] },
    { id: 'recap', title: 'Récapitulatif', short: 'Vérification', fields: ['cgu'] },
    { id: 'securite', title: 'Sécurité', short: 'Mot de passe', fields: ['password', 'password_confirmation'] },
];

// Une étape dont tous les champs paramétrables sont masqués n'est pas affichée.
const visibleSteps = computed(() => STEPS.filter((step) => {
    if (step.id === 'administratif' || step.id === 'rattachement') return step.fields.some(isOn);
    return true;
}));

const currentStep = ref(1);
const currentStepId = computed(() => visibleSteps.value[currentStep.value - 1]?.id);
const stepLabel = computed(() => {
    const step = visibleSteps.value[currentStep.value - 1];
    return `Étape ${currentStep.value} sur ${visibleSteps.value.length} — ${step.short}`;
});
const banner = ref({ type: '', message: '' });

const form = useForm({
    nom: '', prenom: '', sexe: props.inscriptionFields.sexe?.required === false ? '' : 'Masculin', matricule: '',
    grade: '', categorie: '', numero_informatique: '', numero_cim: '', numero_cama: '', numero_iup: '',
    armee: '', region: '', corps: '', service: '', section: '', sous_section: '',
    telephone: '', email: '', personne_a_prevenir: '', tel_personne_a_prevenir: '',
    documents: {},
    password: '', password_confirmation: '', cgu: false,
});

const hasDocuments = computed(() => props.inscriptionDocuments.length > 0);

function handleDocument(key, event) {
    documentErrors[key] = '';
    const file = event.target.files?.[0];
    if (!file) return;
    if (!ACCEPTED_DOC_TYPES.includes(file.type)) {
        documentErrors[key] = 'Format non accepté (PDF, JPEG, PNG).';
        event.target.value = '';
        return;
    }
    if (file.size > MAX_DOC_SIZE) {
        documentErrors[key] = 'Fichier trop volumineux (5 Mo max).';
        event.target.value = '';
        return;
    }
    documentFiles[key] = file;
    form.documents[key] = file;
}

function removeDocument(key) {
    delete documentFiles[key];
    delete form.documents[key];
}

function missingDocuments() {
    return props.inscriptionDocuments.filter((doc) => !documentFiles[doc.key]);
}

const corpsOptions = computed(() => {
    const region = props.orgStructure.regions?.find((r) => r.libelle === form.region);
    return region?.corps?.map((c) => c.libelle) ?? [];
});

const serviceOptions = computed(() => {
    const region = props.orgStructure.regions?.find((r) => r.libelle === form.region);
    const corps = region?.corps?.find((c) => c.libelle === form.corps);
    return corps?.services?.map((s) => s.libelle) ?? [];
});

const sectionOptions = computed(() => {
    const region = props.orgStructure.regions?.find((r) => r.libelle === form.region);
    const corps = region?.corps?.find((c) => c.libelle === form.corps);
    const service = corps?.services?.find((s) => s.libelle === form.service);
    return service?.sections?.map((s) => s.libelle) ?? [];
});

const sousSectionOptions = computed(() => {
    const region = props.orgStructure.regions?.find((r) => r.libelle === form.region);
    const corps = region?.corps?.find((c) => c.libelle === form.corps);
    const service = corps?.services?.find((s) => s.libelle === form.service);
    const section = service?.sections?.find((s) => s.libelle === form.section);
    return section?.sous_sections?.map((s) => s.libelle) ?? [];
});

watch(() => form.region, () => { if (restoringDraft) return; form.corps = ''; form.service = ''; form.section = ''; form.sous_section = ''; });
watch(() => form.corps, () => { if (restoringDraft) return; form.service = ''; form.section = ''; form.sous_section = ''; });
watch(() => form.service, () => { if (restoringDraft) return; form.section = ''; form.sous_section = ''; });
watch(() => form.section, () => { if (restoringDraft) return; form.sous_section = ''; });

watch(() => props.orgStructure, (org) => {
    if (form.grade && !(org.grades ?? []).includes(form.grade)) form.grade = '';
    if (form.categorie && !(org.categories ?? []).includes(form.categorie)) form.categorie = '';
    if (form.armee && !(org.armees ?? []).includes(form.armee)) form.armee = '';
}, { deep: true });

const pwRules = computed(() => ({
    length: form.password.length >= 12,
    upper: /[A-Z]/.test(form.password),
    digit: /[0-9]/.test(form.password),
    special: /[^A-Za-z0-9]/.test(form.password),
}));

const emailLooksValid = computed(() => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email));

const phonesDisplay = computed(() => formatPhonesDisplay(form.telephone));
const telPersonneDisplay = computed(() => formatPhonesDisplay(form.tel_personne_a_prevenir));

const recapBlocks = computed(() => {
    const row = (key, label, value) => ({ key, label: label ?? FIELD_LABELS[key], value: value ?? form[key] });
    const blocks = [
        { title: 'Administratif', rows: [
            row('grade'), row('categorie'), row('numero_informatique'), row('numero_cim'), row('numero_cama'), row('numero_iup'),
        ].filter((r) => isOn(r.key)) },
        { title: 'Rattachement', rows: [
            row('armee'), row('region'), row('corps'), row('service'), row('section'), row('sous_section'),
        ].filter((r) => isOn(r.key)) },
        { title: 'Contact', rows: [
            row('telephone', 'Téléphones', phonesDisplay.value),
            { key: 'email', label: 'E-mail', value: form.email },
            row('personne_a_prevenir'),
            row('tel_personne_a_prevenir', 'Tél. personne à prévenir', telPersonneDisplay.value),
        ].filter((r) => r.key === 'email' || isOn(r.key)) },
    ];
    return blocks.filter((b) => b.rows.length);
});

function hasPhoneValue(raw) {
    return String(raw || '').split('|').some((part) => part.trim());
}

function showBanner(type, message) {
    banner.value = { type, message };
}

function validateStep(n) {
    banner.value = { type: '', message: '' };
    const id = visibleSteps.value[n - 1]?.id;
    if (id === 'identite' && (!form.nom || !form.prenom || (isReq('matricule') && !form.matricule.trim()) || (isReq('sexe') && !form.sexe))) {
        showBanner('error', 'Veuillez compléter les champs obligatoires de cette étape.');
        return false;
    }
    if (id === 'administratif' || id === 'rattachement') {
        const missing = STEPS.find((s) => s.id === id).fields.filter((key) => isReq(key) && !String(form[key] ?? '').trim());
        if (missing.length) {
            const labels = missing.map((key) => FIELD_LABELS[key]);
            showBanner('error', labels.length === 1
                ? `Le champ « ${labels[0]} » est obligatoire.`
                : `Champs obligatoires manquants : ${labels.join(', ')}.`);
            return false;
        }
    }
    if (id === 'coordonnees') {
        if (isReq('telephone') && !hasPhoneValue(form.telephone)) {
            showBanner('error', 'Au moins un numéro de téléphone est obligatoire.');
            return false;
        }
        if (!form.email) {
            showBanner('error', 'L\'e-mail est obligatoire.');
            return false;
        }
        if (isReq('personne_a_prevenir') && !form.personne_a_prevenir.trim()) {
            showBanner('error', 'La personne à prévenir est obligatoire.');
            return false;
        }
        if (isReq('tel_personne_a_prevenir') && !hasPhoneValue(form.tel_personne_a_prevenir)) {
            showBanner('error', 'Le téléphone de la personne à prévenir est obligatoire.');
            return false;
        }
    }
    if (id === 'recap' && !form.cgu) {
        showBanner('error', 'Veuillez accepter les CGU.');
        return false;
    }
    return true;
}

function nextStep() {
    if (validateStep(currentStep.value)) currentStep.value++;
}

// Après un refus du serveur, revenir à la première étape contenant une erreur.
function goToFirstError(errors) {
    const keys = Object.keys(errors);
    const index = visibleSteps.value.findIndex((step) => keys.some((k) => step.fields.includes(k)));
    if (index >= 0) currentStep.value = index + 1;
    showBanner('error', 'Certaines informations sont à corriger : ' + Object.values(errors)[0]);
}

function prevStep() {
    if (currentStep.value > 1) currentStep.value--;
}

// --- Brouillon auto-sauvegardé (le temps de la session du navigateur) ---
// Les infos saisies sont conservées si l'on recharge / revient, tant que la
// session (l'onglet) reste ouverte. Elles sont effacées à la validation du
// formulaire ou à la fermeture de la session. Les fichiers (pièces jointes)
// ne peuvent pas être conservés et sont à re-sélectionner.
const DRAFT_KEY = 'cama_inscription_draft_v1';
let restoringDraft = false;
let draftReady = false;

function saveDraft() {
    if (!draftReady) return;
    try {
        const snapshot = { _step: currentStep.value };
        Object.entries(form.data()).forEach(([key, value]) => {
            if (key === 'documents') return;
            snapshot[key] = value;
        });
        sessionStorage.setItem(DRAFT_KEY, JSON.stringify(snapshot));
    } catch (e) {
        // sessionStorage indisponible (navigation privée…) : on ignore
    }
}

function clearDraft() {
    try {
        sessionStorage.removeItem(DRAFT_KEY);
    } catch (e) {
        // on ignore
    }
}

async function restoreDraft() {
    let data = null;
    try {
        const raw = sessionStorage.getItem(DRAFT_KEY);
        data = raw ? JSON.parse(raw) : null;
    } catch (e) {
        data = null;
    }

    if (data && typeof data === 'object') {
        restoringDraft = true;
        Object.keys(form.data()).forEach((key) => {
            if (key === 'documents') return;
            if (key in data) form[key] = data[key];
        });
        await nextTick();
        restoringDraft = false;

        if (Number.isInteger(data._step) && data._step >= 1 && data._step <= visibleSteps.value.length) {
            currentStep.value = data._step;
        }
        showBanner('info', 'Vos informations précédentes ont été restaurées. Les pièces jointes sont à re-sélectionner.');
    }

    draftReady = true;
    watch(form, saveDraft, { deep: true });
    watch(currentStep, saveDraft);
}

function resetForm() {
    clearDraft();
    form.reset();
    Object.keys(documentFiles).forEach((key) => delete documentFiles[key]);
    Object.keys(documentErrors).forEach((key) => delete documentErrors[key]);
    currentStep.value = 1;
    banner.value = { type: '', message: '' };
}

onMounted(() => {
    restoreDraft();
});

function submit() {
    if (!validateStep(visibleSteps.value.length)) return;
    if (hasDocuments.value) {
        const missing = missingDocuments();
        if (missing.length) {
            showBanner('error', `Pièce obligatoire manquante : ${missing[0].titre}.`);
            return;
        }
    }
    form.post(route('registration.store'), {
        forceFormData: true,
        onSuccess: () => clearDraft(),
        onError: (errors) => goToFirstError(errors),
    });
}
</script>

<template>
    <Head title="Créer un compte assuré" />

    <PublicLayout>
        <main>
            <section class="relative py-12 md:py-14 overflow-hidden bg-on-background text-white">
                <div class="shield-pattern absolute inset-0 opacity-20" />
                <div class="relative z-10 max-w-3xl mx-auto px-4 md:px-margin-desktop text-center">
                    <Link class="inline-flex items-center gap-2 text-white/80 hover:text-white text-sm mb-4" :href="route('assure.login')">
                        <span class="material-symbols-outlined text-[18px]">arrow_back</span> Retour à la connexion
                    </Link>
                    <h1 class="font-headline-lg text-headline-lg md:text-3xl font-bold mb-2">Créer mon compte assuré</h1>
                    <p class="text-white/85 text-sm md:text-base max-w-xl mx-auto">
                        Inscription réservée aux militaires en fonction. Procédez étape par étape — votre numéro CAMA vous sera attribué après validation.
                    </p>
                </div>
            </section>

            <section class="py-8 md:py-12 bg-surface-container-low">
            <div class="max-w-2xl mx-auto px-4 md:px-8">
                <div class="bg-white rounded-2xl border border-outline-variant shadow-sm overflow-hidden">
                    <div class="px-6 pt-6 pb-4 border-b border-outline-variant bg-surface-container-low/50">
                        <div class="flex items-center justify-between gap-1 mb-3">
                            <span
                                v-for="(_, i) in visibleSteps"
                                :key="i"
                                class="wizard-progress-dot"
                                :class="{ 'is-done': i + 1 < currentStep, 'is-active': i + 1 === currentStep }"
                            />
                        </div>
                        <div class="flex items-center justify-between gap-2">
                            <div>
                                <p class="text-xs font-bold text-primary uppercase tracking-wide">{{ stepLabel }}</p>
                                <p class="text-sm text-on-surface-variant mt-0.5">{{ visibleSteps[currentStep - 1].title }}</p>
                            </div>
                            <button
                                type="button"
                                class="shrink-0 inline-flex items-center gap-1 text-xs font-semibold text-on-surface-variant hover:text-red-600 transition-colors"
                                title="Effacer les informations saisies et recommencer"
                                @click="resetForm"
                            >
                                <span class="material-symbols-outlined text-[16px]">restart_alt</span>
                                Recommencer
                            </button>
                        </div>
                    </div>

                    <div class="p-6 md:p-8">
                        <div
                            v-if="banner.message"
                            class="flex items-start gap-2 p-3.5 rounded-lg text-sm mb-5"
                            :class="banner.type === 'error' ? 'bg-red-50 text-red-900 border border-red-200' : 'bg-amber-50 text-amber-900'"
                        >
                            <span class="material-symbols-outlined text-[20px] shrink-0">{{ banner.type === 'error' ? 'error' : 'info' }}</span>
                            <span>{{ banner.message }}</span>
                        </div>

                        <form @submit.prevent="submit">
                            <div v-show="currentStepId === 'identite'" class="wizard-step is-active space-y-4">
                                <h2 class="text-lg font-bold flex items-center gap-2"><span class="material-symbols-outlined text-primary">badge</span> Qui êtes-vous ?</h2>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div><label class="reg-label">Nom *</label><input v-model="form.nom" class="reg-input" required /><FieldError :msg="form.errors.nom" /></div>
                                    <div><label class="reg-label">Prénom(s) *</label><input v-model="form.prenom" class="reg-input" required /><FieldError :msg="form.errors.prenom" /></div>
                                    <div v-if="isOn('sexe')"><label class="reg-label">Sexe{{ star('sexe') }}</label><select v-model="form.sexe" class="reg-input"><option v-if="!isReq('sexe')" value="">— Non précisé —</option><option>Masculin</option><option>Féminin</option></select><FieldError :msg="form.errors.sexe" /></div>
                                    <div v-if="isOn('matricule')"><label class="reg-label">Matricule militaire{{ star('matricule') }}</label><input v-model="form.matricule" class="reg-input" :required="isReq('matricule')" /><FieldError :msg="form.errors.matricule" /></div>
                                </div>
                            </div>

                            <div v-show="currentStepId === 'administratif'" class="space-y-4">
                                <h2 class="text-lg font-bold flex items-center gap-2"><span class="material-symbols-outlined text-primary">military_tech</span> Situation administrative</h2>
                                <p class="text-sm text-on-surface-variant -mt-2">Grade, catégorie et numéros administratifs CAMA. Les champs marqués * sont obligatoires.</p>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div v-if="isOn('grade')"><label class="reg-label">Grade{{ star('grade') }}</label><select v-model="form.grade" class="reg-input" :required="isReq('grade')"><option :disabled="isReq('grade')" value="">— Grade —</option><option v-for="g in orgStructure.grades" :key="g">{{ g }}</option></select><FieldError :msg="form.errors.grade" /></div>
                                    <div v-if="isOn('categorie')"><label class="reg-label">Catégorie{{ star('categorie') }}</label><select v-model="form.categorie" class="reg-input" :required="isReq('categorie')"><option :disabled="isReq('categorie')" value="">— Catégorie —</option><option v-for="c in orgStructure.categories" :key="c">{{ c }}</option></select><FieldError :msg="form.errors.categorie" /></div>
                                    <div v-if="isOn('numero_informatique')"><label class="reg-label">N° informatique{{ star('numero_informatique') }}</label><input v-model="form.numero_informatique" class="reg-input" :required="isReq('numero_informatique')" /><FieldError :msg="form.errors.numero_informatique" /></div>
                                    <div v-if="isOn('numero_cim')"><label class="reg-label">N° CIM{{ star('numero_cim') }}</label><input v-model="form.numero_cim" class="reg-input" :required="isReq('numero_cim')" placeholder="Ex. CIM-104521" /><FieldError :msg="form.errors.numero_cim" /></div>
                                    <div v-if="isOn('numero_cama')"><label class="reg-label">N° Carte CAMA{{ star('numero_cama') }}</label><input v-model="form.numero_cama" class="reg-input" :required="isReq('numero_cama')" placeholder="Ex. CAMA-104521" /><FieldError :msg="form.errors.numero_cama" /></div>
                                    <div v-if="isOn('numero_iup')"><label class="reg-label">N° IUP{{ star('numero_iup') }}</label><input v-model="form.numero_iup" class="reg-input" :required="isReq('numero_iup')" placeholder="Identifiant Unique de la Personne" /><FieldError :msg="form.errors.numero_iup" /></div>
                                </div>
                            </div>

                            <div v-show="currentStepId === 'rattachement'" class="space-y-4">
                                <h2 class="text-lg font-bold flex items-center gap-2"><span class="material-symbols-outlined text-primary">account_tree</span> Structure de rattachement</h2>
                                <p class="text-sm text-on-surface-variant -mt-2">Sélectionnez votre unité dans la hiérarchie militaire configurée par la CAMA.</p>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div v-if="isOn('armee')"><label class="reg-label">Armée{{ star('armee') }}</label><select v-model="form.armee" class="reg-input" :required="isReq('armee')"><option :disabled="isReq('armee')" value="">— Armée —</option><option v-for="a in orgStructure.armees" :key="a">{{ a }}</option></select><FieldError :msg="form.errors.armee" /></div>
                                    <div v-if="isOn('region')"><label class="reg-label">Région{{ star('region') }}</label><select v-model="form.region" class="reg-input" :required="isReq('region')"><option :disabled="isReq('region')" value="">— Région —</option><option v-for="r in orgStructure.regions" :key="r.id">{{ r.libelle }}</option></select><FieldError :msg="form.errors.region" /></div>
                                    <div v-if="isOn('corps')"><label class="reg-label">Corps{{ star('corps') }}</label><select v-model="form.corps" class="reg-input" :disabled="!corpsOptions.length" :required="isReq('corps')"><option :disabled="isReq('corps')" value="">— Corps —</option><option v-for="c in corpsOptions" :key="c">{{ c }}</option></select><FieldError :msg="form.errors.corps" /></div>
                                    <div v-if="isOn('service')"><label class="reg-label">Service{{ star('service') }}</label><select v-model="form.service" class="reg-input" :disabled="!serviceOptions.length" :required="isReq('service')"><option :disabled="isReq('service')" value="">— Service —</option><option v-for="s in serviceOptions" :key="s">{{ s }}</option></select><FieldError :msg="form.errors.service" /></div>
                                    <div v-if="isOn('section')"><label class="reg-label">Section{{ star('section') }}</label><select v-model="form.section" class="reg-input" :disabled="!sectionOptions.length" :required="isReq('section')"><option :disabled="isReq('section')" value="">— Section —</option><option v-for="s in sectionOptions" :key="s">{{ s }}</option></select><FieldError :msg="form.errors.section" /></div>
                                    <div v-if="isOn('sous_section')"><label class="reg-label">Sous-section{{ star('sous_section') }}</label><select v-model="form.sous_section" class="reg-input" :disabled="!sousSectionOptions.length" :required="isReq('sous_section')"><option :disabled="isReq('sous_section')" value="">— Sous-section —</option><option v-for="s in sousSectionOptions" :key="s">{{ s }}</option></select><FieldError :msg="form.errors.sous_section" /></div>
                                </div>
                            </div>

                            <div v-show="currentStepId === 'coordonnees'" class="space-y-4">
                                <h2 class="text-lg font-bold flex items-center gap-2"><span class="material-symbols-outlined text-primary">contact_phone</span> Coordonnées</h2>
                                <div class="grid grid-cols-1 gap-4">
                                    <div v-if="isOn('telephone')">
                                        <label class="reg-label">Téléphones{{ star('telephone') }}</label>
                                        <CamaPhoneInput v-model="form.telephone" :max="2" :required="isReq('telephone')" />
                                        <FieldError :msg="form.errors.telephone" />
                                    </div>
                                    <div>
                                        <label class="reg-label">E-mail *</label>
                                        <div class="relative">
                                            <input
                                                v-model="form.email"
                                                type="email"
                                                class="reg-input pr-10"
                                                :class="form.email && !emailLooksValid ? 'border-red-300' : (emailLooksValid ? 'border-green-400' : '')"
                                                required
                                                autocomplete="email"
                                                placeholder="vous@exemple.bf"
                                            />
                                            <span
                                                v-if="emailLooksValid"
                                                class="material-symbols-outlined text-[20px] text-green-600 absolute right-3 top-1/2 -translate-y-1/2"
                                            >check_circle</span>
                                        </div>
                                        <FieldError :msg="form.errors.email" />

                                        <div
                                            v-if="!emailLooksValid"
                                            class="mt-2 rounded-lg bg-primary/5 border border-primary/15 p-3 text-xs text-on-surface-variant space-y-1.5"
                                        >
                                            <p class="flex items-start gap-1.5">
                                                <span class="material-symbols-outlined text-[16px] text-primary shrink-0">mark_email_read</span>
                                                <span>Une adresse e-mail <strong>valide</strong> est obligatoire pour poursuivre l'inscription (elle sert à vos identifiants et notifications).</span>
                                            </p>
                                            <p class="pl-[22px]">
                                                Pas encore d'adresse ? Créez-en une gratuitement :
                                                <a class="font-semibold text-primary hover:underline" href="https://accounts.google.com/signup" rel="noopener" target="_blank">Gmail&nbsp;↗</a>
                                                <span class="opacity-50">·</span>
                                                <a class="font-semibold text-primary hover:underline" href="https://login.yahoo.com/account/create" rel="noopener" target="_blank">Yahoo&nbsp;↗</a>
                                                <span class="block mt-1 opacity-70">Vos informations déjà saisies restent enregistrées pendant ce temps.</span>
                                            </p>
                                        </div>
                                    </div>
                                    <div v-if="isOn('personne_a_prevenir')"><label class="reg-label">Personne à prévenir{{ star('personne_a_prevenir') }}</label><input v-model="form.personne_a_prevenir" class="reg-input" :required="isReq('personne_a_prevenir')" placeholder="Nom et prénom(s)" /><FieldError :msg="form.errors.personne_a_prevenir" /></div>
                                    <div v-if="isOn('tel_personne_a_prevenir')">
                                        <label class="reg-label">Téléphone (personne à prévenir){{ star('tel_personne_a_prevenir') }}</label>
                                        <CamaPhoneInput v-model="form.tel_personne_a_prevenir" :max="2" :required="isReq('tel_personne_a_prevenir')" />
                                        <FieldError :msg="form.errors.tel_personne_a_prevenir" />
                                    </div>
                                </div>
                            </div>

                            <div v-show="currentStepId === 'recap'" class="space-y-4">
                                <h2 class="text-lg font-bold flex items-center gap-2"><span class="material-symbols-outlined text-primary">fact_check</span> Récapitulatif</h2>
                                <p class="text-sm text-on-surface-variant -mt-2">Vérifiez l'exactitude de toutes les informations avant de finaliser.</p>
                                <div class="recap-grid text-sm">
                                    <div class="recap-block">
                                        <h3>Identité</h3>
                                        <div class="recap-row"><span>Nom</span><strong>{{ form.nom }}</strong></div>
                                        <div class="recap-row"><span>Prénom(s)</span><strong>{{ form.prenom }}</strong></div>
                                        <div v-if="isOn('sexe')" class="recap-row"><span>Sexe</span><strong>{{ form.sexe || '—' }}</strong></div>
                                        <div v-if="isOn('matricule')" class="recap-row"><span>Matricule</span><strong>{{ form.matricule || '—' }}</strong></div>
                                    </div>
                                    <div v-for="block in recapBlocks" :key="block.title" class="recap-block">
                                        <h3>{{ block.title }}</h3>
                                        <div v-for="row in block.rows" :key="row.key" class="recap-row"><span>{{ row.label }}</span><strong>{{ row.value || '—' }}</strong></div>
                                    </div>
                                </div>
                                <label class="flex items-start gap-3 pt-3 border-t border-outline-variant text-xs text-on-surface-variant">
                                    <input v-model="form.cgu" type="checkbox" class="w-5 h-5 mt-0.5 text-primary rounded shrink-0" />
                                    J'accepte les CGU et certifie l'exactitude des informations.
                                </label>
                            </div>

                            <div v-show="currentStepId === 'securite'" class="space-y-4">
                                <div v-if="hasDocuments" class="space-y-3 pb-4 border-b border-outline-variant">
                                    <h2 class="text-lg font-bold flex items-center gap-2"><span class="material-symbols-outlined text-primary">badge</span> Pièces justificatives</h2>
                                    <p class="text-sm text-on-surface-variant -mt-1">Téléversez les documents demandés (PDF, JPEG ou PNG — 5 Mo max) pour permettre la vérification de votre identité.</p>
                                    <div class="grid grid-cols-1 gap-3">
                                        <div v-for="doc in inscriptionDocuments" :key="doc.key" class="border border-outline-variant rounded-lg p-3">
                                            <p class="text-xs font-semibold mb-2">{{ doc.titre }} <span class="text-error">*</span></p>
                                            <div v-if="documentFiles[doc.key]" class="text-[11px] flex items-center gap-2 bg-surface-container-low rounded px-2 py-1.5 mb-2">
                                                <span class="material-symbols-outlined text-secondary text-[16px]">task</span>
                                                <span class="truncate flex-1">{{ documentFiles[doc.key].name }}</span>
                                                <button type="button" class="text-error shrink-0" @click="removeDocument(doc.key)"><span class="material-symbols-outlined text-[16px]">close</span></button>
                                            </div>
                                            <label class="text-[11px] text-primary font-semibold cursor-pointer inline-flex items-center gap-1.5">
                                                <span class="material-symbols-outlined text-[16px]">upload_file</span> Téléverser {{ doc.titre }}
                                                <input type="file" class="hidden" accept=".pdf,.jpg,.jpeg,.png" @change="handleDocument(doc.key, $event)" />
                                            </label>
                                            <p v-if="documentErrors[doc.key]" class="text-error text-[11px] mt-1.5">{{ documentErrors[doc.key] }}</p>
                                            <p v-if="form.errors[`documents.${doc.key}`]" class="text-error text-[11px] mt-1.5">{{ form.errors[`documents.${doc.key}`] }}</p>
                                        </div>
                                    </div>
                                </div>

                                <h2 class="text-lg font-bold flex items-center gap-2"><span class="material-symbols-outlined text-primary">lock</span> Sécurité du compte</h2>
                                <div class="grid grid-cols-1 gap-4">
                                    <div><label class="reg-label">Mot de passe *</label><input v-model="form.password" type="password" class="reg-input" required autocomplete="new-password" /></div>
                                    <div><label class="reg-label">Confirmer *</label><input v-model="form.password_confirmation" type="password" class="reg-input" required autocomplete="new-password" /></div>
                                </div>
                                <ul class="grid grid-cols-1 sm:grid-cols-2 gap-1 text-xs text-on-surface-variant" id="pw-rules">
                                    <li class="flex items-center gap-1.5" :class="{ ok: pwRules.length }"><span class="material-symbols-outlined text-[15px]">{{ pwRules.length ? 'check_circle' : 'radio_button_unchecked' }}</span> Au moins 12 caractères</li>
                                    <li class="flex items-center gap-1.5" :class="{ ok: pwRules.upper }"><span class="material-symbols-outlined text-[15px]">{{ pwRules.upper ? 'check_circle' : 'radio_button_unchecked' }}</span> Une majuscule</li>
                                    <li class="flex items-center gap-1.5" :class="{ ok: pwRules.digit }"><span class="material-symbols-outlined text-[15px]">{{ pwRules.digit ? 'check_circle' : 'radio_button_unchecked' }}</span> Un chiffre</li>
                                    <li class="flex items-center gap-1.5" :class="{ ok: pwRules.special }"><span class="material-symbols-outlined text-[15px]">{{ pwRules.special ? 'check_circle' : 'radio_button_unchecked' }}</span> Un caractère spécial</li>
                                </ul>
                            </div>

                            <div class="flex flex-col-reverse sm:flex-row justify-between gap-3 mt-8 pt-6 border-t border-outline-variant">
                                <button v-if="currentStep > 1" type="button" class="px-5 py-2.5 rounded-lg border border-outline-variant font-semibold text-sm" @click="prevStep">Précédent</button>
                                <button v-if="currentStep < visibleSteps.length" type="button" class="px-5 py-2.5 rounded-lg bg-primary text-white font-bold text-sm sm:ml-auto" @click="nextStep">Suivant</button>
                                <CamaLoadingButton
                                    v-else
                                    type="submit"
                                    variant="success"
                                    button-class="px-5 py-2.5 text-sm sm:ml-auto"
                                    :loading="form.processing"
                                    loading-text="Création…"
                                >
                                    <span class="material-symbols-outlined text-[18px]">person_add</span>
                                    Créer mon compte
                                </CamaLoadingButton>
                            </div>
                        </form>
                    </div>
                </div>
                <p class="text-center text-sm text-on-surface-variant mt-6">Déjà inscrit ? <Link class="text-primary font-bold hover:underline" :href="route('assure.login')">Se connecter</Link></p>
            </div>
        </section>
        </main>
    </PublicLayout>
</template>
