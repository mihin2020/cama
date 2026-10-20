<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import CamaLoadingButton from '@/Components/CamaLoadingButton.vue';
import { formatDuration, useAdminIdleSession } from '@/Composables/useAdminIdleSession';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    profile: Object,
});

const pwdForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const twoFaForm = useForm({
    active: false,
    current_password: '',
});

const devicesForm = useForm({
    current_password: '',
});

function submitTwoFa() {
    twoFaForm.active = !props.profile.deuxFa;
    twoFaForm.patch(route('admin.profil.2fa'), {
        preserveScroll: true,
        onSuccess: () => twoFaForm.reset(),
    });
}

const idleForm = useForm({
    active: false,
    current_password: '',
});

function submitIdle() {
    idleForm.active = !props.profile.deconnexionAuto;
    idleForm.patch(route('admin.profil.idle'), {
        preserveScroll: true,
        onSuccess: () => idleForm.reset(),
    });
}

function submitDevices() {
    devicesForm.post(route('admin.profil.devices'), {
        preserveScroll: true,
        onSuccess: () => devicesForm.reset(),
    });
}

const { policy: sessionPolicy, elapsedSeconds, remainingSeconds } = useAdminIdleSession();
const idleMinutes = computed(() => Math.round((sessionPolicy.value?.idleTimeout ?? 900) / 60));

function submitPassword() {
    pwdForm.post(route('admin.profil.password'), {
        preserveScroll: true,
        onSuccess: () => pwdForm.reset(),
    });
}
</script>

