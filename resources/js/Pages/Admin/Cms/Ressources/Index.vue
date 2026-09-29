<script setup>
import CmsLayout from '@/Layouts/CmsLayout.vue';
import CamaConfirmModal from '@/Components/CamaConfirmModal.vue';
import CamaFilePicker from '@/Components/CamaFilePicker.vue';
import CamaLoadingButton from '@/Components/CamaLoadingButton.vue';
import { useCamaConfirm } from '@/Composables/useCamaConfirm';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    items: Array,
    categoryList: Array,
    categoryOptions: Array,
    activeCategoryId: { type: Number, default: null },
});

const page = usePage();
const editorOpen = ref(false);
const editingId = ref(null);
const newCategoryName = ref('');
const filePickerRef = ref(null);
const selectedFileName = ref('');
const fileInfo = ref('');

const categoryForm = useForm({
    name: '',
});

const form = useForm({
    titre: '',
    description: '',
    category_id: null,
    ordre: 1,
    url: '',
    document_file: null,
    publie: true,
});

const { confirmState, askConfirm, confirm, cancel } = useCamaConfirm();

const toast = computed(() => page.props.flash?.success ?? null);

const filterPills = computed(() => [
    { id: null, name: 'Toutes' },
    ...props.categoryList.map((cat) => ({ id: cat.id, name: cat.name })),
]);

function setCategoryFilter(categoryId) {
    router.get(
        route('admin.cms.ressources'),
        categoryId ? { category: categoryId } : {},
        { preserveState: true, preserveScroll: true },
    );
}

function addCategory() {
    const name = newCategoryName.value.trim();
    if (!name) return;

    categoryForm.name = name;
    categoryForm.post(route('admin.cms.ressources.categories.store'), {
        preserveScroll: true,
        onSuccess: () => {
            newCategoryName.value = '';
            categoryForm.reset();
        },
    });
}

function removeCategory(cat) {
    if (cat.resource_count > 0) {
        askConfirm({
            title: 'Suppression impossible',
            message: `${cat.resource_count} document(s) utilisent « ${cat.name} ». Réaffectez ou supprimez ces documents avant de retirer la catégorie.`,
            variant: 'warning',
            alertOnly: true,
        });
        return;
    }

    askConfirm({
        title: 'Supprimer cette catégorie ?',
        message: `La catégorie « ${cat.name} » sera définitivement supprimée.`,
        confirmLabel: 'Supprimer',
        variant: 'danger',
        onConfirm: () => router.delete(route('admin.cms.ressources.categories.destroy', cat.id), { preserveScroll: true }),
    });
}

function clearDocumentFile() {
    form.document_file = null;
    selectedFileName.value = '';
    fileInfo.value = '';
    filePickerRef.value?.clear();
}

function onDocumentSelected(event) {
    const file = event.target.files?.[0];
    if (!file) return;

    if (file.size > 10 * 1024 * 1024) {
        askConfirm({
            title: 'Fichier trop volumineux',
            message: 'La taille maximale autorisée est de 10 Mo. Utilisez une URL externe pour les fichiers plus lourds.',
            variant: 'warning',
            alertOnly: true,
        });
        event.target.value = '';
        return;
    }

    form.document_file = file;
    selectedFileName.value = file.name;
    const sizeLabel = file.size < 1024 * 1024
        ? `${Math.round(file.size / 1024)} Ko`
        : `${(file.size / 1024 / 1024).toFixed(1).replace('.', ',')} Mo`;
    fileInfo.value = `Joint : ${file.name} (${sizeLabel})`;
}

function openEditor(item = null) {
    editingId.value = item?.id ?? null;
    form.reset();
    form.clearErrors();
    clearDocumentFile();

    if (item) {
        form.titre = item.titre;
        form.description = item.description ?? '';
        form.category_id = item.category_id;
        form.ordre = item.ordre;
        form.url = isExternalUrl(item.url) ? item.url : '';
        form.publie = item.publie;

        if (isLocalFile(item.url)) {
            fileInfo.value = `Fichier actuel : ${item.format}${item.taille ? ` · ${item.taille}` : ''}`;
        }
    } else {
        form.category_id = props.categoryOptions[0]?.id ?? null;
        form.ordre = (props.items?.length ?? 0) + 1;
        form.publie = true;
    }

    editorOpen.value = true;
}

function closeEditor() {
    editorOpen.value = false;
    editingId.value = null;
    clearDocumentFile();
}

