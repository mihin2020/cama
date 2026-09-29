<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { activityColor, STATUT_BAR_COLORS, statusBadgeClass } from '@/Utils/camaStatus';
import { useAdminPermissions } from '@/Composables/useAdminPermissions';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    stats: Object,
    vuePersonnelle: { type: Boolean, default: false },
});

const page = usePage();
const { can, canSee } = useAdminPermissions();
const role = computed(() => page.props.auth.admin?.role ?? 'gestionnaire');

// Pagination des tableaux du dashboard.
const PRIORITY_PER_PAGE = 6;
const priorityPage = ref(1);
const priorityTotalPages = computed(() => Math.max(1, Math.ceil((props.stats?.priority?.length ?? 0) / PRIORITY_PER_PAGE)));
const pagedPriority = computed(() => (props.stats?.priority ?? []).slice((priorityPage.value - 1) * PRIORITY_PER_PAGE, priorityPage.value * PRIORITY_PER_PAGE));

const CHARGES_PER_PAGE = 6;
const chargesPage = ref(1);
const chargesTotalPages = computed(() => Math.max(1, Math.ceil((props.stats?.charges?.length ?? 0) / CHARGES_PER_PAGE)));
const pagedCharges = computed(() => (props.stats?.charges ?? []).slice((chargesPage.value - 1) * CHARGES_PER_PAGE, chargesPage.value * CHARGES_PER_PAGE));

const statutOrder = ['Soumis', 'En instruction', 'Pièce manquante demandée', 'En attente supervision', 'Validé', 'Refusé'];

const statutBars = computed(() => {
    const byStatut = props.stats?.byStatut ?? {};
    return statutOrder
        .filter((s) => byStatut[s])
        .map((s) => ({ statut: s, count: byStatut[s] }));
});

const statutMax = computed(() => Math.max(...statutBars.value.map((e) => e.count), 1));

const chargeTotals = computed(() => {
    const charges = props.stats?.charges ?? [];
    return {
        assignes: charges.reduce((s, g) => s + g.assignes, 0),
        traiter: charges.reduce((s, g) => s + g.nonTraites, 0),
        valides: charges.reduce((s, g) => s + g.valides, 0),
        retard: charges.reduce((s, g) => s + g.retard, 0),
    };
});

const activityDotClass = (libelle) => {
    const map = {
        secondary: 'bg-secondary',
        error: 'bg-error',
        primary: 'bg-primary',
        tertiary: 'bg-tertiary',
    };
    return map[activityColor(libelle)] ?? 'bg-tertiary';
};

const formatNumber = (n) => (n ?? 0).toLocaleString('fr-FR');
</script>

