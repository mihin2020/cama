<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import CamaConfirmModal from '@/Components/CamaConfirmModal.vue';
import FlashMessage from '@/Components/FlashMessage.vue';
import { useCamaConfirm } from '@/Composables/useCamaConfirm';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    roles: { type: Array, default: () => [] },
    permissionsCatalog: { type: Array, default: () => [] },
    minRank: { type: Object, default: () => ({}) },
    grantablePermissions: { type: Array, default: () => [] },
    rangMax: { type: Number, default: 99 },
    actorRole: { type: String, default: '' },
});

const { confirmState, askConfirm, confirm, cancel } = useCamaConfirm();
const formOpen = ref(false);
const editing = ref(null);

const form = useForm({
    nom: '',
    description: '',
    rang: 20,
    permissions: [],
    apply_to_users: false,
});

const isEditing = computed(() => editing.value !== null);
const isAdminRole = computed(() => editing.value?.administrateur === true);
const rangLocked = computed(() => editing.value?.systeme === true);

const CMS_EXPLICIT_ONLY = ['cms.html'];

function openCreate() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    formOpen.value = true;
}

function openEdit(role) {
    editing.value = role;
    form.nom = role.nom;
    form.description = role.description ?? '';
    form.rang = role.rang;
    form.permissions = [...role.permissions];
    form.apply_to_users = false;
    form.clearErrors();
    formOpen.value = true;
}

function closeForm() {
    formOpen.value = false;
    editing.value = null;
}

function impliedByCmsManage(key) {
    return key !== 'cms.manage' && key.startsWith('cms.') && !CMS_EXPLICIT_ONLY.includes(key)
        && form.permissions.includes('cms.manage');
}

function permissionState(key) {
    if (isAdminRole.value) {
        return { disabled: true, reason: "L'administrateur a toujours toutes les permissions." };
    }
    const min = props.minRank[key];
    if (min && Number(form.rang) < min) {
        return { disabled: true, reason: `Réservé aux rôles de rang ${min} ou plus.` };
    }
    if (impliedByCmsManage(key)) {
        return { disabled: true, reason: 'Déjà inclus dans « Accès complet au studio de contenu ».' };
    }
    if (!props.grantablePermissions.includes(key)) {
        return { disabled: true, reason: 'Vous ne possédez pas cette permission.' };
    }
    return { disabled: false, reason: '' };
}

function isChecked(key) {
    return isAdminRole.value || form.permissions.includes(key) || impliedByCmsManage(key);
}

function togglePermission(key) {
    if (permissionState(key).disabled) return;
    form.permissions = form.permissions.includes(key)
        ? form.permissions.filter((p) => p !== key)
        : [...form.permissions, key];
}

function onRangChange() {
    // Les permissions que le nouveau rang n'autorise plus sont retirées.
    form.permissions = form.permissions.filter((key) => !props.minRank[key] || Number(form.rang) >= props.minRank[key]);
}

function save() {
    const options = { preserveScroll: true, onSuccess: closeForm };
    if (isEditing.value) {
        form.put(route('admin.roles.update', editing.value.id), options);
    } else {
        form.post(route('admin.roles.store'), options);
    }
}

function confirmToggle(role) {
    askConfirm({
        title: role.actif ? 'Désactiver ce rôle ?' : 'Activer ce rôle ?',
        message: role.actif
            ? `Les ${role.usersCount} compte(s) ayant le rôle « ${role.nom} » ne pourront plus se connecter tant qu'il reste désactivé.`
            : `Les comptes ayant le rôle « ${role.nom} » pourront de nouveau se connecter.`,
        confirmLabel: role.actif ? 'Désactiver' : 'Activer',
        variant: role.actif ? 'danger' : 'info',
        onConfirm: () => router.post(route('admin.roles.toggle', role.id), {}, { preserveScroll: true }),
    });
}

function confirmDelete(role) {
    askConfirm({
        title: 'Supprimer ce rôle ?',
        message: `Le rôle « ${role.nom} » sera supprimé définitivement.`,
        confirmLabel: 'Supprimer',
        variant: 'danger',
        onConfirm: () => router.delete(route('admin.roles.destroy', role.id), { preserveScroll: true }),
    });
}
</script>

