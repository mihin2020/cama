<script setup>
import CmsLayout from '@/Layouts/CmsLayout.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    tab: { type: String, default: 'overview' },
    items: { type: Array, default: () => [] },
    batches: { type: Array, default: () => [] },
    campaigns: { type: Array, default: () => [] },
    templates: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
    segments: { type: Object, default: () => ({}) },
    emailStatuses: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
});

const page = usePage();
const toast = computed(() => page.props.flash?.success ?? null);
const activeTab = ref(props.tab || 'overview');
const search = ref(props.filters?.q ?? '');
const status = ref(props.filters?.status ?? '');
const segment = ref(props.filters?.segment ?? '');
const selectedIds = ref([]);
const selectedBatchIds = ref([]);
const editingSubscriber = ref(null);
let timer = null;

const batchForm = useForm({ name: '', description: '', subscriber_ids: [] });
const subscriberForm = useForm({ full_name: '', segment: 'visiteur', email_status: 'active' });
const cleanForm = useForm({ type: 'invalid', months: 12 });

const tabs = [
    { key: 'overview', label: 'Vue d\'ensemble', icon: 'monitoring' },
    { key: 'subscribers', label: 'Abonnés', icon: 'group' },
    { key: 'campaigns', label: 'Campagnes', icon: 'campaign' },
    { key: 'templates', label: 'Modèles', icon: 'dashboard_customize' },
];

watch([status, segment], applyFilters);

watch(
    () => props.tab,
    (value) => {
        if (value) activeTab.value = value;
    },
);

function switchTab(key) {
    activeTab.value = key;
    router.get(route('admin.cms.newsletter'), { tab: key, q: search.value || undefined, status: status.value || undefined, segment: segment.value || undefined }, { preserveState: true, replace: true });
}

function onSearch() {
    clearTimeout(timer);
    timer = setTimeout(applyFilters, 250);
}

function applyFilters() {
    router.get(route('admin.cms.newsletter'), { tab: activeTab.value, q: search.value || undefined, status: status.value || undefined, segment: segment.value || undefined }, { preserveState: true, preserveScroll: true, replace: true });
}

function toggleSelect(item) {
    if (item.emailStatus !== 'active' && item.status !== 'active') return;
    const i = selectedIds.value.indexOf(item.id);
    if (i >= 0) selectedIds.value.splice(i, 1);
    else selectedIds.value.push(item.id);
}

function createBatch() {
    batchForm.subscriber_ids = [...selectedIds.value];
    if (!batchForm.name.trim() || !batchForm.subscriber_ids.length) {
        window.alert('Nom du lot et au moins un abonné actif requis.');
        return;
    }
    batchForm.post(route('admin.cms.newsletter.batches.store'), { preserveScroll: true, onSuccess: () => { batchForm.reset(); selectedIds.value = []; } });
}

function deleteBatch(batch) {
    if (!window.confirm(`Supprimer le lot « ${batch.name} » ?`)) return;
    router.delete(route('admin.cms.newsletter.batches.destroy', batch.id), { preserveScroll: true });
}

function openEditSubscriber(item) {
    editingSubscriber.value = item.id;
    subscriberForm.full_name = item.fullName || '';
    subscriberForm.segment = item.segment || 'visiteur';
    subscriberForm.email_status = item.emailStatus || 'active';
}

function saveSubscriber(item) {
    subscriberForm.put(route('admin.cms.newsletter.subscribers.update', item.id), {
        preserveScroll: true,
        onSuccess: () => { editingSubscriber.value = null; },
    });
}

function cleanList(type) {
    cleanForm.type = type;
    cleanForm.post(route('admin.cms.newsletter.clean'), { preserveScroll: true });
}

function toggle(item) {
    router.put(route('admin.cms.newsletter.toggle', item.id), {}, { preserveScroll: true });
}

function deleteCampaign(campaign) {
    if (!window.confirm(`Supprimer la campagne « ${campaign.name} » ?`)) return;
    router.delete(route('admin.cms.newsletter.campaigns.destroy', campaign.id), { preserveScroll: true });
}

function inBatch(item) {
    return (item.batchNames ?? []).length > 0;
}
</script>

