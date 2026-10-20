<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import CamaConfirmModal from '@/Components/CamaConfirmModal.vue';
import CamaPagination from '@/Components/CamaPagination.vue';
import FlashMessage from '@/Components/FlashMessage.vue';
import { useCamaConfirm } from '@/Composables/useCamaConfirm';
import { useServerPagination } from '@/Composables/usePagination';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    users: Array,
    pagination: { type: Object, default: () => ({ currentPage: 1, lastPage: 1, perPage: 20, total: 0 }) },
    search: { type: String, default: '' },
    permissionsCatalog: Array,
    rolePresets: Object,
    grades: Array,
    assignableRoles: Array,
    grantablePermissions: { type: Array, default: () => [] },
    roleRestricted: { type: Object, default: () => ({}) },
    actorId: { type: Number, default: null },
    actorIsAdmin: { type: Boolean, default: false },
});

const search = ref(props.search);
const listTop = ref(null);
const { reload, goToPage, setPerPage } = useServerPagination(() => props.pagination, {
    routeName: 'admin.utilisateurs',
    params: () => ({ q: search.value.trim() }),
    anchor: listTop,
});
let searchTimer = null;
watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(reload, 300);
});

const page = usePage();
const { confirmState, askConfirm, confirm, cancel } = useCamaConfirm();
const formOpen = ref(false);
const editingUser = ref(null);
const resendingId = ref(null);
const toast = ref(null);

const ROLE_BADGE = {
    Gestionnaire: 'bg-primary/10 text-primary',
    Superviseur: 'bg-tertiary/10 text-tertiary',
    Administrateur: 'bg-secondary/10 text-secondary',
    Direction: 'bg-on-background/10 text-on-background',
};

const STATUT_BADGE = {
    Actif: 'bg-secondary text-on-secondary',
    Invité: 'bg-tertiary text-on-tertiary',
    Désactivé: 'bg-error/10 text-error',
};

const form = useForm({
    prenom: '',
    nom: '',
    email: '',
    grade: '',
    role: 'gestionnaire',
    permissions: [],
    matricule_interne: '',
});

const isEditing = computed(() => editingUser.value !== null);

function showToast(message) {
    toast.value = message;
    setTimeout(() => { toast.value = null; }, 3500);
}

function openCreate() {
    editingUser.value = null;
    form.reset();
    form.clearErrors();
    form.role = 'gestionnaire';
    applyRolePreset();
    formOpen.value = true;
}

function openEdit(user) {
    if (!user.canManage) return;
    editingUser.value = user;
    form.prenom = user.prenom;
    form.nom = user.nom;
    form.email = user.email;
    form.grade = user.grade ?? '';
    form.role = user.role;
    form.permissions = [...(user.permissions ?? [])];
    form.matricule_interne = '';
    form.clearErrors();
    formOpen.value = true;
}

function closeForm() {
    formOpen.value = false;
    editingUser.value = null;
}

/* Règles d'attribution (vérifiées aussi par le serveur) : on ne donne que ce qu'on possède,
   certaines permissions sont réservées à des rôles, et ses propres droits ne se modifient pas. */
const CMS_EXPLICIT_ONLY = ['cms.html'];
const isSelf = computed(() => isEditing.value && editingUser.value?.id === props.actorId);

function allowedForRole(key, role = form.role) {
    return !props.roleRestricted[key] || props.roleRestricted[key].includes(role);
}

function impliedByCmsManage(key) {
    return key !== 'cms.manage' && key.startsWith('cms.') && !CMS_EXPLICIT_ONLY.includes(key)
        && form.permissions.includes('cms.manage');
}

function permissionState(key) {
    if (isSelf.value && !props.actorIsAdmin) {
        return { disabled: true, reason: 'Vos propres droits ne peuvent être modifiés que par un administrateur.' };
    }
    if (!allowedForRole(key)) {
        return { disabled: true, reason: 'Réservé aux rôles superviseur et administrateur.' };
    }
    if (impliedByCmsManage(key)) {
        return { disabled: true, reason: 'Déjà inclus dans « Accès complet au studio de contenu ».' };
    }
    const alreadyHad = isEditing.value && (editingUser.value?.permissions ?? []).includes(key);
    if (!props.grantablePermissions.includes(key) && !alreadyHad) {
        return { disabled: true, reason: 'Vous ne possédez pas cette permission : vous ne pouvez pas l’attribuer.' };
    }
    return { disabled: false, reason: '' };
}

