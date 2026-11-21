<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import CamaConfirmModal from '@/Components/CamaConfirmModal.vue';
import PermissionPicker from '@/Components/Admin/PermissionPicker.vue';
import { useCamaConfirm } from '@/Composables/useCamaConfirm';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    roles: { type: Array, default: () => [] },
    permissionsCatalog: { type: Array, default: () => [] },
    restrictions: { type: Object, default: () => ({}) },
    customRank: { type: Number, default: 10 },
    grantablePermissions: { type: Array, default: () => [] },
    actorRole: { type: String, default: '' },
});

const { confirmState, askConfirm, confirm, cancel } = useCamaConfirm();
const formOpen = ref(false);
const editing = ref(null);

// Même message que les autres écrans du back-office : bandeau sombre en bas à droite.
const toast = ref(null);
let toastTimer = null;
function showToast(message, icon = 'check_circle') {
    if (!message) return;
    toast.value = { message, icon };
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { toast.value = null; }, 3500);
}
const toastFromFlash = (page) => showToast(page.props.flash?.success ?? page.props.flash?.warning);
const toastFromErrors = (errors) => showToast(Object.values(errors)[0], 'error');

const form = useForm({
    nom: '',
    description: '',
    permissions: [],
    apply_to_users: false,
});

const isEditing = computed(() => editing.value !== null);
const isAdminRole = computed(() => editing.value?.administrateur === true);
// Niveau interne du rôle édité (un rôle créé est au niveau d'un gestionnaire) : non affiché.
const roleRank = computed(() => editing.value?.rang ?? props.customRank);

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
    form.permissions = [...role.permissions];
    form.apply_to_users = false;
    form.clearErrors();
    formOpen.value = true;
}

function closeForm() {
    formOpen.value = false;
    editing.value = null;
}

function permissionReason(key) {
    const restriction = props.restrictions[key];
    if (restriction && roleRank.value < restriction.minRank) return restriction.label;
    if (!props.grantablePermissions.includes(key)) return 'Vous ne possédez pas cette permission.';
    return '';
}

function save() {
    const options = {
        preserveScroll: true,
        onSuccess: (page) => {
            closeForm();
            toastFromFlash(page);
        },
    };
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
        onConfirm: () => router.post(route('admin.roles.toggle', role.id), {}, {
            preserveScroll: true,
            onSuccess: toastFromFlash,
            onError: toastFromErrors,
        }),
    });
}

// Suppression d'un rôle d'origine : fenêtre dédiée, avec le mot de passe actuel de l'administrateur.
const deleting = ref(null);
const deleteForm = useForm({ password: '' });

function openSystemDelete(role) {
    deleting.value = role;
    deleteForm.reset();
    deleteForm.clearErrors();
}

function closeSystemDelete() {
    deleting.value = null;
    deleteForm.reset();
}

function submitSystemDelete() {
    deleteForm.delete(route('admin.roles.destroy', deleting.value.id), {
        preserveScroll: true,
        onSuccess: (page) => {
            closeSystemDelete();
            toastFromFlash(page);
        },
        onError: (errors) => {
            if (!errors.password) {
                closeSystemDelete();
                toastFromErrors(errors);
            }
        },
        onFinish: () => deleteForm.reset('password'),
    });
}

function deleteTitle(role) {
    if (role.administrateur) return 'Le rôle Administrateur ne peut pas être supprimé';
    if (role.usersCount > 0) return `Attribué à ${role.usersCount} compte(s) : changez d'abord leur rôle`;
    return 'Supprimer';
}

function askDelete(role) {
    if (role.systeme) {
        openSystemDelete(role);
        return;
    }
    confirmDelete(role);
}

function confirmDelete(role) {
    askConfirm({
        title: 'Supprimer ce rôle ?',
        message: `Le rôle « ${role.nom} » sera supprimé définitivement.`,
        confirmLabel: 'Supprimer',
        variant: 'danger',
        onConfirm: () => router.delete(route('admin.roles.destroy', role.id), {
            preserveScroll: true,
            onSuccess: toastFromFlash,
            onError: toastFromErrors,
        }),
    });
}
</script>

