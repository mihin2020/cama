<script setup>
import CamaLoadingButton from '@/Components/CamaLoadingButton.vue';
import CamaPhoneInput from '@/Components/CamaPhoneInput.vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { formatPhonesDisplay } from '@/Utils/camaPhone';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue';

const props = defineProps({
    orgStructure: Object,
    inscriptionDocuments: { type: Array, default: () => [] },
});

const MAX_DOC_SIZE = 5 * 1024 * 1024;
const ACCEPTED_DOC_TYPES = ['application/pdf', 'image/jpeg', 'image/png'];

const documentFiles = reactive({});
const documentErrors = reactive({});

const STEPS = [
    { title: 'Identité', label: 'Étape 1 sur 6 — Identité' },
    { title: 'Situation administrative', label: 'Étape 2 sur 6 — Grade & catégorie' },
    { title: 'Rattachement', label: 'Étape 3 sur 6 — Structure militaire' },
    { title: 'Coordonnées', label: 'Étape 4 sur 6 — Contact' },
    { title: 'Récapitulatif', label: 'Étape 5 sur 6 — Vérification' },
    { title: 'Sécurité', label: 'Étape 6 sur 6 — Mot de passe' },
];

const currentStep = ref(1);
const banner = ref({ type: '', message: '' });