function applyRolePreset() {
    form.permissions = (props.rolePresets[form.role] ?? [])
        .filter((key) => allowedForRole(key) && props.grantablePermissions.includes(key));
}

watch(() => form.role, () => {
    if (!formOpen.value) return;
    if (!isEditing.value) {
        applyRolePreset();
    } else {
        // Changement de rôle : on retire ce qui n'est pas permis pour le nouveau rôle.
        form.permissions = form.permissions.filter((key) => allowedForRole(key));
    }
});

function togglePermission(key) {
    if (permissionState(key).disabled) return;
    if (form.permissions.includes(key)) {
        form.permissions = form.permissions.filter((p) => p !== key);
    } else {
        form.permissions = [...form.permissions, key];
    }
}

function saveUser() {
    if (isEditing.value) {
        form.put(route('admin.utilisateurs.update', editingUser.value.id), {
            preserveScroll: true,
            onSuccess: () => {
                closeForm();
                showToast('Compte mis à jour.');
            },
        });
        return;
    }

    form.post(route('admin.utilisateurs.store'), {
        preserveScroll: true,
        onSuccess: (page) => {
            closeForm();
            showToast(page.props.flash?.success ?? page.props.flash?.warning ?? 'Invitation enregistrée.');
        },
    });
}

function confirmToggle(user) {
    if (!user.canManage) return;
    if (user.id === page.props.auth?.admin?.id) {
        showToast('Vous ne pouvez pas désactiver votre propre compte.');
        return;
    }
    const activating = user.statut === 'Désactivé';
    askConfirm({
        title: activating ? 'Activer ce compte ?' : 'Désactiver ce compte ?',
        message: `Confirmer pour ${user.displayName}.`,
        confirmLabel: activating ? 'Activer' : 'Désactiver',
        variant: 'danger',
        onConfirm: () => executeToggle(user),
    });
}

function executeToggle(user) {
    const wasActive = user.actif;
    router.post(route('admin.utilisateurs.toggle', user.id), {}, {
        preserveScroll: true,
        onSuccess: () => {
            showToast(wasActive ? 'Compte désactivé.' : 'Compte activé.');
        },
    });
}

function confirmResend(user) {
    if (!user.canResendInvitation || resendingId.value) return;
    askConfirm({
        title: 'Renvoyer l\'invitation ?',
        message: `Un nouvel e-mail d'activation sera envoyé à ${user.email}.`,
        confirmLabel: 'Envoyer l\'e-mail',
        variant: 'info',
        onConfirm: () => executeResend(user),
    });
}

function executeResend(user) {
    if (resendingId.value) return;
    resendingId.value = user.id;
    router.post(route('admin.utilisateurs.resend-invitation', user.id), {}, {
        preserveScroll: true,
        onSuccess: (page) => {
            showToast(page.props.flash?.success ?? page.props.flash?.warning ?? 'Invitation renvoyée.');
        },
        onError: (errors) => {
            showToast(Object.values(errors)[0] ?? 'Impossible de renvoyer l\'invitation.');
        },
        onFinish: () => {
            resendingId.value = null;
        },
    });
}

function confirmDelete(user) {
    if (!user.canDelete) return;
    askConfirm({
        title: 'Supprimer ce compte ?',
        message: `Cette action est définitive pour ${user.displayName} (${user.email}).`,
        confirmLabel: 'Supprimer',
        variant: 'danger',
        onConfirm: () => executeDelete(user),
    });
}

function executeDelete(user) {
    router.delete(route('admin.utilisateurs.destroy', user.id), {
        preserveScroll: true,
        onSuccess: () => {
            showToast(`Compte ${user.email} supprimé.`);
        },
        onError: (errors) => {
            showToast(Object.values(errors)[0] ?? 'Suppression impossible.');
        },
    });
}
</script>