<template>
    <Head title="Mon profil" />

    <AdminLayout active-nav="profil" title="Mon profil" subtitle="Compte interne et sécurité">
        <div class="max-w-[800px] w-full mx-auto space-y-5">
            <section class="assure-card p-5 md:p-6">
                <div class="flex items-center gap-4 mb-5 pb-5 border-b border-outline-variant">
                    <div class="w-14 h-14 rounded-full bg-primary/10 text-primary flex items-center justify-center font-bold text-lg">{{ profile.initiales }}</div>
                    <div>
                        <h2 class="text-base font-semibold text-on-surface">{{ profile.displayName }}</h2>
                        <span class="inline-block mt-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-primary/10 text-primary">{{ profile.roleLabel }}</span>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="bg-surface-container-low rounded-lg p-3.5">
                        <p class="text-[10px] text-on-surface-variant uppercase">Identifiant CAMA</p>
                        <p class="text-sm font-semibold">{{ profile.matriculeInterne }}</p>
                    </div>
                    <div class="bg-surface-container-low rounded-lg p-3.5">
                        <p class="text-[10px] text-on-surface-variant uppercase">E-mail professionnel</p>
                        <p class="text-sm font-semibold break-all">{{ profile.email }}</p>
                    </div>
                    <div class="bg-surface-container-low rounded-lg p-3.5 col-span-2 sm:col-span-1">
                        <p class="text-[10px] text-on-surface-variant uppercase">Dernière connexion</p>
                        <p class="text-sm font-semibold">{{ profile.lastLogin }}</p>
                    </div>
                </div>
            </section>

            <section v-if="profile.isGestionnaire && profile.workload" class="assure-card p-5 md:p-6">
                <h2 class="text-base font-semibold mb-1">Mes dossiers affectés</h2>
                <p class="text-xs text-on-surface-variant mb-4">Synthèse des dossiers familiaux qui vous ont été assignés par le superviseur.</p>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <div class="bg-surface-container-low rounded-lg p-3.5 text-center">
                        <p class="text-[10px] text-on-surface-variant uppercase">Assignés</p>
                        <p class="text-xl font-bold text-on-surface">{{ profile.workload.assignes }}</p>
                    </div>
                    <div class="bg-surface-container-low rounded-lg p-3.5 text-center">
                        <p class="text-[10px] text-on-surface-variant uppercase">À traiter</p>
                        <p class="text-xl font-bold text-primary">{{ profile.workload.nonTraites }}</p>
                    </div>
                    <div class="bg-surface-container-low rounded-lg p-3.5 text-center">
                        <p class="text-[10px] text-on-surface-variant uppercase">Validés</p>
                        <p class="text-xl font-bold text-secondary">{{ profile.workload.valides }}</p>
                    </div>
                    <div class="bg-surface-container-low rounded-lg p-3.5 text-center">
                        <p class="text-[10px] text-on-surface-variant uppercase">En retard</p>
                        <p class="text-xl font-bold text-error">{{ profile.workload.retard }}</p>
                    </div>
                </div>
                <p class="text-[11px] text-on-surface-variant mt-4">
                    <Link :href="route('admin.dossiers')" class="text-primary font-bold hover:underline">Voir mes dossiers →</Link>
                </p>
            </section>

            <section class="assure-card p-5 md:p-6">
                <div class="flex items-start justify-between gap-3 mb-1">
                    <h2 class="text-base font-semibold">Sécurité de session</h2>
                    <span
                        class="shrink-0 px-2.5 py-0.5 rounded-full text-[11px] font-semibold"
                        :class="profile.deconnexionAuto ? 'bg-secondary/10 text-secondary' : 'bg-surface-container-low text-on-surface-variant'"
                    >Déconnexion auto {{ profile.deconnexionAuto ? 'activée' : 'désactivée' }}</span>
                </div>
                <p class="text-xs text-on-surface-variant mb-4">
                    Option facultative : déconnexion automatique après {{ idleMinutes }} minutes d'inactivité (aucun clic, aucune frappe, aucun défilement).
                    Recommandée sur un poste partagé.
                </p>

                <div v-if="!profile.deconnexionAuto" class="flex items-center gap-3 bg-surface-container-low border border-outline-variant rounded-lg p-3 mb-3">
                    <span class="material-symbols-outlined text-on-surface-variant">timer_off</span>
                    <p class="text-xs">Session active depuis <strong>{{ formatDuration(elapsedSeconds) }}</strong> — pas de déconnexion pour inactivité.</p>
                </div>
                <div v-else-if="sessionPolicy?.remembered" class="flex items-center gap-3 bg-primary/5 border border-primary/20 rounded-lg p-3 mb-3">
                    <span class="material-symbols-outlined text-primary">devices</span>
                    <p class="text-xs">
                        Session active depuis <strong>{{ formatDuration(elapsedSeconds) }}</strong>.
                        Cet appareil est mémorisé (« Se souvenir de moi ») : la déconnexion automatique ne s'y applique pas, mais une reconnexion
                        est obligatoire au plus tard {{ sessionPolicy.rememberDays }} jour(s) après la connexion.
                    </p>
                </div>
                <div v-else class="flex items-center gap-3 bg-tertiary/10 border border-tertiary/30 rounded-lg p-3 mb-3">
                    <span class="material-symbols-outlined text-tertiary">timer</span>
                    <p class="text-xs">Session active depuis <strong>{{ formatDuration(elapsedSeconds) }}</strong> — expiration automatique dans <strong>{{ formatDuration(remainingSeconds) }}</strong> sans activité.</p>
                </div>

                <form class="flex flex-col sm:flex-row sm:items-end gap-3 mb-5 pb-5 border-b border-outline-variant" @submit.prevent="submitIdle">
                    <div v-if="profile.deconnexionAuto" class="flex-1">
                        <label class="text-[11px] font-bold uppercase text-on-surface-variant block mb-1">Mot de passe actuel</label>
                        <input v-model="idleForm.current_password" class="w-full px-3 py-2 text-xs border border-outline-variant rounded-lg" type="password" autocomplete="current-password" />
                        <p v-if="idleForm.errors.current_password" class="text-error text-xs mt-1">{{ idleForm.errors.current_password }}</p>
                    </div>
                    <CamaLoadingButton type="submit" :variant="profile.deconnexionAuto ? 'danger' : 'success'" :loading="idleForm.processing" loading-text="Enregistrement…">
                        {{ profile.deconnexionAuto ? 'Désactiver la déconnexion auto' : 'Activer la déconnexion auto' }}
                    </CamaLoadingButton>
                </form>
                <form class="grid grid-cols-1 md:grid-cols-2 gap-4" @submit.prevent="submitPassword">
                    <div class="md:col-span-2">
                        <label class="text-[11px] font-bold uppercase text-on-surface-variant block mb-1">Mot de passe actuel</label>
                        <input v-model="pwdForm.current_password" class="w-full px-3 py-2 text-xs border border-outline-variant rounded-lg" type="password" />
                        <p v-if="pwdForm.errors.current_password" class="text-error text-xs mt-1">{{ pwdForm.errors.current_password }}</p>
                    </div>
                    <div>
                        <label class="text-[11px] font-bold uppercase text-on-surface-variant block mb-1">Nouveau mot de passe</label>
                        <input v-model="pwdForm.password" class="w-full px-3 py-2 text-xs border border-outline-variant rounded-lg" type="password" />
                        <p v-if="pwdForm.errors.password" class="text-error text-xs mt-1">{{ pwdForm.errors.password }}</p>
                    </div>
                    <div>
                        <label class="text-[11px] font-bold uppercase text-on-surface-variant block mb-1">Confirmation</label>
                        <input v-model="pwdForm.password_confirmation" class="w-full px-3 py-2 text-xs border border-outline-variant rounded-lg" type="password" />
                    </div>
                    <div class="md:col-span-2">
                        <CamaLoadingButton type="submit" :loading="pwdForm.processing" loading-text="Mise à jour…">
                            Mettre à jour le mot de passe
                        </CamaLoadingButton>
                    </div>
                </form>
            </section>

            <section class="assure-card p-5 md:p-6">
                <div class="flex items-start justify-between gap-3 mb-1">
                    <h2 class="text-base font-semibold">Vérification en deux étapes (2FA)</h2>
                    <span
                        class="shrink-0 px-2.5 py-0.5 rounded-full text-[11px] font-semibold"
                        :class="profile.deuxFa ? 'bg-secondary/10 text-secondary' : 'bg-surface-container-low text-on-surface-variant'"
                    >{{ profile.deuxFa ? 'Activée' : 'Désactivée' }}</span>
                </div>
                <p class="text-xs text-on-surface-variant mb-4">
                    Option facultative. Une fois activée, un code à 6 chiffres est envoyé à {{ profile.email }} à chaque connexion par mot de passe.
                </p>
                <form class="flex flex-col sm:flex-row sm:items-end gap-3" @submit.prevent="submitTwoFa">
                    <div class="flex-1">
                        <label class="text-[11px] font-bold uppercase text-on-surface-variant block mb-1">Mot de passe actuel</label>
                        <input v-model="twoFaForm.current_password" class="w-full px-3 py-2 text-xs border border-outline-variant rounded-lg" type="password" autocomplete="current-password" />
                        <p v-if="twoFaForm.errors.current_password" class="text-error text-xs mt-1">{{ twoFaForm.errors.current_password }}</p>
                    </div>
                    <CamaLoadingButton type="submit" :variant="profile.deuxFa ? 'danger' : 'success'" :loading="twoFaForm.processing" loading-text="Enregistrement…">
                        {{ profile.deuxFa ? 'Désactiver la 2FA' : 'Activer la 2FA' }}
                    </CamaLoadingButton>
                </form>
            </section>

            <section class="assure-card p-5 md:p-6">
                <h2 class="text-base font-semibold mb-1">Appareils connectés</h2>
                <p class="text-xs text-on-surface-variant mb-4">
                    Ferme les sessions ouvertes et annule les « Se souvenir de moi » sur tous vos autres navigateurs. Cet appareil reste connecté.
                </p>
                <form class="flex flex-col sm:flex-row sm:items-end gap-3" @submit.prevent="submitDevices">
                    <div class="flex-1">
                        <label class="text-[11px] font-bold uppercase text-on-surface-variant block mb-1">Mot de passe actuel</label>
                        <input v-model="devicesForm.current_password" class="w-full px-3 py-2 text-xs border border-outline-variant rounded-lg" type="password" autocomplete="current-password" />
                        <p v-if="devicesForm.errors.current_password" class="text-error text-xs mt-1">{{ devicesForm.errors.current_password }}</p>
                    </div>
                    <CamaLoadingButton type="submit" :loading="devicesForm.processing" loading-text="Déconnexion…">
                        Déconnecter mes autres appareils
                    </CamaLoadingButton>
                </form>
            </section>
        </div>
    </AdminLayout>
</template>
