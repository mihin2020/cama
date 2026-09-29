<script setup>
import AssureLayout from '@/Layouts/AssureLayout.vue';
import { NOTIF_ICONS, statusBadgeClass } from '@/Utils/camaStatus';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

const props = defineProps({
    assure: Object,
    stats: Object,
    membres: Array,
    notifications: Array,
    unreadCount: Number,
});

// État global du dossier familial (workflow), à partir des membres soumis.
const OPEN_STATUTS = ['Soumis', 'En instruction', 'En attente supervision', 'Pièce manquante demandée'];
const familyStatus = computed(() => {
    const submitted = (props.membres ?? []).filter((m) => m.statut !== 'Brouillon' && m.statut !== 'Retiré');
    const total = submitted.length;
    const validated = submitted.filter((m) => m.statut === 'Validé').length;
    const complement = submitted.filter((m) => m.statut === 'Pièce manquante demandée').length;
    const pendingNew = submitted.filter((m) => OPEN_STATUTS.includes(m.statut)).length;
    const hasAcquired = validated > 0;
    const allValidated = total > 0 && validated === total;

    let key = 'aucun';
    if (total === 0) key = 'aucun';
    else if (allValidated) key = 'valide';
    else if (hasAcquired && pendingNew > 0) key = 'assure_ajout';
    else if (complement > 0) key = 'complement';
    else key = 'instruction';

    return {
        key,
        step: total === 0 ? 0 : (allValidated ? 3 : 2),
        total,
        validated,
        complement,
        pendingNew,
        hasAcquired,
        allValidated,
        pct: total ? Math.round((validated / total) * 100) : 0,
    };
});

const WORKFLOW_STEPS = [
    { n: 1, label: 'Soumission', icon: 'send' },
    { n: 2, label: 'Instruction', icon: 'fact_check' },
    { n: 3, label: 'Validation', icon: 'verified' },
];

const displayedStats = ref({
    enAttente: 0,
    valides: 0,
    refuses: 0,
    aCompleter: 0,
});

const notifIconClass = (type) => {
    const color = NOTIF_ICONS[type]?.color ?? 'primary';
    const map = {
        secondary: 'bg-secondary/10 text-secondary',
        primary: 'bg-primary/10 text-primary',
        tertiary: 'bg-tertiary/10 text-tertiary',
        error: 'bg-error/10 text-error',
    };
    return map[color] ?? map.primary;
};

function countUp(key, target, duration = 800) {
    const start = performance.now();
    const tick = (now) => {
        const progress = Math.min((now - start) / duration, 1);
        displayedStats.value[key] = Math.round(target * (1 - Math.pow(1 - progress, 3)));
        if (progress < 1) requestAnimationFrame(tick);
    };
    requestAnimationFrame(tick);
}

onMounted(() => {
    countUp('enAttente', props.stats.enAttente);
    countUp('valides', props.stats.valides);
    countUp('refuses', props.stats.refuses);
    countUp('aCompleter', props.stats.aCompleter);
});
</script>