<template>
    <Head title="Comptes internes" />

    <AdminLayout active-nav="utilisateurs" title="Comptes internes" subtitle="Gestionnaires, superviseurs, administrateurs">
        <FlashMessage />

        <div class="flex flex-wrap justify-between items-center gap-3 mb-5">
            <div class="relative w-full sm:w-72">
                <span class="material-symbols-outlined absolute left-2.5 top-1/2 -translate-y-1/2 text-on-surface-variant text-[18px]">search</span>
                <input v-model="search" class="w-full pl-8 pr-3 py-2 text-xs border border-outline-variant rounded-lg bg-white" placeholder="Rechercher (nom, e-mail)…" type="search" />
            </div>
            <button class="bg-primary text-on-primary px-4 py-2.5 rounded-lg text-xs font-bold flex items-center gap-1.5 hover:opacity-90" type="button" @click="openCreate">
                <span class="material-symbols-outlined text-[16px]">person_add</span> Nouveau compte
            </button>
        </div>

        <div ref="listTop" class="assure-card overflow-hidden scroll-mt-24">
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="bg-surface-container-low text-on-surface-variant text-[11px] uppercase tracking-wide">
                            <th class="px-5 py-3 font-medium">Nom</th>
                            <th class="px-5 py-3 font-medium hidden md:table-cell">Email</th>
                            <th class="px-5 py-3 font-medium">Rôle</th>
                            <th class="px-5 py-3 font-medium hidden lg:table-cell">Statut</th>
                            <th class="px-5 py-3 font-medium text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant">
                        <tr v-for="user in users" :key="user.id" class="hover:bg-surface-container-low transition-colors">
                            <td class="px-5 py-3 text-xs">
                                <div class="flex items-center gap-2">
                                    <div class="w-7 h-7 rounded-full bg-surface-container flex items-center justify-center font-bold text-[10px] text-primary shrink-0">{{ user.initiales }}</div>
                                    <div>
                                        <p class="font-semibold">{{ user.displayName }}</p>
                                        <p class="text-[10px] text-on-surface-variant">{{ user.permissionsCount }} permission(s)</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3 text-xs text-on-surface-variant hidden md:table-cell">{{ user.email }}</td>
                            <td class="px-5 py-3">
                                <span class="px-2.5 py-1 rounded-full text-[11px] font-bold" :class="ROLE_BADGE[user.roleLabel] ?? ''">{{ user.roleLabel }}</span>
                            </td>
                            <td class="px-5 py-3 hidden lg:table-cell">
                                <span class="px-2.5 py-1 rounded-full text-[11px] font-bold" :class="STATUT_BADGE[user.statut] ?? ''">{{ user.statut }}</span>
                            </td>
                            <td class="px-5 py-3 text-right whitespace-nowrap">
                                <button
                                    class="text-on-surface-variant hover:text-primary mr-2 disabled:opacity-30"
                                    type="button"
                                    :disabled="!user.canManage"
                                    title="Modifier"
                                    @click="openEdit(user)"
                                >
                                    <span class="material-symbols-outlined text-[18px]">edit</span>
                                </button>
                                <button
                                    v-if="user.canResendInvitation"
                                    class="text-on-surface-variant hover:text-primary mr-2 disabled:opacity-30"
                                    type="button"
                                    :disabled="resendingId === user.id"
                                    title="Renvoyer l'invitation"
                                    @click.stop="confirmResend(user)"
                                >
                                    <span class="material-symbols-outlined text-[18px]">mail</span>
                                </button>
                                <button
                                    class="text-on-surface-variant hover:text-error mr-2 disabled:opacity-30"
                                    type="button"
                                    :disabled="!user.canManage || user.id === page.props.auth?.admin?.id"
                                    :title="user.statut === 'Désactivé' ? 'Activer' : 'Désactiver'"
                                    @click="confirmToggle(user)"
                                >
                                    <span class="material-symbols-outlined text-[18px]">{{ user.statut === 'Désactivé' ? 'check_circle' : 'block' }}</span>
                                </button>
                                <button
                                    class="text-on-surface-variant hover:text-error disabled:opacity-30"
                                    type="button"
                                    :disabled="!user.canDelete"
                                    title="Supprimer"
                                    @click="confirmDelete(user)"
                                >
                                    <span class="material-symbols-outlined text-[18px]">delete</span>
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!users.length">
                            <td class="px-5 py-10 text-center text-on-surface-variant text-sm" colspan="5">
                                {{ props.search ? `Aucun compte ne correspond à « ${props.search} ».` : 'Aucun compte interne.' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="px-4 border-t border-outline-variant">
                <CamaPagination v-bind="pagination" label="compte(s)" @change="goToPage" @per-page="setPerPage" />
            </div>
        </div>

        <!-- Modale formulaire -->
        <div v-if="formOpen" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="closeForm">
            <div class="bg-white rounded-xl w-full max-w-2xl max-h-[92vh] overflow-y-auto p-6">
                <h3 class="font-title-lg text-title-lg mb-1">{{ isEditing ? 'Modifier le compte interne' : 'Nouveau compte interne' }}</h3>
                <p class="text-xs text-on-surface-variant mb-4">
                    {{ isEditing ? 'Mettez à jour le profil, le rôle et les permissions.' : "Renseignez l'e-mail et les droits : une invitation sera envoyée pour finaliser le profil et définir le mot de passe." }}
                </p>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="text-[11px] font-bold uppercase text-on-surface-variant block mb-1">Grade</label>
                        <select v-model="form.grade" class="w-full px-3 py-2 text-xs border border-outline-variant rounded-lg bg-white">
                            <option value="">— Grade —</option>
                            <option v-for="grade in grades" :key="grade" :value="grade">{{ grade }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-[11px] font-bold uppercase text-on-surface-variant block mb-1">Rôle</label>
                        <select v-model="form.role" class="w-full px-3 py-2 text-xs border border-outline-variant rounded-lg bg-white">
                            <option v-for="role in assignableRoles" :key="role.value" :value="role.value">{{ role.label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-[11px] font-bold uppercase text-on-surface-variant block mb-1">Prénom <span class="text-error">*</span></label>
                        <input v-model="form.prenom" class="w-full px-3 py-2 text-xs border border-outline-variant rounded-lg" type="text" />
                        <p v-if="form.errors.prenom" class="text-[10px] text-error mt-1">{{ form.errors.prenom }}</p>
                    </div>
                    <div>
                        <label class="text-[11px] font-bold uppercase text-on-surface-variant block mb-1">Nom <span class="text-error">*</span></label>
                        <input v-model="form.nom" class="w-full px-3 py-2 text-xs border border-outline-variant rounded-lg uppercase" type="text" />
                        <p v-if="form.errors.nom" class="text-[10px] text-error mt-1">{{ form.errors.nom }}</p>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="text-[11px] font-bold uppercase text-on-surface-variant block mb-1">Email <span class="text-error">*</span></label>
                        <input v-model="form.email" class="w-full px-3 py-2 text-xs border border-outline-variant rounded-lg" type="email" placeholder="prenom.nom@cama.bf" />
                        <p v-if="form.errors.email" class="text-[10px] text-error mt-1">{{ form.errors.email }}</p>
                    </div>
                </div>

                <div class="mt-5">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-[11px] font-bold uppercase text-on-surface-variant">Permissions sur la plateforme</p>
                        <button class="text-[11px] font-bold text-primary hover:underline flex items-center gap-1" type="button" @click="applyRolePreset">
                            <span class="material-symbols-outlined text-[14px]">restart_alt</span> Appliquer le rôle
                        </button>
                    </div>
                    <p v-if="isSelf && !actorIsAdmin" class="mb-2 text-[11px] text-tertiary flex items-center gap-1">
                        <span class="material-symbols-outlined text-[14px]">lock</span>
                        Vos propres droits ne peuvent être modifiés que par un administrateur.
                    </p>
                    <p v-else-if="!actorIsAdmin" class="mb-2 text-[11px] text-on-surface-variant">
                        Les cases grisées correspondent à des permissions que vous ne possédez pas vous-même : vous ne pouvez pas les attribuer.
                    </p>
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
                                        :checked="form.permissions.includes(item.key) || impliedByCmsManage(item.key)"
                                        :disabled="permissionState(item.key).disabled"
                                        @change="togglePermission(item.key)"
                                    />
                                    <span>
                                        {{ item.label }}
                                        <span v-if="item.key === 'cms.manage'" class="block text-[10px] text-on-surface-variant">Inclut tout le studio, sauf le HTML libre.</span>
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>
                    <p v-if="form.errors.permissions" class="text-[11px] text-error mt-2">{{ form.errors.permissions }}</p>
                </div>

                <div class="flex justify-end gap-2 mt-6">
                    <button class="px-4 py-2 text-xs rounded-lg border border-outline" type="button" @click="closeForm">Annuler</button>
                    <button class="px-4 py-2 text-xs rounded-lg bg-primary text-on-primary font-bold disabled:opacity-60" type="button" :disabled="form.processing" @click="saveUser">
                        {{ isEditing ? 'Enregistrer' : 'Créer et inviter' }}
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

        <div v-if="toast" class="fixed bottom-6 right-6 z-[70]">
            <div class="bg-on-background text-white px-5 py-3 rounded-lg shadow-xl flex items-center gap-2 text-sm font-semibold">{{ toast }}</div>
        </div>
    </AdminLayout>
</template>