<template>
    <Head title="Tableau de bord" />

    <AdminLayout
        :inscriptions-count="page.props.app?.inscriptionsEnAttente ?? 0"
        title="Tableau de bord"
    >
        <div v-if="canSee('direction')" class="assure-card p-4 mb-6 border border-primary/25 bg-primary/5" id="direction-banner">
            <div class="flex items-start gap-3">
                <span class="material-symbols-outlined text-primary text-[28px] shrink-0">insights</span>
                <div>
                    <p class="font-bold text-sm text-on-surface mb-0.5">Vue stratégique — Direction Générale</p>
                    <p class="text-xs text-on-surface-variant leading-relaxed">
                        Synthèse en temps réel de l'activité CAMA : assurés, dossiers, charge des équipes et indicateurs de performance. Consultation en lecture seule.
                    </p>
                </div>
            </div>
        </div>

        <section class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
            <div class="assure-card p-5 hover:shadow-lg hover:-translate-y-1 transition-all duration-300">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center">
                        <span class="material-symbols-outlined text-[20px]">groups</span>
                    </div>
                    <span class="text-secondary text-[11px] font-bold flex items-center gap-0.5">
                        <span class="material-symbols-outlined text-[14px]">trending_up</span>+4.2%
                    </span>
                </div>
                <p class="text-headline-md font-headline-lg">{{ formatNumber(stats.assuresTotal) }}</p>
                <p class="text-[11px] text-on-surface-variant uppercase tracking-wide">{{ vuePersonnelle ? 'Familles suivies' : 'Assurés inscrits' }}</p>
                <p class="text-[10px] text-on-surface-variant mt-1">
                    <template v-if="vuePersonnelle">{{ stats.assuresActifs }} validée{{ stats.assuresActifs > 1 ? 's' : '' }}</template>
                    <template v-else>{{ stats.assuresActifs }} actif{{ stats.assuresActifs > 1 ? 's' : '' }}</template>
                </p>
            </div>
            <div class="assure-card p-5 hover:shadow-lg hover:-translate-y-1 transition-all duration-300">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 rounded-xl bg-tertiary/10 text-tertiary flex items-center justify-center">
                        <span class="material-symbols-outlined text-[20px]">pending_actions</span>
                    </div>
                    <span class="text-tertiary text-[11px] font-bold">
                        {{ stats.famillesATraiter > 0 ? `${stats.famillesATraiter} famille(s)` : 'À traiter' }}
                    </span>
                </div>
                <p class="text-headline-md font-headline-lg">{{ formatNumber(stats.dossiersEnAttente) }}</p>
                <p class="text-[11px] text-on-surface-variant uppercase tracking-wide">Dossiers en attente</p>
                <p class="text-[10px] text-on-surface-variant mt-1">{{ stats.famillesDossiers }} dossier(s) familial(aux)</p>
            </div>
            <div class="assure-card p-5 hover:shadow-lg hover:-translate-y-1 transition-all duration-300">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 rounded-xl bg-secondary/10 text-secondary flex items-center justify-center">
                        <span class="material-symbols-outlined text-[20px]">verified</span>
                    </div>
                    <span class="text-secondary text-[11px] font-bold">Optimal</span>
                </div>
                <p class="text-headline-md font-headline-lg">{{ stats.tauxValidation }}%</p>
                <p class="text-[11px] text-on-surface-variant uppercase tracking-wide">Taux de validation</p>
            </div>
            <div class="assure-card p-5 hover:shadow-lg hover:-translate-y-1 transition-all duration-300">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 rounded-xl bg-on-background/10 text-on-background flex items-center justify-center">
                        <span class="material-symbols-outlined text-[20px]">schedule</span>
                    </div>
                    <span class="text-secondary text-[11px] font-bold flex items-center gap-0.5">
                        <span class="material-symbols-outlined text-[14px]">trending_down</span>-0.5j
                    </span>
                </div>
                <p class="text-headline-md font-headline-lg">{{ stats.delaiMoyenJours }}j</p>
                <p class="text-[11px] text-on-surface-variant uppercase tracking-wide">Délai moyen de traitement</p>
                <p class="text-[10px] text-on-surface-variant mt-1">
                    {{ stats.totalRetard > 0 ? `${stats.totalRetard} en retard` : 'Aucun retard' }}
                </p>
            </div>
        </section>

        <section v-if="can('dossiers.assign') || canSee('direction')" class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6" id="stats-extended">
            <div class="assure-card p-4">
                <div class="flex items-center gap-2 mb-2">
                    <span class="material-symbols-outlined text-secondary text-[20px]">check_circle</span>
                    <span class="text-[11px] text-on-surface-variant uppercase tracking-wide">Dossiers validés</span>
                </div>
                <p class="text-2xl font-bold text-secondary">{{ stats.dossiersValides }}</p>
            </div>
            <div class="assure-card p-4">
                <div class="flex items-center gap-2 mb-2">
                    <span class="material-symbols-outlined text-error text-[20px]">cancel</span>
                    <span class="text-[11px] text-on-surface-variant uppercase tracking-wide">Dossiers refusés</span>
                </div>
                <p class="text-2xl font-bold text-error">{{ stats.dossiersRefuses }}</p>
            </div>
            <div class="assure-card p-4">
                <div class="flex items-center gap-2 mb-2">
                    <span class="material-symbols-outlined text-tertiary text-[20px]">person_add</span>
                    <span class="text-[11px] text-on-surface-variant uppercase tracking-wide">Inscriptions en attente</span>
                </div>
                <p class="text-2xl font-bold text-tertiary">{{ stats.inscriptionsEnAttente }}</p>
            </div>
            <div class="assure-card p-4">
                <div class="flex items-center gap-2 mb-2">
                    <span class="material-symbols-outlined text-primary text-[20px]">assignment_late</span>
                    <span class="text-[11px] text-on-surface-variant uppercase tracking-wide">Non affectés</span>
                </div>
                <p class="text-2xl font-bold text-primary">{{ stats.nonAffectes }}</p>
            </div>
        </section>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 assure-card overflow-hidden">
                <div class="px-5 py-4 border-b border-outline-variant bg-surface-container-low flex items-center justify-between">
                    <h3 class="font-title-lg text-title-lg flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary text-[20px]">priority_high</span> Dossiers prioritaires
                    </h3>
                    <Link v-if="can('dossiers.view')" class="text-primary font-bold text-xs hover:underline" :href="route('admin.dossiers')">Voir tout</Link>
                    <Link v-else-if="can('assures.view')" class="text-primary font-bold text-xs hover:underline" :href="route('admin.assures')">Voir les assurés</Link>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="bg-surface-container-low text-on-surface-variant text-[11px] uppercase tracking-wide">
                                <th class="px-5 py-2.5 font-medium">Référence</th>
                                <th class="px-5 py-2.5 font-medium hidden sm:table-cell">Bénéficiaire</th>
                                <th class="px-5 py-2.5 font-medium">Statut</th>
                                <th class="px-5 py-2.5 font-medium text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant">
                            <tr v-for="d in pagedPriority" :key="d.ref" class="hover:bg-surface-container-low transition-colors">
                                <td class="px-5 py-3 font-bold text-xs">{{ d.ref }}</td>
                                <td class="px-5 py-3 text-xs hidden sm:table-cell">{{ d.nom }}</td>
                                <td class="px-5 py-3">
                                    <span class="px-2.5 py-1 rounded-full text-[11px] font-bold whitespace-nowrap" :class="statusBadgeClass(d.statut)">{{ d.statut }}</span>
                                </td>
                                <td class="px-5 py-3 text-right"><span class="text-on-surface-variant text-[11px]">—</span></td>
                            </tr>
                            <tr v-if="!stats.priority?.length">
                                <td colspan="4" class="px-5 py-8 text-center text-xs text-on-surface-variant italic">Aucun dossier en attente de traitement.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="priorityTotalPages > 1" class="flex items-center justify-between gap-2 px-5 py-3 border-t border-outline-variant">
                    <span class="text-[11px] text-on-surface-variant">Page {{ priorityPage }} / {{ priorityTotalPages }} · {{ stats.priority.length }} dossier(s)</span>
                    <div class="flex items-center gap-1">
                        <button type="button" class="w-8 h-8 rounded-lg border border-outline-variant flex items-center justify-center text-on-surface-variant hover:bg-surface-container-low disabled:opacity-40 disabled:pointer-events-none" :disabled="priorityPage <= 1" @click="priorityPage--"><span class="material-symbols-outlined text-[18px]">chevron_left</span></button>
                        <button type="button" class="w-8 h-8 rounded-lg border border-outline-variant flex items-center justify-center text-on-surface-variant hover:bg-surface-container-low disabled:opacity-40 disabled:pointer-events-none" :disabled="priorityPage >= priorityTotalPages" @click="priorityPage++"><span class="material-symbols-outlined text-[18px]">chevron_right</span></button>
                    </div>
                </div>
            </div>

            <div class="assure-card overflow-hidden flex flex-col">
                <div class="px-5 py-4 border-b border-outline-variant bg-surface-container-low flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary text-[20px]">history</span>
                    <h3 class="font-title-lg text-title-lg">Activité récente</h3>
                </div>
                <div class="p-5 space-y-4 flex-1 max-h-[360px] overflow-y-auto">
                    <div v-for="(a, i) in stats.recentActivity" :key="`${a.ref}-${i}`" class="flex gap-3">
                        <div class="relative flex flex-col items-center">
                            <div class="w-2.5 h-2.5 rounded-full z-10 mt-1" :class="activityDotClass(a.libelle)" />
                            <div v-if="i < stats.recentActivity.length - 1" class="w-0.5 flex-1 bg-outline-variant" />
                        </div>
                        <div class="pb-4">
                            <p class="text-xs font-bold text-on-surface">{{ a.libelle }} — {{ a.beneficiaire }}</p>
                            <p class="text-[11px] text-on-surface-variant mt-0.5">{{ a.ref }} · {{ a.date }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-6">
            <div v-if="can('dossiers.assign') || canSee('direction')" class="lg:col-span-2 assure-card overflow-hidden">
                <div class="px-5 py-4 border-b border-outline-variant bg-surface-container-low flex items-center justify-between gap-3">
                    <h3 class="font-title-lg text-title-lg">Charge par gestionnaire</h3>
                    <Link v-if="can('dossiers.assign')" class="text-primary font-bold text-xs hover:underline shrink-0" :href="route('admin.dossiers')">Affecter des dossiers</Link>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="bg-surface-container-low text-on-surface-variant text-[11px] uppercase tracking-wide">
                                <th class="px-5 py-2.5 font-medium">Gestionnaire</th>
                                <th class="px-5 py-2.5 font-medium text-center">Assignés</th>
                                <th class="px-5 py-2.5 font-medium text-center">À traiter</th>
                                <th class="px-5 py-2.5 font-medium text-center">Validés</th>
                                <th class="px-5 py-2.5 font-medium text-center">En retard</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant">
                            <tr v-for="g in pagedCharges" :key="g.nom" class="hover:bg-surface-container-low transition-colors">
                                <td class="px-5 py-3 text-xs font-bold">{{ g.nom }}</td>
                                <td class="px-5 py-3 text-xs text-center">{{ g.assignes }}</td>
                                <td class="px-5 py-3 text-xs text-center font-bold" :class="g.nonTraites > 0 ? 'text-primary' : 'text-on-surface-variant'">{{ g.nonTraites }}</td>
                                <td class="px-5 py-3 text-xs text-center text-secondary font-bold">{{ g.valides }}</td>
                                <td class="px-5 py-3 text-xs text-center" :class="g.retard > 0 ? 'text-error font-bold' : 'text-on-surface-variant'">{{ g.retard }}</td>
                            </tr>
                        </tbody>
                        <tfoot v-if="canSee('direction') && stats.charges?.length" class="bg-surface-container-low border-t border-outline-variant">
                            <tr>
                                <td class="px-5 py-2.5 text-xs font-bold">Total</td>
                                <td class="px-5 py-2.5 text-xs text-center font-bold">{{ chargeTotals.assignes }}</td>
                                <td class="px-5 py-2.5 text-xs text-center font-bold text-primary">{{ chargeTotals.traiter }}</td>
                                <td class="px-5 py-2.5 text-xs text-center font-bold text-secondary">{{ chargeTotals.valides }}</td>
                                <td class="px-5 py-2.5 text-xs text-center font-bold text-error">{{ chargeTotals.retard }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div v-if="chargesTotalPages > 1" class="flex items-center justify-between gap-2 px-5 py-3 border-t border-outline-variant">
                    <span class="text-[11px] text-on-surface-variant">Page {{ chargesPage }} / {{ chargesTotalPages }} · {{ stats.charges.length }} gestionnaire(s)</span>
                    <div class="flex items-center gap-1">
                        <button type="button" class="w-8 h-8 rounded-lg border border-outline-variant flex items-center justify-center text-on-surface-variant hover:bg-surface-container-low disabled:opacity-40 disabled:pointer-events-none" :disabled="chargesPage <= 1" @click="chargesPage--"><span class="material-symbols-outlined text-[18px]">chevron_left</span></button>
                        <button type="button" class="w-8 h-8 rounded-lg border border-outline-variant flex items-center justify-center text-on-surface-variant hover:bg-surface-container-low disabled:opacity-40 disabled:pointer-events-none" :disabled="chargesPage >= chargesTotalPages" @click="chargesPage++"><span class="material-symbols-outlined text-[18px]">chevron_right</span></button>
                    </div>
                </div>
            </div>

            <div class="space-y-6" :class="(can('dossiers.assign') || canSee('direction')) ? '' : 'lg:col-span-3'">
                <div v-if="can('dossiers.assign') || canSee('direction')" class="assure-card p-5">
                    <h3 class="font-title-lg text-title-lg mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary text-[20px]">pie_chart</span> Répartition par statut
                    </h3>
                    <div class="space-y-3">
                        <div v-for="e in statutBars" :key="e.statut">
                            <div class="flex justify-between text-[11px] mb-1">
                                <span class="font-semibold text-on-surface">{{ e.statut }}</span>
                                <span class="font-bold">{{ e.count }}</span>
                            </div>
                            <div class="w-full h-2 bg-surface-container-high rounded-full overflow-hidden">
                                <div
                                    class="h-full rounded-full transition-all"
                                    :class="STATUT_BAR_COLORS[e.statut] || 'bg-on-surface-variant'"
                                    :style="{ width: `${Math.round((e.count / statutMax) * 100)}%` }"
                                />
                            </div>
                        </div>
                        <p v-if="!statutBars.length" class="text-xs text-on-surface-variant italic">Aucun dossier soumis.</p>
                    </div>
                    <p v-if="statutBars.length" class="text-[10px] text-on-surface-variant mt-4">{{ stats.dossiersTotal }} dossier(s) membre(s) au total (hors brouillons)</p>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