<template>
    <Head title="Rôles et permissions" />

    <AdminLayout active-nav="roles" title="Rôles et permissions" subtitle="Créez des rôles (ex. webmaster) et choisissez leurs permissions">
        <FlashMessage />

        <div class="flex flex-wrap justify-between items-start gap-3 mb-5">
            <p class="text-xs text-on-surface-variant max-w-2xl">
                Les permissions d'un rôle sont proposées automatiquement à la création d'un compte de ce rôle.
                Le <strong>rang</strong> fixe la hiérarchie : un compte ne gère que les comptes d'un rang inférieur ou égal au sien.
            </p>
            <button class="bg-primary text-on-primary px-4 py-2.5 rounded-lg text-xs font-bold flex items-center gap-1.5 hover:opacity-90" type="button" @click="openCreate">
                <span class="material-symbols-outlined text-[16px]">add</span> Nouveau rôle
            </button>
        </div>

        <p v-if="$page.props.errors?.role" class="mb-4 text-xs text-error">{{ $page.props.errors.role }}</p>

        <div class="assure-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="bg-surface-container-low text-on-surface-variant text-[11px] uppercase tracking-wide">
                            <th class="px-5 py-3 font-medium">Rôle</th>
                            <th class="px-5 py-3 font-medium">Rang</th>
                            <th class="px-5 py-3 font-medium hidden md:table-cell">Permissions</th>
                            <th class="px-5 py-3 font-medium hidden md:table-cell">Comptes</th>
                            <th class="px-5 py-3 font-medium">Statut</th>
                            <th class="px-5 py-3 font-medium text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant">
                        <tr v-for="role in roles" :key="role.id" class="hover:bg-surface-container-low transition-colors">
                            <td class="px-5 py-3 text-xs">
                                <p class="font-semibold">
                                    {{ role.nom }}
                                    <span v-if="role.systeme" class="ml-1 px-1.5 py-0.5 rounded bg-surface-container text-[10px] font-bold text-on-surface-variant">Système</span>
                                </p>
                                <p v-if="role.description" class="text-[11px] text-on-surface-variant">{{ role.description }}</p>
                            </td>
                            <td class="px-5 py-3 text-xs font-bold">{{ role.rang }}</td>
                            <td class="px-5 py-3 text-xs hidden md:table-cell">{{ role.permissions.length }}</td>
                            <td class="px-5 py-3 text-xs hidden md:table-cell">{{ role.usersCount }}</td>
                            <td class="px-5 py-3">
                                <span
                                    class="px-2.5 py-1 rounded-full text-[11px] font-bold"
                                    :class="role.actif ? 'bg-secondary text-on-secondary' : 'bg-error/10 text-error'"
                                >{{ role.actif ? 'Actif' : 'Désactivé' }}</span>
                            </td>
                            <td class="px-5 py-3 text-right whitespace-nowrap">
                                <button class="text-on-surface-variant hover:text-primary mr-2" type="button" title="Modifier" @click="openEdit(role)">
                                    <span class="material-symbols-outlined text-[18px]">edit</span>
                                </button>
                                <button
                                    class="text-on-surface-variant hover:text-error mr-2 disabled:opacity-30"
                                    type="button"
                                    :disabled="role.administrateur || role.slug === actorRole"
                                    :title="role.actif ? 'Désactiver' : 'Activer'"
                                    @click="confirmToggle(role)"
                                >
                                    <span class="material-symbols-outlined text-[18px]">{{ role.actif ? 'block' : 'check_circle' }}</span>
                                </button>
                                <button
                                    class="text-on-surface-variant hover:text-error disabled:opacity-30"
                                    type="button"
                                    :disabled="role.systeme || role.usersCount > 0"
                                    :title="role.systeme ? 'Rôle d’origine : non supprimable' : (role.usersCount > 0 ? 'Rôle attribué à des comptes' : 'Supprimer')"
                                    @click="confirmDelete(role)"
                                >
                                    <span class="material-symbols-outlined text-[18px]">delete</span>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="formOpen" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="closeForm">
            <div class="bg-white rounded-xl w-full max-w-2xl max-h-[92vh] overflow-y-auto p-6">
                <h3 class="font-title-lg text-title-lg mb-1">{{ isEditing ? 'Modifier le rôle' : 'Nouveau rôle' }}</h3>
                <p class="text-xs text-on-surface-variant mb-4">
                    {{ isAdminRole ? "Le rôle Administrateur garde toutes les permissions : seuls son nom et sa description sont modifiables." : 'Nommez le rôle, fixez son rang puis cochez ses permissions.' }}
                </p>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="sm:col-span-2">
                        <label class="text-[11px] font-bold uppercase text-on-surface-variant block mb-1">Nom du rôle <span class="text-error">*</span></label>
                        <input v-model="form.nom" class="w-full px-3 py-2 text-xs border border-outline-variant rounded-lg" maxlength="100" placeholder="Ex. Webmaster" type="text" />
                        <p v-if="form.errors.nom" class="text-[10px] text-error mt-1">{{ form.errors.nom }}</p>
                    </div>
                    <div>
                        <label class="text-[11px] font-bold uppercase text-on-surface-variant block mb-1">Rang <span class="text-error">*</span></label>
                        <input
                            v-model.number="form.rang"
                            class="w-full px-3 py-2 text-xs border border-outline-variant rounded-lg disabled:bg-surface-container"
                            :disabled="rangLocked"
                            :max="rangMax"
                            min="1"
                            type="number"
                            @change="onRangChange"
                        />
                        <p v-if="rangLocked" class="text-[10px] text-on-surface-variant mt-1">Rang fixe pour un rôle d'origine.</p>
                        <p v-else class="text-[10px] text-on-surface-variant mt-1">1 à {{ rangMax }} (gestionnaire 10, superviseur 50, direction 70).</p>
                        <p v-if="form.errors.rang" class="text-[10px] text-error mt-1">{{ form.errors.rang }}</p>
                    </div>
                    <div class="sm:col-span-3">
                        <label class="text-[11px] font-bold uppercase text-on-surface-variant block mb-1">Description</label>
                        <input v-model="form.description" class="w-full px-3 py-2 text-xs border border-outline-variant rounded-lg" maxlength="255" placeholder="Ex. Gère le contenu du site public" type="text" />
                    </div>
                </div>

                <div class="mt-5">
                    <p class="text-[11px] font-bold uppercase text-on-surface-variant mb-2">Permissions du rôle</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <div v-for="group in permissionsCatalog" :key="group.group" class="border border-outline-variant rounded-lg p-3">
                            <p class="text-[11px] font-bold uppercase text-on-surface-variant mb-1.5">{{ group.group }}</p>
                            <div class="space-y-1.5">
                                <label
                                    v-for="item in group.items"
                                    :key="item.key"
                                    class="flex items-start gap-2 text-xs"
                                    :class="permissionState(item.key).disabled ? 'opacity-55 cursor-not-allowed' : 'cursor-pointer'"
                                    :title="permissionState(item.key).reason"
                                >
                                    <input
                                        type="checkbox"
                                        class="mt-0.5 rounded border-outline-variant text-primary disabled:cursor-not-allowed"
                                        :checked="isChecked(item.key)"
                                        :disabled="permissionState(item.key).disabled"
                                        @change="togglePermission(item.key)"
                                    />
                                    <span>{{ item.label }}</span>
                                </label>
                            </div>
                        </div>
                    </div>
                    <p v-if="form.errors.permissions" class="text-[11px] text-error mt-2">{{ form.errors.permissions }}</p>
                </div>

                <label v-if="isEditing && !isAdminRole && editing.usersCount > 0" class="mt-4 flex items-start gap-2 text-xs cursor-pointer">
                    <input v-model="form.apply_to_users" class="mt-0.5 rounded border-outline-variant text-primary" type="checkbox" />
                    <span>
                        Appliquer aussi ces permissions aux {{ editing.usersCount }} compte(s) existant(s) de ce rôle
                        <span class="block text-[10px] text-on-surface-variant">Sinon, seuls les nouveaux comptes reçoivent les permissions modifiées.</span>
                    </span>
                </label>

                <div class="flex justify-end gap-2 mt-6">
                    <button class="px-4 py-2 text-xs rounded-lg border border-outline" type="button" @click="closeForm">Annuler</button>
                    <button class="px-4 py-2 text-xs rounded-lg bg-primary text-on-primary font-bold disabled:opacity-60" type="button" :disabled="form.processing" @click="save">
                        {{ isEditing ? 'Enregistrer' : 'Créer le rôle' }}
                    </button>
                </div>
            </div>
        </div>

        <CamaConfirmModal
            :show="confirmState.show"
            :title="confirmState.title"
            :message="confirmState.message"
            :confirm-label="confirmState.confirmLabel"
            :cancel-label="confirmState.cancelLabel"
            :variant="confirmState.variant"
            :alert-only="confirmState.alertOnly"
            @confirm="confirm"
            @cancel="cancel"
        />
    </AdminLayout>
</template>