function saveResource() {
    const options = {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => closeEditor(),
    };

    if (editingId.value) {
        form.transform((data) => ({ ...data, _method: 'put' }))
            .post(route('admin.cms.ressources.update', editingId.value), options);
    } else {
        form.post(route('admin.cms.ressources.store'), options);
    }
}

function removeResource(id) {
    askConfirm({
        title: 'Supprimer ce document ?',
        message: 'Ce document sera retiré du centre de ressources public.',
        confirmLabel: 'Supprimer',
        variant: 'danger',
        onConfirm: () => router.delete(route('admin.cms.ressources.destroy', id), { preserveScroll: true }),
    });
}

function formatCell(item) {
    const taille = item.taille ? ` · ${item.taille}` : '';
    return `${item.format || 'PDF'}${taille}`;
}

function isExternalUrl(url) {
    return Boolean(url && url !== '#' && (url.startsWith('http://') || url.startsWith('https://')));
}

function isLocalFile(url) {
    return Boolean(url && url !== '#' && !isExternalUrl(url));
}
</script>

<template>
    <Head title="Ressources" />

    <CmsLayout active-nav="ressources" title="Ressources" subtitle="Documents téléchargeables, classés par catégorie">
        <div class="assure-card p-5">
            <div class="flex items-center justify-between mb-3">
                <h2 class="font-title-lg text-sm font-bold">Catégories</h2>
            </div>
            <div class="flex flex-wrap gap-2 mb-3">
                <span
                    v-for="cat in categoryList"
                    :key="cat.id"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[11px] font-semibold bg-surface-container-high text-on-surface"
                >
                    {{ cat.name }}
                    <button type="button" title="Supprimer" @click="removeCategory(cat)">
                        <span class="material-symbols-outlined text-[14px] text-error">close</span>
                    </button>
                </span>
                <span v-if="!categoryList.length" class="text-[11px] text-on-surface-variant italic">Aucune catégorie.</span>
            </div>
            <div class="flex gap-2 max-w-sm">
                <input
                    v-model="newCategoryName"
                    class="flex-1 px-3 py-2 text-xs border border-outline-variant rounded-lg"
                    placeholder="Nouvelle catégorie…"
                    type="text"
                    @keyup.enter="addCategory"
                />
                <button
                    class="px-3 py-2 text-xs font-bold bg-primary text-on-primary rounded-lg flex items-center gap-1"
                    type="button"
                    :disabled="categoryForm.processing"
                    @click="addCategory"
                >
                    <span class="material-symbols-outlined text-[16px]">add</span>
                    Ajouter
                </button>
            </div>
            <p v-if="categoryForm.errors.name" class="text-error text-[10px] mt-2">{{ categoryForm.errors.name }}</p>
        </div>

        <div class="flex flex-wrap gap-2 items-center justify-between mt-5">
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="pill in filterPills"
                    :key="pill.id ?? 'all'"
                    type="button"
                    class="px-3 py-1.5 rounded-full text-[11px] font-semibold border"
                    :class="(activeCategoryId === pill.id || (!activeCategoryId && pill.id === null)) ? 'bg-primary text-on-primary border-primary' : 'bg-white text-on-surface-variant border-outline-variant'"
                    @click="setCategoryFilter(pill.id)"
                >
                    {{ pill.name }}
                </button>
            </div>
            <button
                type="button"
                class="bg-primary text-on-primary px-4 py-2 rounded-lg text-xs font-bold flex items-center gap-1.5"
                :disabled="!categoryOptions.length"
                @click="openEditor()"
            >
                <span class="material-symbols-outlined text-[16px]">add</span>
                Nouveau document
            </button>
        </div>

        <div class="assure-card overflow-x-auto mt-4">
            <table class="w-full text-xs">
                <thead>
                    <tr class="border-b border-outline-variant text-left text-[10px] uppercase text-on-surface-variant">
                        <th class="p-3">Document</th>
                        <th class="p-3">Catégorie</th>
                        <th class="p-3">Format</th>
                        <th class="p-3">Statut</th>
                        <th class="p-3">Ordre</th>
                        <th class="p-3" />
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="item in items" :key="item.id" class="border-b border-outline-variant last:border-0">
                        <td class="p-3 font-semibold max-w-xs truncate">{{ item.titre }}</td>
                        <td class="p-3 text-on-surface-variant">{{ item.categorie }}</td>
                        <td class="p-3 text-on-surface-variant">{{ formatCell(item) }}</td>
                        <td class="p-3">
                            <span
                                class="px-2 py-0.5 rounded-full text-[10px] font-semibold"
                                :class="item.publie ? 'bg-secondary text-on-secondary' : 'bg-surface-container-high text-on-surface-variant'"
                            >
                                {{ item.publie ? 'Publié' : 'Brouillon' }}
                            </span>
                        </td>
                        <td class="p-3 text-on-surface-variant">{{ item.ordre }}</td>
                        <td class="p-3 text-right whitespace-nowrap">
                            <button type="button" title="Modifier" @click="openEditor(item)">
                                <span class="material-symbols-outlined text-[18px]">edit</span>
                            </button>
                            <button type="button" class="ml-2" title="Supprimer" @click="removeResource(item.id)">
                                <span class="material-symbols-outlined text-[18px] text-error">delete</span>
                            </button>
                        </td>
                    </tr>
                    <tr v-if="!items.length">
                        <td class="p-6 text-center text-on-surface-variant" colspan="6">Aucun document dans cette catégorie.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </CmsLayout>

    <div
        v-if="editorOpen"
        class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4"
        @click.self="closeEditor"
    >
        <div class="bg-white rounded-xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between p-5 border-b border-outline-variant">
                <h3 class="font-semibold text-sm">Document</h3>
                <button type="button" :disabled="form.processing" @click="closeEditor">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>

            <form class="p-5 space-y-4" @submit.prevent="saveResource">
                <div>
                    <label class="text-[11px] font-bold uppercase text-on-surface-variant block mb-1">Titre</label>
                    <input v-model="form.titre" class="w-full px-3 py-2 text-xs border border-outline-variant rounded-lg" required />
                    <p v-if="form.errors.titre" class="text-error text-[10px] mt-1">{{ form.errors.titre }}</p>
                </div>

                <div>
                    <label class="text-[11px] font-bold uppercase text-on-surface-variant block mb-1">Description</label>
                    <textarea v-model="form.description" class="w-full px-3 py-2 text-xs border border-outline-variant rounded-lg" rows="3" />
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-[11px] font-bold uppercase text-on-surface-variant block mb-1">Catégorie</label>
                        <select v-model="form.category_id" class="w-full px-3 py-2 text-xs border border-outline-variant rounded-lg" required>
                            <option v-for="cat in categoryOptions" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-[11px] font-bold uppercase text-on-surface-variant block mb-1">Ordre</label>
                        <input v-model.number="form.ordre" class="w-full px-3 py-2 text-xs border border-outline-variant rounded-lg" min="1" type="number" />
                    </div>
                </div>

                <div>
                    <label class="text-[11px] font-bold uppercase text-on-surface-variant block mb-1">Fichier (PDF, DOC, image…)</label>
                    <CamaFilePicker
                        ref="filePickerRef"
                        accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg"
                        label="Choisir un fichier"
                        :file-name="selectedFileName"
                        @change="onDocumentSelected"
                    />
                    <p class="text-[10px] text-on-surface-variant mt-1">
                        {{ fileInfo || 'Optionnel. Pour les fichiers volumineux, renseignez plutôt une URL ci-dessous.' }}
                    </p>
                    <p v-if="form.errors.document_file" class="text-error text-[10px] mt-1">{{ form.errors.document_file }}</p>
                </div>

                <div>
                    <label class="text-[11px] font-bold uppercase text-on-surface-variant block mb-1">… ou URL externe</label>
                    <input v-model="form.url" class="w-full px-3 py-2 text-xs border border-outline-variant rounded-lg" placeholder="https://…" type="text" />
                </div>

                <label class="flex items-center gap-2 text-xs font-semibold">
                    <input v-model="form.publie" class="rounded border-outline-variant text-primary" type="checkbox" />
                    Publié
                </label>

                <div class="flex justify-end gap-2">
                    <button class="px-4 py-2 text-xs font-bold border border-outline-variant rounded-lg" type="button" :disabled="form.processing" @click="closeEditor">
                        Annuler
                    </button>
                    <CamaLoadingButton type="submit" :loading="form.processing">
                        Enregistrer
                    </CamaLoadingButton>
                </div>
            </form>
        </div>
    </div>

    <div v-if="toast" class="fixed bottom-6 right-6 z-[70]">
        <div class="bg-on-background text-white px-5 py-3 rounded-lg shadow-xl flex items-center gap-2 text-xs font-semibold">
            <span class="material-symbols-outlined text-[18px]">check_circle</span>
            {{ toast }}
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
</template>