const form = useForm({
    nom: '', prenom: '', sexe: 'Masculin', matricule: '',
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

function hasPhoneValue(raw) {
    return String(raw || '').split('|').some((part) => part.trim());
}

function showBanner(type, message) {
    banner.value = { type, message };
}

function validateStep(n) {
    banner.value = { type: '', message: '' };
    if (n === 1 && (!form.nom || !form.prenom || !form.matricule)) {
        showBanner('error', 'Veuillez compléter les champs obligatoires de cette étape.');
        return false;
    }
    if (n === 2 && (!form.grade || !form.categorie || !form.numero_cim || !form.numero_cama)) {
        showBanner('error', 'Grade, catégorie, N° CIM et N° Carte CAMA sont obligatoires.');
        return false;
    }
    if (n === 3 && (!form.armee || !form.region)) {
        showBanner('error', 'Armée et région sont obligatoires.');
        return false;
    }
    if (n === 4 && (!hasPhoneValue(form.telephone) || !form.email)) {
        showBanner('error', 'Au moins un téléphone et l\'e-mail sont obligatoires.');
        return false;
    }
    if (n === 5 && !form.cgu) {
        showBanner('error', 'Veuillez accepter les CGU.');
        return false;
    }
    return true;
}

function nextStep() {
    if (validateStep(currentStep.value)) currentStep.value++;
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

        if (Number.isInteger(data._step) && data._step >= 1 && data._step <= STEPS.length) {
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
    if (!validateStep(6)) return;
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
                                v-for="(_, i) in STEPS"
                                :key="i"
                                class="wizard-progress-dot"
                                :class="{ 'is-done': i + 1 < currentStep, 'is-active': i + 1 === currentStep }"
                            />
                        </div>
                        <div class="flex items-center justify-between gap-2">
                            <div>
                                <p class="text-xs font-bold text-primary uppercase tracking-wide">{{ STEPS[currentStep - 1].label }}</p>
                                <p class="text-sm text-on-surface-variant mt-0.5">{{ STEPS[currentStep - 1].title }}</p>
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
                            <div v-show="currentStep === 1" class="wizard-step is-active space-y-4">
                                <h2 class="text-lg font-bold flex items-center gap-2"><span class="material-symbols-outlined text-primary">badge</span> Qui êtes-vous ?</h2>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div><label class="reg-label">Nom *</label><input v-model="form.nom" class="reg-input" required /></div>
                                    <div><label class="reg-label">Prénom(s) *</label><input v-model="form.prenom" class="reg-input" required /></div>
                                    <div><label class="reg-label">Sexe *</label><select v-model="form.sexe" class="reg-input"><option>Masculin</option><option>Féminin</option></select></div>
                                    <div><label class="reg-label">Matricule militaire *</label><input v-model="form.matricule" class="reg-input" required /></div>
                                </div>
                            </div>

                            <div v-show="currentStep === 2" class="space-y-4">
                                <h2 class="text-lg font-bold flex items-center gap-2"><span class="material-symbols-outlined text-primary">military_tech</span> Situation administrative</h2>
                                <p class="text-sm text-on-surface-variant -mt-2">Grade, catégorie et numéros administratifs CAMA (N° CIM et N° Carte CAMA obligatoires).</p>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div><label class="reg-label">Grade *</label><select v-model="form.grade" class="reg-input" required><option disabled value="">— Grade —</option><option v-for="g in orgStructure.grades" :key="g">{{ g }}</option></select></div>
                                    <div><label class="reg-label">Catégorie *</label><select v-model="form.categorie" class="reg-input" required><option disabled value="">— Catégorie —</option><option v-for="c in orgStructure.categories" :key="c">{{ c }}</option></select></div>
                                    <div><label class="reg-label">N° informatique <span class="text-error">*</span></label><input v-model="form.numero_informatique" class="reg-input" required /><p v-if="form.errors.numero_informatique" class="text-error text-xs mt-1">{{ form.errors.numero_informatique }}</p></div>
                                    <div><label class="reg-label">N° CIM *</label><input v-model="form.numero_cim" class="reg-input" required placeholder="Ex. CIM-104521" /></div>
                                    <div><label class="reg-label">N° Carte CAMA *</label><input v-model="form.numero_cama" class="reg-input" required placeholder="Ex. CAMA-104521" /></div>
                                    <div><label class="reg-label">N° IUP</label><input v-model="form.numero_iup" class="reg-input" placeholder="Identifiant Unique de la Personne" /></div>
                                </div>
                            </div>

                            <div v-show="currentStep === 3" class="space-y-4">
                                <h2 class="text-lg font-bold flex items-center gap-2"><span class="material-symbols-outlined text-primary">account_tree</span> Structure de rattachement</h2>
                                <p class="text-sm text-on-surface-variant -mt-2">Sélectionnez votre unité dans la hiérarchie militaire configurée par la CAMA.</p>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div><label class="reg-label">Armée *</label><select v-model="form.armee" class="reg-input" required><option disabled value="">— Armée —</option><option v-for="a in orgStructure.armees" :key="a">{{ a }}</option></select></div>
                                    <div><label class="reg-label">Région *</label><select v-model="form.region" class="reg-input" required><option disabled value="">— Région —</option><option v-for="r in orgStructure.regions" :key="r.id">{{ r.libelle }}</option></select></div>
                                    <div><label class="reg-label">Corps</label><select v-model="form.corps" class="reg-input" :disabled="!corpsOptions.length"><option disabled value="">— Corps —</option><option v-for="c in corpsOptions" :key="c">{{ c }}</option></select></div>
                                    <div><label class="reg-label">Service</label><select v-model="form.service" class="reg-input" :disabled="!serviceOptions.length"><option disabled value="">— Service —</option><option v-for="s in serviceOptions" :key="s">{{ s }}</option></select></div>
                                    <div><label class="reg-label">Section</label><select v-model="form.section" class="reg-input" :disabled="!sectionOptions.length"><option disabled value="">— Section —</option><option v-for="s in sectionOptions" :key="s">{{ s }}</option></select></div>
                                    <div><label class="reg-label">Sous-section</label><select v-model="form.sous_section" class="reg-input" :disabled="!sousSectionOptions.length"><option disabled value="">— Sous-section —</option><option v-for="s in sousSectionOptions" :key="s">{{ s }}</option></select></div>
                                </div>
                            </div>

                            <div v-show="currentStep === 4" class="space-y-4">
                                <h2 class="text-lg font-bold flex items-center gap-2"><span class="material-symbols-outlined text-primary">contact_phone</span> Coordonnées</h2>
                                <div class="grid grid-cols-1 gap-4">
                                    <div>
                                        <label class="reg-label">Téléphones *</label>
                                        <CamaPhoneInput v-model="form.telephone" :max="2" required />
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
                                    <div><label class="reg-label">Personne à prévenir</label><input v-model="form.personne_a_prevenir" class="reg-input" placeholder="Nom et prénom(s)" /></div>
                                    <div>
                                        <label class="reg-label">Téléphone (personne à prévenir)</label>
                                        <CamaPhoneInput v-model="form.tel_personne_a_prevenir" :max="2" />
                                    </div>
                                </div>
                            </div>

                            <div v-show="currentStep === 5" class="space-y-4">
                                <h2 class="text-lg font-bold flex items-center gap-2"><span class="material-symbols-outlined text-primary">fact_check</span> Récapitulatif</h2>
                                <p class="text-sm text-on-surface-variant -mt-2">Vérifiez l'exactitude de toutes les informations avant de finaliser.</p>
                                <div class="recap-grid text-sm">
                                    <div class="recap-block">
                                        <h3>Identité</h3>
                                        <div class="recap-row"><span>Nom</span><strong>{{ form.nom }}</strong></div>
                                        <div class="recap-row"><span>Prénom(s)</span><strong>{{ form.prenom }}</strong></div>
                                        <div class="recap-row"><span>Sexe</span><strong>{{ form.sexe }}</strong></div>
                                        <div class="recap-row"><span>Matricule</span><strong>{{ form.matricule }}</strong></div>
                                    </div>
                                    <div class="recap-block">
                                        <h3>Administratif</h3>
                                        <div class="recap-row"><span>Grade</span><strong>{{ form.grade }}</strong></div>
                                        <div class="recap-row"><span>Catégorie</span><strong>{{ form.categorie }}</strong></div>
                                        <div class="recap-row"><span>N° informatique</span><strong>{{ form.numero_informatique || '—' }}</strong></div>
                                        <div class="recap-row"><span>N° CIM</span><strong>{{ form.numero_cim }}</strong></div>
                                        <div class="recap-row"><span>N° Carte CAMA</span><strong>{{ form.numero_cama }}</strong></div>
                                        <div class="recap-row"><span>N° IUP</span><strong>{{ form.numero_iup || '—' }}</strong></div>
                                    </div>
                                    <div class="recap-block">
                                        <h3>Rattachement</h3>
                                        <div class="recap-row"><span>Armée</span><strong>{{ form.armee }}</strong></div>
                                        <div class="recap-row"><span>Région</span><strong>{{ form.region }}</strong></div>
                                        <div class="recap-row"><span>Corps</span><strong>{{ form.corps || '—' }}</strong></div>
                                        <div class="recap-row"><span>Service</span><strong>{{ form.service || '—' }}</strong></div>
                                        <div class="recap-row"><span>Section</span><strong>{{ form.section || '—' }}</strong></div>
                                        <div class="recap-row"><span>Sous-section</span><strong>{{ form.sous_section || '—' }}</strong></div>
                                    </div>
                                    <div class="recap-block">
                                        <h3>Contact</h3>
                                        <div class="recap-row"><span>Téléphones</span><strong>{{ phonesDisplay }}</strong></div>
                                        <div class="recap-row"><span>E-mail</span><strong>{{ form.email }}</strong></div>
                                        <div class="recap-row"><span>Personne à prévenir</span><strong>{{ form.personne_a_prevenir || '—' }}</strong></div>
                                        <div class="recap-row"><span>Tél. personne à prévenir</span><strong>{{ telPersonneDisplay }}</strong></div>
                                    </div>
                                </div>
                                <label class="flex items-start gap-3 pt-3 border-t border-outline-variant text-xs text-on-surface-variant">
                                    <input v-model="form.cgu" type="checkbox" class="w-5 h-5 mt-0.5 text-primary rounded shrink-0" />
                                    J'accepte les CGU et certifie l'exactitude des informations.
                                </label>
                            </div>

                            <div v-show="currentStep === 6" class="space-y-4">
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
                                <button v-if="currentStep < 6" type="button" class="px-5 py-2.5 rounded-lg bg-primary text-white font-bold text-sm sm:ml-auto" @click="nextStep">Suivant</button>
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