<template>
    <Head title="Newsletter" />
    <CmsLayout active-nav="newsletter" title="Newsletter CAMA" subtitle="CRM abonnés, campagnes HTML, statistiques d'ouverture et de clics">
        <div v-if="toast" class="rounded-xl border border-secondary/30 bg-secondary/10 px-4 py-3 text-sm text-secondary font-semibold mb-4">{{ toast }}</div>

        <div class="flex flex-wrap gap-2 mb-4">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                type="button"
                class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-bold border transition-colors"
                :class="activeTab === tab.key ? 'bg-primary text-on-primary border-primary' : 'border-outline-variant text-on-surface-variant'"
                @click="switchTab(tab.key)"
            >
                <span class="material-symbols-outlined text-[16px]">{{ tab.icon }}</span>
                {{ tab.label }}
            </button>
            <Link :href="route('admin.cms.newsletter.campaigns.create')" class="ml-auto inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-primary text-on-primary text-xs font-bold">
                <span class="material-symbols-outlined text-[16px]">add</span>
                Nouvelle campagne
            </Link>
        </div>

        <!-- Vue d'ensemble -->
        <div v-if="activeTab === 'overview'" class="space-y-4">
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="assure-card p-4">
                    <p class="text-xs text-on-surface-variant uppercase font-bold">Abonnés actifs</p>
                    <p class="text-3xl font-bold text-primary mt-1">{{ stats.activeSubscribers ?? 0 }}</p>
                </div>
                <div class="assure-card p-4">
                    <p class="text-xs text-on-surface-variant uppercase font-bold">Campagnes envoyées</p>
                    <p class="text-3xl font-bold text-primary mt-1">{{ stats.totalCampaigns ?? 0 }}</p>
                </div>
                <div class="assure-card p-4">
                    <p class="text-xs text-on-surface-variant uppercase font-bold">Taux ouverture moy.</p>
                    <p class="text-3xl font-bold text-secondary mt-1">{{ stats.avgOpenRate ?? 0 }}%</p>
                </div>
                <div class="assure-card p-4">
                    <p class="text-xs text-on-surface-variant uppercase font-bold">Taux clic moy.</p>
                    <p class="text-3xl font-bold text-secondary mt-1">{{ stats.avgClickRate ?? 0 }}%</p>
                </div>
            </div>
            <section class="assure-card p-4">
                <p class="text-xs uppercase tracking-[0.18em] text-primary font-bold mb-3">Dernières campagnes</p>
                <div v-if="campaigns.length" class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-on-surface-variant text-xs"><tr><th class="text-left py-2">Campagne</th><th class="text-left py-2">Envoyés</th><th class="text-left py-2">Ouvertures</th><th class="text-left py-2">Clics</th><th class="text-left py-2">Taux</th></tr></thead>
                        <tbody>
                            <tr v-for="c in campaigns.slice(0, 5)" :key="c.id" class="border-t border-outline-variant">
                                <td class="py-2 font-semibold">{{ c.name }}</td>
                                <td class="py-2">{{ c.sentCount }}</td>
                                <td class="py-2">{{ c.openCount }}</td>
                                <td class="py-2">{{ c.clickCount }}</td>
                                <td class="py-2">{{ c.openRate }}% / {{ c.clickRate }}%</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else class="text-sm text-on-surface-variant italic">Aucune campagne envoyée.</p>
            </section>

            <section class="assure-card p-4">
                <div class="flex items-center justify-between gap-3 mb-3">
                    <p class="text-xs uppercase tracking-[0.18em] text-primary font-bold">Modèles CAMA prêts à l'emploi</p>
                    <button type="button" class="text-xs font-bold text-primary" @click="switchTab('templates')">Voir tout →</button>
                </div>
                <div v-if="templates.length" class="grid md:grid-cols-3 gap-3">
                    <div v-for="template in templates" :key="template.id" class="rounded-xl border border-outline-variant p-4 bg-surface-container-low">
                        <p class="font-bold text-sm">{{ template.name }}</p>
                        <p class="text-xs text-on-surface-variant mt-1 line-clamp-2">{{ template.description }}</p>
                        <Link :href="route('admin.cms.newsletter.campaigns.create', { template: template.id })" class="inline-flex items-center gap-1 mt-3 text-xs font-bold text-primary">
                            <span class="material-symbols-outlined text-[16px]">dashboard_customize</span>
                            Utiliser ce modèle
                        </Link>
                    </div>
                </div>
                <p v-else class="text-sm text-on-surface-variant italic">Aucun modèle — exécutez les migrations (<code class="text-xs">php artisan migrate</code>).</p>
            </section>
        </div>

        <!-- Abonnés -->
        <div v-else-if="activeTab === 'subscribers'" class="space-y-4">
            <div class="flex flex-wrap gap-2 items-center">
                <input v-model="search" class="px-3 py-2 border border-outline-variant rounded-lg text-sm w-64" placeholder="Rechercher…" @input="onSearch" />
                <select v-model="status" class="px-3 py-2 border border-outline-variant rounded-lg text-sm">
                    <option value="">Tous statuts</option>
                    <option value="active">Actifs</option>
                    <option value="unsubscribed">Désinscrits</option>
                    <option value="invalid">Invalides</option>
                    <option value="bounced">Rebonds</option>
                </select>
                <select v-model="segment" class="px-3 py-2 border border-outline-variant rounded-lg text-sm">
                    <option value="">Tous segments</option>
                    <option v-for="(label, key) in segments" :key="key" :value="key">{{ label }}</option>
                </select>
                <a :href="route('admin.cms.newsletter.export', { status: status || undefined })" class="px-3 py-2 rounded-lg bg-primary text-on-primary text-xs font-bold">Export CSV</a>
                <button class="px-3 py-2 border border-outline-variant rounded-lg text-xs font-bold" type="button" @click="cleanList('invalid')">Nettoyer emails invalides</button>
                <button class="px-3 py-2 border border-outline-variant rounded-lg text-xs font-bold" type="button" @click="cleanList('inactive')">Marquer inactifs (12 mois)</button>
            </div>

            <section class="assure-card p-4 space-y-3">
                <p class="text-xs uppercase tracking-[0.18em] text-primary font-bold">Lots / segments manuels</p>
                <div class="grid md:grid-cols-3 gap-2">
                    <input v-model="batchForm.name" class="px-3 py-2 border border-outline-variant rounded-lg text-sm" placeholder="Nom du lot" />
                    <input v-model="batchForm.description" class="px-3 py-2 border border-outline-variant rounded-lg text-sm" placeholder="Description" />
                    <button class="px-3 py-2 bg-secondary text-on-secondary rounded-lg text-sm font-bold disabled:opacity-60" :disabled="!selectedIds.length" type="button" @click="createBatch">Créer lot ({{ selectedIds.length }} sélectionné(s))</button>
                </div>
                <div v-if="batches.length" class="flex flex-wrap gap-2">
                    <span v-for="batch in batches" :key="batch.id" class="inline-flex items-center gap-1 rounded-full border border-outline-variant px-3 py-1 text-xs font-bold">
                        {{ batch.name }} ({{ batch.count }})
                        <button type="button" class="material-symbols-outlined text-[14px] text-error" @click="deleteBatch(batch)">close</button>
                    </span>
                </div>
            </section>

            <section class="assure-card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-surface-container-low border-b border-outline-variant text-on-surface-variant text-xs">
                            <tr>
                                <th class="px-3 py-2 w-8"></th>
                                <th class="px-3 py-2 text-left">Email</th>
                                <th class="px-3 py-2 text-left">Nom</th>
                                <th class="px-3 py-2 text-left">Segment</th>
                                <th class="px-3 py-2 text-left">Lots</th>
                                <th class="px-3 py-2 text-left">Engagement</th>
                                <th class="px-3 py-2 text-left">Statut</th>
                                <th class="px-3 py-2 text-left">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in items" :key="item.id" class="border-b border-outline-variant" :class="inBatch(item) ? 'bg-secondary/5' : ''">
                                <td class="px-3 py-2">
                                    <input v-if="item.emailStatus === 'active'" type="checkbox" :checked="selectedIds.includes(item.id)" @change="toggleSelect(item)" />
                                </td>
                                <td class="px-3 py-2 font-semibold">{{ item.email }}</td>
                                <td class="px-3 py-2">
                                    <input v-if="editingSubscriber === item.id" v-model="subscriberForm.full_name" class="w-full px-2 py-1 border rounded text-xs" />
                                    <span v-else>{{ item.fullName || '—' }}</span>
                                </td>
                                <td class="px-3 py-2">
                                    <select v-if="editingSubscriber === item.id" v-model="subscriberForm.segment" class="px-2 py-1 border rounded text-xs">
                                        <option v-for="(label, key) in segments" :key="key" :value="key">{{ label }}</option>
                                    </select>
                                    <span v-else class="text-xs">{{ item.segmentLabel }}</span>
                                </td>
                                <td class="px-3 py-2">
                                    <span v-for="name in item.batchNames" :key="name" class="text-[10px] px-1.5 py-0.5 rounded-full bg-secondary/10 text-secondary font-bold mr-1">{{ name }}</span>
                                    <span v-if="!item.batchNames?.length" class="text-xs text-on-surface-variant">—</span>
                                </td>
                                <td class="px-3 py-2 text-xs text-on-surface-variant">{{ item.openCount }} ouv. · {{ item.clickCount }} clics</td>
                                <td class="px-3 py-2"><span class="text-[10px] px-2 py-0.5 rounded-full font-bold bg-primary/10 text-primary">{{ item.emailStatusLabel }}</span></td>
                                <td class="px-3 py-2 whitespace-nowrap">
                                    <template v-if="editingSubscriber === item.id">
                                        <button class="text-xs font-bold text-primary mr-2" type="button" @click="saveSubscriber(item)">OK</button>
                                        <button class="text-xs font-bold" type="button" @click="editingSubscriber = null">Annuler</button>
                                    </template>
                                    <template v-else>
                                        <button class="text-xs font-bold mr-2" type="button" @click="openEditSubscriber(item)">Modifier</button>
                                        <button class="text-xs font-bold" type="button" @click="toggle(item)">{{ item.status === 'active' ? 'Désinscrire' : 'Réactiver' }}</button>
                                    </template>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <!-- Campagnes -->
        <div v-else-if="activeTab === 'campaigns'" class="space-y-4">
            <section class="assure-card overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-surface-container-low border-b border-outline-variant text-on-surface-variant text-xs">
                        <tr>
                            <th class="px-4 py-3 text-left">Campagne</th>
                            <th class="px-4 py-3 text-left">Objet</th>
                            <th class="px-4 py-3 text-left">Statut</th>
                            <th class="px-4 py-3 text-left">Envoyés</th>
                            <th class="px-4 py-3 text-left">Ouvertures</th>
                            <th class="px-4 py-3 text-left">Clics</th>
                            <th class="px-4 py-3 text-left">Taux O/C</th>
                            <th class="px-4 py-3 text-left">Date</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="campaign in campaigns" :key="campaign.id" class="border-b border-outline-variant">
                            <td class="px-4 py-3 font-bold">{{ campaign.name }}</td>
                            <td class="px-4 py-3 text-on-surface-variant">{{ campaign.subject }}</td>
                            <td class="px-4 py-3"><span class="text-[10px] px-2 py-0.5 rounded-full font-bold" :class="campaign.status === 'sent' ? 'bg-secondary/10 text-secondary' : 'bg-tertiary-container/40 text-tertiary'">{{ campaign.statusLabel }}</span></td>
                            <td class="px-4 py-3">{{ campaign.sentCount }}</td>
                            <td class="px-4 py-3">{{ campaign.openCount }} <span class="text-xs text-on-surface-variant">({{ campaign.openRate }}%)</span></td>
                            <td class="px-4 py-3">{{ campaign.clickCount }} <span class="text-xs text-on-surface-variant">({{ campaign.clickRate }}%)</span></td>
                            <td class="px-4 py-3 text-xs">{{ campaign.openRate }}% / {{ campaign.clickRate }}%</td>
                            <td class="px-4 py-3 text-xs text-on-surface-variant">{{ campaign.sentAt || campaign.createdAt }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <Link :href="route('admin.cms.newsletter.campaigns.edit', campaign.id)" class="text-xs font-bold text-primary mr-2">Ouvrir</Link>
                                <button v-if="campaign.status !== 'sent'" class="text-xs font-bold text-error" type="button" @click="deleteCampaign(campaign)">Supprimer</button>
                            </td>
                        </tr>
                        <tr v-if="!campaigns.length"><td colspan="9" class="px-4 py-8 text-center text-on-surface-variant">Aucune campagne.</td></tr>
                    </tbody>
                </table>
            </section>
        </div>

        <!-- Modèles -->
        <div v-else-if="activeTab === 'templates'" class="grid md:grid-cols-3 gap-4">
            <div v-for="template in templates" :key="template.id" class="assure-card p-4 space-y-2">
                <p class="font-bold text-sm">{{ template.name }}</p>
                <p class="text-xs text-on-surface-variant">{{ template.description }}</p>
                <p class="text-[10px] text-on-surface-variant">{{ (template.blocksJson ?? []).length }} bloc(s)</p>
                <Link :href="route('admin.cms.newsletter.campaigns.create', { template: template.id })" class="inline-block text-xs font-bold text-primary mt-2">Créer une campagne avec ce modèle →</Link>
            </div>
        </div>
    </CmsLayout>
</template>
