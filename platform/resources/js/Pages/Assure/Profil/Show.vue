<script setup>
import AssureLayout from '@/Layouts/AssureLayout.vue';
import CamaLoadingButton from '@/Components/CamaLoadingButton.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    profil: Object,
    securityLog: Array,
    unreadCount: Number,
});

const inputCls = 'w-full px-3 py-2.5 text-sm border border-outline-variant rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition-all outline-none bg-white';

const contactForm = useForm({
    email: props.profil.email,
    telephone: props.profil.telephone,
    personne_a_prevenir: props.profil.personneAPrevenir ?? '',
    tel_personne_a_prevenir: props.profil.telPersonneAPrevenir ?? '',
    numero_cama: props.profil.numeroCama ?? '',
});

const pwdForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const deuxFa = ref(props.profil.deuxFa);
const pwdError = ref('');
const toast = ref({ show: false, html: '' });

const militaireRows = [
    ['Sexe', props.profil.sexe],
    ['Grade', props.profil.grade],
    ['Catégorie', props.profil.categorie],
    ['N° informatique', props.profil.numeroInformatique],
    ['N° CIM', props.profil.numeroCim],
    ['N° IUP', props.profil.numeroIup],
    ['Armée', props.profil.armee],
    ['Région', props.profil.region],
    ['Corps', props.profil.corps],
    ['Service', props.profil.service],
    ['Section', props.profil.section],
    ['Sous-section', props.profil.sousSection],
    ['Personne à prévenir', props.profil.personneAPrevenir],
    ['Tél. personne à prévenir', props.profil.telPersonneAPrevenir],
];

function showToast(html) {
    toast.value = { show: true, html };
    setTimeout(() => { toast.value.show = false; }, 3000);
}

function saveContact() {
    contactForm.patch(route('assure.profil.update'), {
        preserveScroll: true,
        onSuccess: () => showToast('<span class="material-symbols-outlined">check_circle</span> Coordonnées mises à jour.'),
    });
}

function savePassword() {
    pwdError.value = '';
    if (pwdForm.password.length < 12) {
        pwdError.value = 'Le mot de passe doit contenir au moins 12 caractères.';
        return;
    }
    if (pwdForm.password !== pwdForm.password_confirmation) {
        pwdError.value = 'Les mots de passe ne correspondent pas.';
        return;
    }
    pwdForm.patch(route('assure.profil.password'), {
        preserveScroll: true,
        onSuccess: () => {
            pwdForm.reset();
            showToast('<span class="material-symbols-outlined">check_circle</span> Mot de passe mis à jour.');
        },
        onError: (errors) => {
            pwdError.value = errors.current_password || errors.password || 'Erreur lors de la mise à jour.';
        },
    });
}

function toggle2fa() {
    deuxFa.value = !deuxFa.value;
    router.patch(route('assure.profil.2fa'), { active: deuxFa.value }, {
        preserveScroll: true,
        onSuccess: () => showToast(`<span class="material-symbols-outlined">verified_user</span> 2FA ${deuxFa.value ? 'activée' : 'désactivée'}.`),
        onError: () => { deuxFa.value = !deuxFa.value; },
    });
}

function displayValue(value) {
    return value && String(value).trim() ? value : '—';
}
</script>

