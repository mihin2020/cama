<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    logs: Object,
    users: Array,
    filters: Object,
    retentionDays: Number,
});

const action = ref(props.filters.action ?? '');
const adminUserId = ref(props.filters.admin_user_id ?? '');

watch([action, adminUserId], () => {
    router.get(
        route('admin.audit'),
        {
            action: action.value || undefined,
            admin_user_id: adminUserId.value || undefined,
        },
        { preserveState: true, replace: true, preserveScroll: true },
    );
});

function actionIcon(type) {
    return type === 'connexion' ? 'login' : 'logout';
}

function actionClass(type) {
    return type === 'connexion' ? 'text-primary' : 'text-on-surface-variant';
}
</script>

<template>
    <Head title="Connexions" />

    <AdminLayout active-nav="audit" title="Connexions" :subtitle="`Connexions et déconnexions — conservation ${retentionDays} jours`">
        <div class="max-w-[1100px] w-full mx-auto">
            <div class="assure-card p-4 mb-5 flex flex-wrap gap-3 items-center">
                <select v-model="action" class="px-3 py-2 text-xs border border-outline-variant rounded-lg bg-white">
                    <option value="">Toutes les actions</option>
                    <option value="connexion">Connexion</option>
                    <option value="deconnexion">Déconnexion</option>
                </select>
                <select v-model="adminUserId" class="px-3 py-2 text-xs border border-outline-variant rounded-lg bg-white">
                    <option value="">Tous les utilisateurs</option>
                    <option v-for="user in users" :key="user.id" :value="String(user.id)">{{ user.label }}</option>
                </select>
                <p class="ml-auto text-[11px] text-on-surface-variant italic">
                    Lecture seule — aucune autre action n'est enregistrée.
                </p>
            </div>

            <div class="assure-card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="bg-surface-container-low text-on-surface-variant text-[11px] uppercase tracking-wide">
                                <th class="px-5 py-3 font-medium">Action</th>
                                <th class="px-5 py-3 font-medium hidden md:table-cell">Utilisateur</th>
                                <th class="px-5 py-3 font-medium hidden lg:table-cell">Adresse IP</th>
                                <th class="px-5 py-3 font-medium">Horodatage</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant">
                            <tr v-for="log in logs.data" :key="log.id" class="hover:bg-surface-container-low transition-colors">
                                <td class="px-5 py-3 text-xs">
                                    <span class="inline-flex items-center gap-1.5 font-bold" :class="actionClass(log.action)">
                                        <span class="material-symbols-outlined text-[16px]">{{ actionIcon(log.action) }}</span>
                                        {{ log.actionLabel }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-xs text-on-surface-variant hidden md:table-cell">{{ log.utilisateur }}</td>
                                <td class="px-5 py-3 text-xs text-on-surface-variant hidden lg:table-cell">{{ log.ipAddress || '—' }}</td>
                                <td class="px-5 py-3 text-xs text-on-surface-variant">{{ log.date }}</td>
                            </tr>
                            <tr v-if="!logs.data.length">
                                <td colspan="4" class="px-5 py-10 text-center text-on-surface-variant text-sm">Aucune connexion enregistrée pour ces filtres.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="logs.last_page > 1" class="px-5 py-3 border-t border-outline-variant flex justify-center gap-2">
                    <button
                        v-for="link in logs.links"
                        :key="link.label"
                        type="button"
                        class="px-3 py-1 text-xs rounded-lg border border-outline-variant disabled:opacity-40"
                        :class="link.active ? 'bg-primary text-on-primary border-primary' : 'bg-white text-on-surface'"
                        :disabled="!link.url"
                        v-html="link.label"
                        @click="link.url && router.get(link.url, {}, { preserveState: true, preserveScroll: true })"
                    />
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