<template>
    <Head title="Tableau de bord" />

    <AssureLayout :preview-notifications="notifications" :unread-count="unreadCount">
        <div
            v-if="!assure.peutEnroler"
            class="flex items-start gap-2 bg-tertiary/15 border border-tertiary/40 rounded-lg p-4 mb-5 text-sm"
            id="pending-banner"
            role="status"
        >
            <span class="material-symbols-outlined text-tertiary text-[20px] shrink-0">hourglass_top</span>
            <span>
                Votre compte est <strong>en attente de validation</strong> par la CAMA. Vous pouvez consulter votre espace et préparer votre dossier familial en brouillon.
                Un gestionnaire vérifie actuellement vos informations (matricule, N° CIM, N° Carte CAMA). La <strong>soumission des dossiers de vos membres</strong> sera disponible dès l'activation de votre compte.
            </span>
        </div>

        <section class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-primary to-primary-container text-on-primary p-5 md:p-6 mb-6">
            <div class="shield-pattern absolute inset-0" />
            <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider opacity-80 mb-1">Bienvenue</p>
                    <h2 class="text-lg md:text-xl font-bold font-headline-lg leading-snug mb-0.5">Bonjour, {{ assure.prenom }} {{ assure.nom }}</h2>
                    <p class="text-sm opacity-90">Matricule {{ assure.matricule }} · {{ assure.numeroCama || 'N° Carte CAMA en attente' }}</p>
                </div>
                <div class="inline-flex items-center gap-1.5 bg-white/15 backdrop-blur-md px-3 py-1.5 rounded-full text-xs font-semibold uppercase tracking-wide w-fit">
                    <span class="material-symbols-outlined text-[16px]">verified_user</span>
                    <span>Compte {{ assure.statut }}</span>
                </div>
            </div>
        </section>

        <section class="grid grid-cols-2 md:grid-cols-4 gap-3 md:gap-4 mb-6">
            <div class="bg-white rounded-xl border border-outline-variant p-4 md:p-5 hover:shadow-md hover:-translate-y-0.5 transition-all duration-300">
                <div class="w-9 h-9 rounded-lg bg-primary/10 text-primary flex items-center justify-center mb-3">
                    <span class="material-symbols-outlined text-[20px]">hourglass_top</span>
                </div>
                <p class="dash-stat-value text-on-surface">{{ displayedStats.enAttente }}</p>
                <p class="text-xs text-on-surface-variant font-medium mt-0.5">En attente</p>
            </div>
            <div class="bg-white rounded-xl border border-outline-variant p-4 md:p-5 hover:shadow-md hover:-translate-y-0.5 transition-all duration-300">
                <div class="w-9 h-9 rounded-lg bg-secondary/10 text-secondary flex items-center justify-center mb-3">
                    <span class="material-symbols-outlined text-[20px]">verified</span>
                </div>
                <p class="dash-stat-value text-on-surface">{{ displayedStats.valides }}</p>
                <p class="text-xs text-on-surface-variant font-medium mt-0.5">Validés</p>
            </div>
            <div class="bg-white rounded-xl border border-outline-variant p-4 md:p-5 hover:shadow-md hover:-translate-y-0.5 transition-all duration-300">
                <div class="w-9 h-9 rounded-lg bg-error/10 text-error flex items-center justify-center mb-3">
                    <span class="material-symbols-outlined text-[20px]">cancel</span>
                </div>
                <p class="dash-stat-value text-on-surface">{{ displayedStats.refuses }}</p>
                <p class="text-xs text-on-surface-variant font-medium mt-0.5">Refusés</p>
            </div>
            <div class="bg-white rounded-xl border border-outline-variant p-4 md:p-5 hover:shadow-md hover:-translate-y-0.5 transition-all duration-300">
                <div class="w-9 h-9 rounded-lg bg-tertiary/10 text-tertiary flex items-center justify-center mb-3">
                    <span class="material-symbols-outlined text-[20px]">edit_note</span>
                </div>
                <p class="dash-stat-value text-on-surface">{{ displayedStats.aCompleter }}</p>
                <p class="text-xs text-on-surface-variant font-medium mt-0.5">À compléter</p>
            </div>
        </section>

        <!-- Suivi du dossier familial (workflow) -->
        <section v-if="familyStatus.total" class="bg-white rounded-xl border border-outline-variant p-4 md:p-5 mb-6">
            <div
                class="rounded-lg p-3.5 flex items-start gap-3 mb-4"
                :class="{
                    'bg-secondary/10 border border-secondary/30': familyStatus.key === 'valide',
                    'bg-tertiary/5 border border-tertiary/30': familyStatus.key === 'complement' || familyStatus.key === 'assure_ajout',
                    'bg-primary/5 border border-primary/20': familyStatus.key === 'instruction',
                }"
            >
                <span
                    class="material-symbols-outlined text-[28px] shrink-0"
                    :class="{
                        'text-secondary': familyStatus.key === 'valide',
                        'text-tertiary': familyStatus.key === 'complement' || familyStatus.key === 'assure_ajout',
                        'text-primary': familyStatus.key === 'instruction',
                    }"
                >{{ familyStatus.key === 'valide' ? 'task_alt' : (familyStatus.key === 'assure_ajout' ? 'group_add' : (familyStatus.key === 'complement' ? 'assignment_late' : 'hourglass_top')) }}</span>
                <div class="flex-1 min-w-0">
                    <p class="font-bold text-sm text-on-surface">
                        <template v-if="familyStatus.key === 'valide'">Dossier familial validé — tous vos membres sont pris en charge</template>
                        <template v-else-if="familyStatus.key === 'assure_ajout'">Ajout en cours — {{ familyStatus.pendingNew }} nouveau(x) membre(s) en instruction</template>
                        <template v-else-if="familyStatus.key === 'complement'">Une pièce complémentaire est demandée</template>
                        <template v-else>Dossier en cours d'instruction par la CAMA</template>
                    </p>
                    <p class="text-xs text-on-surface-variant mt-1">
                        <template v-if="familyStatus.key === 'assure_ajout'">Vous êtes déjà assuré ({{ familyStatus.validated }} membre(s) pris en charge). Votre nouvel ajout est en cours d'examen.</template>
                        <template v-else><strong class="text-on-surface">{{ familyStatus.validated }}/{{ familyStatus.total }}</strong> membre(s) validé(s)</template>
                    </p>
                </div>
                <Link :href="route('assure.membres')" class="text-xs font-bold shrink-0 hover:underline" :class="familyStatus.key === 'valide' ? 'text-secondary' : 'text-primary'">Détails →</Link>
            </div>

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
        </section>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 md:gap-6">
            <div class="lg:col-span-2 bg-white rounded-xl border border-outline-variant overflow-hidden">
                <div class="px-4 md:px-5 py-3 border-b border-outline-variant bg-surface-container-low flex items-center justify-between">
                    <h3 class="text-base font-semibold text-on-surface">Mes dossiers</h3>
                    <Link class="text-primary font-semibold text-sm hover:underline" :href="route('assure.membres')">Voir tout</Link>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="bg-surface-container-low text-on-surface-variant text-xs uppercase tracking-wide">
                                <th class="px-4 md:px-5 py-2.5 font-medium">Bénéficiaire</th>
                                <th class="px-4 md:px-5 py-2.5 font-medium hidden sm:table-cell">Lien</th>
                                <th class="px-4 md:px-5 py-2.5 font-medium hidden md:table-cell">Soumis le</th>
                                <th class="px-4 md:px-5 py-2.5 font-medium">Statut</th>
                                <th class="px-4 md:px-5 py-2.5 font-medium text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant">
                            <tr
                                v-for="m in membres"
                                :key="m.id"
                                class="hover:bg-surface-container-low transition-colors cursor-pointer"
                                @click="router.visit(route('assure.membres'))"
                            >
                                <td class="px-4 md:px-5 py-3">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-8 h-8 rounded-full bg-surface-container flex items-center justify-center text-xs font-semibold text-primary shrink-0">{{ m.initiales }}</div>
                                        <span class="font-semibold text-on-surface text-sm">{{ m.prenom }} {{ m.nom }}</span>
                                    </div>
                                </td>
                                <td class="px-4 md:px-5 py-3 text-on-surface-variant hidden sm:table-cell">{{ m.lien }}</td>
                                <td class="px-4 md:px-5 py-3 text-on-surface-variant hidden md:table-cell">{{ m.dateSoumission || '—' }}</td>
                                <td class="px-4 md:px-5 py-3">
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold whitespace-nowrap" :class="statusBadgeClass(m.statut)">{{ m.statut }}</span>
                                </td>
                                <td class="px-4 md:px-5 py-3 text-right">
                                    <span class="material-symbols-outlined text-on-surface-variant text-[20px]">chevron_right</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-outline-variant overflow-hidden flex flex-col">
                <div class="px-4 md:px-5 py-3 border-b border-outline-variant bg-surface-container-low flex items-center justify-between">
                    <h3 class="text-base font-semibold text-on-surface">Notifications</h3>
                    <Link class="text-primary font-semibold text-sm hover:underline" :href="route('assure.notifications')">Voir tout</Link>
                </div>
                <div class="divide-y divide-outline-variant flex-1">
                    <Link
                        v-for="n in notifications"
                        :key="n.id"
                        :href="n.lien || route('assure.notifications')"
                        class="flex gap-2.5 px-4 md:px-5 py-3 hover:bg-surface-container-low transition-colors"
                        :class="n.lu ? '' : 'bg-primary/5'"
                    >
                        <div class="w-8 h-8 rounded-full flex items-center justify-center shrink-0" :class="notifIconClass(n.type)">
                            <span class="material-symbols-outlined text-[16px]">{{ NOTIF_ICONS[n.type]?.icon ?? 'notifications' }}</span>
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm text-on-surface leading-snug line-clamp-2">{{ n.contenu }}</p>
                            <p class="text-xs text-on-surface-variant mt-1">{{ n.date }}</p>
                        </div>
                    </Link>
                </div>
            </div>
        </div>

        <section class="grid grid-cols-1 md:grid-cols-3 gap-3 md:gap-4 mt-6">
            <Link class="group bg-white p-4 md:p-5 rounded-xl border border-outline-variant hover:shadow-md hover:-translate-y-0.5 transition-all duration-300 flex items-center gap-3" :href="route('assure.ajouter-membre')">
                <div class="w-10 h-10 rounded-lg bg-primary text-on-primary flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-[20px]">person_add</span>
                </div>
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-on-surface">Mon dossier familial</p>
                    <p class="text-xs text-on-surface-variant mt-0.5">Enrôler conjoint(e)s et enfants</p>
                </div>
                <span class="material-symbols-outlined ml-auto text-on-surface-variant text-[20px] group-hover:translate-x-1 transition-transform shrink-0">arrow_forward</span>
            </Link>
            <Link class="group bg-white p-4 md:p-5 rounded-xl border border-outline-variant hover:shadow-md hover:-translate-y-0.5 transition-all duration-300 flex items-center gap-3" :href="route('assure.profil')">
                <div class="w-10 h-10 rounded-lg bg-secondary text-on-secondary flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-[20px]">manage_accounts</span>
                </div>
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-on-surface">Compléter mon profil</p>
                    <p class="text-xs text-on-surface-variant mt-0.5">Vérifier mes informations</p>
                </div>
                <span class="material-symbols-outlined ml-auto text-on-surface-variant text-[20px] group-hover:translate-x-1 transition-transform shrink-0">arrow_forward</span>
            </Link>
            <Link class="group bg-white p-4 md:p-5 rounded-xl border border-outline-variant hover:shadow-md hover:-translate-y-0.5 transition-all duration-300 flex items-center gap-3" :href="route('assure.membres')">
                <div class="w-10 h-10 rounded-lg bg-tertiary text-on-tertiary flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-[20px]">upload_file</span>
                </div>
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-on-surface">Mettre à jour une pièce</p>
                    <p class="text-xs text-on-surface-variant mt-0.5">Compléter un dossier en attente</p>
                </div>
                <span class="material-symbols-outlined ml-auto text-on-surface-variant text-[20px] group-hover:translate-x-1 transition-transform shrink-0">arrow_forward</span>
            </Link>
        </section>
    </AssureLayout>
</template>