<template>
    <Head title="Mon profil" />

    <AssureLayout active-nav="profil" title="Mon profil" subtitle="Informations et sécurité du compte" :unread-count="unreadCount">
        <div class="space-y-5 max-w-[900px]">
            <section class="assure-card p-5 md:p-6">
                <div class="flex items-center gap-4 mb-5 pb-5 border-b border-outline-variant">
                    <div class="w-14 h-14 rounded-full bg-primary/10 text-primary flex items-center justify-center font-bold text-lg">{{ profil.initiales }}</div>
                    <div>
                        <h2 class="text-base font-semibold text-on-surface">{{ profil.fullName }}</h2>
                        <span
                            class="inline-block mt-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold"
                            :class="profil.statutActif ? 'bg-secondary text-on-secondary' : 'bg-tertiary text-on-tertiary'"
                        >{{ profil.statut }}</span>
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="bg-surface-container-low rounded-lg p-3.5">
                        <p class="text-xs text-on-surface-variant uppercase tracking-wide mb-1">Matricule militaire</p>
                        <p class="text-sm font-semibold text-on-surface">{{ profil.matricule }}</p>
                    </div>
                    <div class="bg-surface-container-low rounded-lg p-3.5">
                        <p class="text-xs text-on-surface-variant uppercase tracking-wide mb-1">Numéro CAMA</p>
                        <p class="text-sm font-semibold text-on-surface">{{ profil.numeroCama || "En attente d'attribution" }}</p>
                    </div>
                    <div class="bg-surface-container-low rounded-lg p-3.5">
                        <p class="text-xs text-on-surface-variant uppercase tracking-wide mb-1">Compte créé le</p>
                        <p class="text-sm font-semibold text-on-surface">{{ profil.dateCreation }}</p>
                    </div>
                    <div class="bg-surface-container-low rounded-lg p-3.5">
                        <p class="text-xs text-on-surface-variant uppercase tracking-wide mb-1">Dernière connexion</p>
                        <p class="text-sm font-semibold text-on-surface">{{ profil.derniereConnexion }}</p>
                    </div>
                </div>
            </section>

            <section class="assure-card p-5 md:p-6">
                <h2 class="text-base font-semibold text-on-surface mb-1">Informations administratives du militaire</h2>
                <p class="text-on-surface-variant text-xs mb-5">Données déclarées à l'inscription. Contactez la CAMA pour toute correction.</p>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                    <div v-for="([label, value], i) in militaireRows" :key="i" class="bg-surface-container-low rounded-lg p-3.5">
                        <p class="text-xs text-on-surface-variant uppercase tracking-wide mb-1">{{ label }}</p>
                        <p class="text-sm font-semibold text-on-surface break-words">{{ displayValue(value) }}</p>
                    </div>
                </div>
            </section>

            <section class="assure-card p-5 md:p-6">
                <h2 class="text-base font-semibold text-on-surface mb-1">Coordonnées</h2>
                <p class="text-on-surface-variant text-xs mb-5">Modifiez vos coordonnées de contact. Les données militaires sont gérées par la CAMA.</p>
                <form class="grid grid-cols-1 md:grid-cols-2 gap-4" @submit.prevent="saveContact">
                    <div class="space-y-1.5">
                        <label class="text-sm font-medium text-on-surface-variant ml-1">Adresse e-mail</label>
                        <input v-model="contactForm.email" :class="inputCls" type="email" required />
                    </div>
                    <div class="space-y-1.5 md:col-span-2">
                        <label class="text-sm font-medium text-on-surface-variant ml-1">Téléphone(s)</label>
                        <input v-model="contactForm.telephone" :class="inputCls" type="text" required />
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-sm font-medium text-on-surface-variant ml-1">Personne à prévenir</label>
                        <input v-model="contactForm.personne_a_prevenir" :class="inputCls" type="text" />
                    </div>
                    <div class="space-y-1.5 md:col-span-2">
                        <label class="text-sm font-medium text-on-surface-variant ml-1">Téléphone(s) personne à prévenir</label>
                        <input v-model="contactForm.tel_personne_a_prevenir" :class="inputCls" type="text" />
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-sm font-medium text-on-surface-variant ml-1">N° Carte CAMA</label>
                        <input v-model="contactForm.numero_cama" :class="inputCls" type="text" placeholder="Si déjà attribué" />
                    </div>
                    <div class="md:col-span-2">
                        <CamaLoadingButton type="submit" button-class="px-5 py-2.5 text-sm" :loading="contactForm.processing">
                            Enregistrer
                        </CamaLoadingButton>
                    </div>
                </form>
            </section>

            <section class="assure-card p-5 md:p-6">
                <h2 class="text-base font-semibold text-on-surface mb-1">Sécurité</h2>
                <p class="text-on-surface-variant text-xs mb-5">Vous serez alerté par e-mail en cas de connexion inhabituelle ou de changement de mot de passe.</p>
                <form class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6" @submit.prevent="savePassword">
                    <div class="md:col-span-2 space-y-1.5">
                        <label class="text-sm font-medium text-on-surface-variant ml-1">Mot de passe actuel</label>
                        <input v-model="pwdForm.current_password" :class="inputCls" type="password" />
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-sm font-medium text-on-surface-variant ml-1">Nouveau mot de passe</label>
                        <input v-model="pwdForm.password" :class="inputCls" type="password" placeholder="12 caractères minimum" />
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-sm font-medium text-on-surface-variant ml-1">Confirmation</label>
                        <input v-model="pwdForm.password_confirmation" :class="inputCls" type="password" />
                    </div>
                    <p v-if="pwdError" class="md:col-span-2 text-error text-xs">{{ pwdError }}</p>
                    <div class="md:col-span-2">
                        <CamaLoadingButton type="submit" variant="dark" button-class="px-5 py-2.5 text-sm" :loading="pwdForm.processing" loading-text="Mise à jour…">
                            Mettre à jour le mot de passe
                        </CamaLoadingButton>
                    </div>
                </form>
                <div class="flex items-center justify-between pt-5 border-t border-outline-variant gap-4">
                    <div>
                        <p class="text-sm font-semibold flex items-center gap-2">
                            <span class="material-symbols-outlined text-secondary text-[18px]">verified_user</span> Authentification à deux facteurs (2FA)
                        </p>
                        <p class="text-xs text-on-surface-variant mt-1">Code de vérification envoyé par e-mail à chaque connexion.</p>
                    </div>
                    <button
                        type="button"
                        class="toggle-switch shrink-0"
                        :class="deuxFa ? 'on' : 'off'"
                        @click="toggle2fa"
                    >
                        <div class="knob" />
                    </button>
                </div>
            </section>

            <section class="assure-card p-5 md:p-6">
                <h2 class="text-base font-semibold text-on-surface mb-4">Dernières connexions</h2>
                <div class="space-y-3">
                    <div
                        v-for="(s, i) in securityLog"
                        :key="i"
                        class="flex items-center gap-3 p-3 rounded-lg"
                        :class="s.suspect ? 'bg-error/5 border border-error/20' : 'bg-surface-container-low'"
                    >
                        <span class="material-symbols-outlined text-[18px]" :class="s.suspect ? 'text-error' : 'text-secondary'">{{ s.suspect ? 'warning' : 'check_circle' }}</span>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-on-surface">
                                {{ s.appareil }}
                                <span v-if="s.suspect" class="text-error text-xs font-medium">(connexion inhabituelle)</span>
                            </p>
                            <p class="text-xs text-on-surface-variant mt-0.5">{{ s.date }} · {{ s.lieu }}</p>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </AssureLayout>

    <div v-if="toast.show" class="fixed bottom-6 right-6 z-50">
        <div class="bg-on-background text-white px-5 py-3 rounded-lg shadow-xl flex items-center gap-2 font-label-md text-label-md" v-html="toast.html" />
    </div>
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
</style>

