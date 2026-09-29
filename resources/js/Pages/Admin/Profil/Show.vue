<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import CamaLoadingButton from '@/Components/CamaLoadingButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref } from 'vue';

defineProps({
    profile: Object,
});

const pwdForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const sessionSeconds = ref(0);
let timer = null;

onMounted(() => {
    timer = setInterval(() => {
        sessionSeconds.value++;
    }, 1000);
});

onUnmounted(() => {
    if (timer) clearInterval(timer);
});

const sessionTime = () => {
    const m = String(Math.floor(sessionSeconds.value / 60)).padStart(2, '0');
    const s = String(sessionSeconds.value % 60).padStart(2, '0');
    return `${m}:${s}`;
};

const sessionRemaining = () => {
    const remaining = Math.max(0, 900 - sessionSeconds.value);
    const m = String(Math.floor(remaining / 60)).padStart(2, '0');
    const s = String(remaining % 60).padStart(2, '0');
    return `${m}:${s}`;
};

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
                <h2 class="text-base font-semibold mb-1">Sécurité de session</h2>
                <p class="text-xs text-on-surface-variant mb-4">Conformément à la politique de sécurité du back-office : déconnexion automatique après 15 minutes d'inactivité.</p>
                <div class="flex items-center gap-3 bg-tertiary/10 border border-tertiary/30 rounded-lg p-3 mb-5">
                    <span class="material-symbols-outlined text-tertiary">timer</span>
                    <p class="text-xs">Session active depuis <strong>{{ sessionTime() }}</strong> — expiration automatique dans <strong>{{ sessionRemaining() }}</strong>.</p>
                </div>
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
        </div>
    </AdminLayout>
</template>