<template>
    <Head title="Rôles et permissions" />

    <AdminLayout active-nav="roles" title="Rôles et permissions" subtitle="Créez des rôles (ex. webmaster) et choisissez leurs permissions">
        <div class="flex flex-wrap justify-between items-start gap-3 mb-5">
            <p class="text-xs text-on-surface-variant max-w-2xl">
                Les permissions se règlent ici, sur le rôle. Dans « Comptes internes », on choisit simplement le rôle d'un compte :
                il reçoit ses permissions, et toute modification du rôle s'applique aussitôt à ses comptes.
            </p>
            <button class="bg-primary text-on-primary px-4 py-2.5 rounded-lg text-xs font-bold flex items-center gap-1.5 hover:opacity-90" type="button" @click="openCreate">
                <span class="material-symbols-outlined text-[16px]">add</span> Nouveau rôle
            </button>
        </div>

        <div class="assure-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="bg-surface-container-low text-on-surface-variant text-[11px] uppercase tracking-wide">
                            <th class="px-5 py-3 font-medium">Rôle</th>
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
                                    :disabled="role.administrateur || role.usersCount > 0"
                                    :title="deleteTitle(role)"
                                    @click="askDelete(role)"
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
                    {{ isAdminRole ? "Le rôle Administrateur garde toutes les permissions : seuls son nom et sa description sont modifiables." : 'Nommez le rôle puis cochez ses permissions.' }}
                </p>

                <div class="grid grid-cols-1 gap-3">
                    <div>
                        <label class="text-[11px] font-bold uppercase text-on-surface-variant block mb-1">Nom du rôle <span class="text-error">*</span></label>
                        <input v-model="form.nom" class="w-full px-3 py-2 text-xs border border-outline-variant rounded-lg" maxlength="100" placeholder="Ex. Webmaster" type="text" />
                        <p v-if="form.errors.nom" class="text-[10px] text-error mt-1">{{ form.errors.nom }}</p>
                    </div>
                    <div>
                        <label class="text-[11px] font-bold uppercase text-on-surface-variant block mb-1">Description</label>
                        <input v-model="form.description" class="w-full px-3 py-2 text-xs border border-outline-variant rounded-lg" maxlength="255" placeholder="Ex. Gère le contenu du site public" type="text" />
                    </div>
                </div>

                <div class="mt-5">
                    <p class="text-[11px] font-bold uppercase text-on-surface-variant mb-2">Permissions du rôle</p>
                    <PermissionPicker
                        v-model="form.permissions"
                        :all-locked="isAdminRole"
                        :catalog="permissionsCatalog"
                        :disabled-reason="permissionReason"
                    />
                    <p v-if="form.errors.permissions" class="text-[11px] text-error mt-2">{{ form.errors.permissions }}</p>
                </div>

                <p v-if="isEditing && !isAdminRole && editing.usersCount > 0" class="mt-4 rounded-lg bg-surface-container-low p-3 text-xs">
                    <strong>{{ editing.usersCount }}</strong> compte(s) ont ce rôle : ils recevront ces permissions dès l'enregistrement.
                </p>

                <div class="flex justify-end gap-2 mt-6">
                    <button class="px-4 py-2 text-xs rounded-lg border border-outline" type="button" @click="closeForm">Annuler</button>
                    <button class="px-4 py-2 text-xs rounded-lg bg-primary text-on-primary font-bold disabled:opacity-60" type="button" :disabled="form.processing" @click="save">
                        {{ isEditing ? 'Enregistrer' : 'Créer le rôle' }}
                    </button>
                </div>
            </div>
        </div>

        <div v-if="deleting" class="fixed inset-0 bg-black/50 z-[60] flex items-center justify-center p-4" @click.self="closeSystemDelete">
            <form class="bg-white rounded-xl w-full max-w-md p-6" @submit.prevent="submitSystemDelete">
                <div class="flex items-start gap-3 mb-3">
                    <span class="material-symbols-outlined text-error text-[28px]">warning</span>
                    <div>
                        <h3 class="font-title-lg text-title-lg">Supprimer le rôle « {{ deleting.nom }} » ?</h3>
                        <p class="text-xs text-on-surface-variant mt-1">
                            C'est un rôle d'origine de la plateforme. Sa suppression est définitive.
                            Pour confirmer, saisissez <strong>votre mot de passe actuel</strong>.
                        </p>
                    </div>
                </div>
                <label class="text-[11px] font-bold uppercase text-on-surface-variant block mb-1" for="role-delete-password">Mot de passe</label>
                <input
                    id="role-delete-password"
                    v-model="deleteForm.password"
                    autocomplete="current-password"
                    autofocus
                    class="w-full px-3 py-2 text-xs border border-outline-variant rounded-lg"
                    required
                    type="password"
                />
                <p v-if="deleteForm.errors.password" class="text-[10px] text-error mt-1">{{ deleteForm.errors.password }}</p>
                <div class="flex justify-end gap-2 mt-6">
                    <button class="px-4 py-2 text-xs rounded-lg border border-outline" type="button" @click="closeSystemDelete">Annuler</button>
                    <button
                        class="px-4 py-2 text-xs rounded-lg bg-error text-on-error font-bold disabled:opacity-60"
                        type="submit"
                        :disabled="deleteForm.processing || !deleteForm.password"
                    >Supprimer définitivement</button>
                </div>
            </form>
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

        <div v-if="toast" class="fixed bottom-6 right-6 z-[70]">
            <div class="bg-on-background text-white px-5 py-3 rounded-lg shadow-xl flex items-center gap-2 text-sm font-semibold">
                <span class="material-symbols-outlined">{{ toast.icon }}</span> {{ toast.message }}
            </div>
        </div>
    </AdminLayout>
</template>
