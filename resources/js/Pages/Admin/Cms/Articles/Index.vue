<script setup>
import CmsLayout from '@/Layouts/CmsLayout.vue';
import CamaConfirmModal from '@/Components/CamaConfirmModal.vue';
import CamaFilePicker from '@/Components/CamaFilePicker.vue';
import CamaLoadingButton from '@/Components/CamaLoadingButton.vue';
import { useCamaConfirm } from '@/Composables/useCamaConfirm';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    items: Array,
    statusFilters: Array,
    activeFilter: { type: String, default: 'Tous' },
    categoryList: Array,
    categoryOptions: Array,
    activeCategoryId: { type: Number, default: null },
    statusOptions: Object,
});

const editorOpen = ref(false);
const viewerOpen = ref(false);
const editingId = ref(null);
const viewedItem = ref(null);
const imagePreviewError = ref(false);
const editorImageError = ref(false);
const localPreviewUrl = ref(null);
const filePickerRef = ref(null);
const selectedFileName = ref('');
const newCategoryName = ref('');
const editingCategory = ref(null);

const categoryForm = useForm({
    name: '',
    sort_order: 1,
});

const form = useForm({
    title: '',
    category_id: null,
    status: 'draft',
    image_url: '',
    image_file: null,
    excerpt: '',
    body_html: '',
    published_at: '',
});

const categoryFilterPills = computed(() => [
    { id: null, name: 'Toutes' },
    ...props.categoryList,
]);

const { confirmState, askConfirm, confirm, cancel } = useCamaConfirm();

const statusStyles = {
    Brouillon: 'bg-surface-container-high text-on-surface-variant',
    Programmé: 'bg-tertiary/10 text-tertiary',
    Publié: 'bg-secondary text-on-secondary',
    Archivé: 'bg-on-surface-variant/10 text-on-surface-variant',
};

const editorImageSrc = computed(() => {
    if (localPreviewUrl.value) return localPreviewUrl.value;
    return resolveImageSrc(form.image_url);
});

const isScheduled = computed(() => form.status === 'scheduled');

function resolveImageSrc(path) {
    if (!path) return null;
    if (path.startsWith('http://') || path.startsWith('https://')) return path;
    return `/${path.replace(/^\/+/, '')}`;
}

function clearLocalPreview() {
    if (localPreviewUrl.value) {
        URL.revokeObjectURL(localPreviewUrl.value);
        localPreviewUrl.value = null;
    }
    form.image_file = null;
    selectedFileName.value = '';
    filePickerRef.value?.clear();
}

function onImageSelected(event) {
    const file = event.target.files?.[0];
    if (!file) return;

    if (file.size > 5 * 1024 * 1024) {
        askConfirm({
            title: 'Image trop volumineuse',
            message: 'La taille maximale autorisée est de 5 Mo. Choisissez une image plus légère.',
            variant: 'warning',
            alertOnly: true,
        });
        event.target.value = '';
        return;
    }

    if (localPreviewUrl.value) {
        URL.revokeObjectURL(localPreviewUrl.value);
    }

    form.image_file = file;
    selectedFileName.value = file.name;
    localPreviewUrl.value = URL.createObjectURL(file);
    editorImageError.value = false;
}

function removeImage() {
    clearLocalPreview();
    form.image_url = '';
    editorImageError.value = false;
}

function setFilter(status) {
    router.get(route('admin.cms.actualites'), {
        status,
        ...(props.activeCategoryId ? { category: props.activeCategoryId } : {}),
    }, { preserveState: true, preserveScroll: true });
}

function setCategoryFilter(categoryId) {
    const params = { status: props.activeFilter };
    if (categoryId) params.category = categoryId;
    router.get(route('admin.cms.actualites'), params, { preserveState: true, preserveScroll: true });
}

function addCategory() {
    const name = newCategoryName.value.trim();
    if (!name) return;

    categoryForm.name = name;
    categoryForm.sort_order = (props.categoryList?.length ?? 0) + 1;
    categoryForm.post(route('admin.cms.actualites.categories.store'), {
        preserveScroll: true,
        onSuccess: () => {
            newCategoryName.value = '';
            categoryForm.reset();
        },
    });
}

function startEditCategory(cat) {
    editingCategory.value = cat.id;
    categoryForm.name = cat.name;
    categoryForm.sort_order = cat.sort_order;
}

function cancelEditCategory() {
    editingCategory.value = null;
    categoryForm.reset();
}

function saveCategory(catId) {
    categoryForm.put(route('admin.cms.actualites.categories.update', catId), {
        preserveScroll: true,
        onSuccess: () => cancelEditCategory(),
    });
}

function removeCategory(cat) {
    if (cat.article_count > 0) {
        askConfirm({
            title: 'Suppression impossible',
            message: `${cat.article_count} article(s) utilisent « ${cat.name} ». Réaffectez ou supprimez ces articles avant de retirer la catégorie.`,
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
        onConfirm: () => router.delete(route('admin.cms.actualites.categories.destroy', cat.id), { preserveScroll: true }),
    });
}

function stripHtml(html) {
    if (!html) return '';
    return html.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
}

function openViewer(item) {
    imagePreviewError.value = false;
    viewedItem.value = item;
    viewerOpen.value = true;
}

function closeViewer() {
    viewerOpen.value = false;
    viewedItem.value = null;
}

function openEditor(item = null) {
    closeViewer();
    clearLocalPreview();
    editingId.value = item?.id ?? null;
    editorImageError.value = false;
    form.reset();
    form.clearErrors();

    if (item) {
        form.title = item.title;
        form.category_id = item.category_id;
        form.status = item.status;
        form.image_url = item.image_url ?? '';
        form.excerpt = item.excerpt ?? '';
        form.body_html = stripHtml(item.body_html);
        form.published_at = item.published_at_iso ?? '';
    } else {
        form.category_id = props.categoryOptions[0]?.id ?? null;
        form.status = 'draft';
    }

    editorOpen.value = true;
}

function closeEditor() {
    clearLocalPreview();
    editorOpen.value = false;
    editingId.value = null;
}

function saveArticle() {
    const options = {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => closeEditor(),
    };
    if (editingId.value) {
        form.transform((data) => ({ ...data, _method: 'put' }))
            .post(route('admin.cms.actualites.update', editingId.value), options);
    } else {
        form.post(route('admin.cms.actualites.store'), options);
    }
}

function removeArticle(id) {
    askConfirm({
        title: 'Supprimer cet article ?',
        message: 'Cette action est irréversible. L\'article sera retiré du site public.',
        confirmLabel: 'Supprimer',
        variant: 'danger',
        onConfirm: () => router.delete(route('admin.cms.actualites.destroy', id), { preserveScroll: true }),
    });
}
</script>

<template>
    <Head title="Actualités" />

    <CmsLayout active-nav="actualites" title="Actualités" subtitle="Articles publiés sur le site public">
        <div class="assure-card p-5 space-y-3">
            <h2 class="font-title-lg text-sm font-bold">Catégories</h2>
            <div class="flex flex-wrap gap-2">
                <template v-for="cat in categoryList" :key="cat.id">
                    <div v-if="editingCategory === cat.id" class="inline-flex items-center gap-2 px-2 py-1 rounded-lg border border-outline-variant bg-white">
                        <input v-model="categoryForm.name" class="px-2 py-1 text-xs border border-outline-variant rounded w-32" />
                        <input v-model.number="categoryForm.sort_order" class="px-2 py-1 text-xs border border-outline-variant rounded w-14" min="1" type="number" />
                        <button class="text-primary" type="button" @click="saveCategory(cat.id)">
                            <span class="material-symbols-outlined text-[16px]">check</span>
                        </button>
                        <button type="button" @click="cancelEditCategory">
                            <span class="material-symbols-outlined text-[16px]">close</span>
                        </button>
                    </div>
                    <span
                        v-else
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[11px] font-semibold bg-surface-container-high text-on-surface"
                    >
                        {{ cat.name }}
                        <span class="text-on-surface-variant">({{ cat.article_count }})</span>
                        <button type="button" title="Modifier" @click="startEditCategory(cat)">
                            <span class="material-symbols-outlined text-[14px]">edit</span>
                        </button>
                        <button type="button" title="Supprimer" @click="removeCategory(cat)">
                            <span class="material-symbols-outlined text-[14px] text-error">close</span>
                        </button>
                    </span>
                </template>
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
            <p v-if="categoryForm.errors.name" class="text-error text-[10px]">{{ categoryForm.errors.name }}</p>
        </div>

        <div class="flex flex-wrap gap-2">
            <button
                v-for="pill in categoryFilterPills"
                :key="pill.id ?? 'all-cat'"
                type="button"
                class="px-3 py-1.5 rounded-full text-[11px] font-semibold border"
                :class="(activeCategoryId === pill.id || (!activeCategoryId && pill.id === null)) ? 'bg-secondary text-on-secondary border-secondary' : 'bg-white text-on-surface-variant border-outline-variant'"
                @click="setCategoryFilter(pill.id)"
            >
                {{ pill.name }}
            </button>
        </div>

        <div class="flex flex-wrap gap-2 items-center justify-between">
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="status in statusFilters"
                    :key="status"
                    type="button"
                    class="px-3 py-1.5 rounded-full text-[11px] font-semibold border"
                    :class="activeFilter === status ? 'bg-primary text-on-primary border-primary' : 'bg-white text-on-surface-variant border-outline-variant'"
                    @click="setFilter(status)"
                >
                    {{ status }}
                </button>
            </div>
                <button
                    type="button"
                    class="bg-primary text-on-primary px-4 py-2 rounded-lg text-xs font-bold flex items-center gap-1.5"
                    :disabled="!categoryOptions.length"
                    @click="openEditor()"
                >
                <span class="material-symbols-outlined text-[16px]">add</span>
                Nouvel article
            </button>
        </div>

        <div class="assure-card overflow-x-auto">
            <table class="w-full text-xs">
                <thead>
                    <tr class="border-b border-outline-variant text-left text-[10px] uppercase text-on-surface-variant">
                        <th class="p-3 w-14" />
                        <th class="p-3">Titre</th>
                        <th class="p-3">Catégorie</th>
                        <th class="p-3">Statut</th>
                        <th class="p-3">Auteur</th>
                        <th class="p-3">Date</th>
                        <th class="p-3" />
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="item in items" :key="item.id" class="border-b border-outline-variant last:border-0">
                        <td class="p-3">
                            <div class="w-10 h-10 rounded-lg overflow-hidden bg-surface-container-high shrink-0">
                                <img
                                    v-if="item.image_src"
                                    :alt="item.title"
                                    class="w-full h-full object-cover"
                                    :src="item.image_src"
                                    @error="($event.target.style.display = 'none')"
                                />
                                <div v-else class="w-full h-full flex items-center justify-center text-on-surface-variant">
                                    <span class="material-symbols-outlined text-[18px]">image</span>
                                </div>
                            </div>
                        </td>
                        <td class="p-3 font-semibold text-on-surface max-w-[220px]">
                            <button class="text-left truncate block max-w-[220px] hover:text-primary" type="button" @click="openViewer(item)">
                                {{ item.title }}
                            </button>
                        </td>
                        <td class="p-3 text-on-surface-variant">{{ item.category }}</td>
                        <td class="p-3">
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold" :class="statusStyles[item.status_label]">
                                {{ item.status_label }}
                            </span>
                        </td>
                        <td class="p-3 text-on-surface-variant">{{ item.author_name }}</td>
                        <td class="p-3 text-on-surface-variant">{{ item.published_at }}</td>
                        <td class="p-3 text-right whitespace-nowrap">
                            <button type="button" title="Voir" class="text-on-surface-variant hover:text-primary" @click="openViewer(item)">
                                <span class="material-symbols-outlined text-[18px]">visibility</span>
                            </button>
                            <button type="button" title="Modifier" class="text-on-surface-variant hover:text-primary ml-2" @click="openEditor(item)">
                                <span class="material-symbols-outlined text-[18px]">edit</span>
                            </button>
                            <button type="button" title="Supprimer" class="text-on-surface-variant hover:text-error ml-2" @click="removeArticle(item.id)">
                                <span class="material-symbols-outlined text-[18px]">delete</span>
                            </button>
                        </td>
                    </tr>
                    <tr v-if="!items.length">
                        <td class="p-6 text-center text-on-surface-variant" colspan="7">Aucun article dans ce statut.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </CmsLayout>

    <!-- Modal détail -->
    <div
        v-if="viewerOpen && viewedItem"
        class="fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4"
        @click.self="closeViewer"
    >
        <div class="bg-white rounded-2xl w-full max-w-2xl max-h-[92vh] overflow-hidden shadow-2xl flex flex-col">
            <div class="relative h-44 md:h-52 bg-surface-container-high shrink-0">
                <img
                    v-if="viewedItem.image_src && !imagePreviewError"
                    :alt="viewedItem.title"
                    class="w-full h-full object-cover"
                    :src="viewedItem.image_src"
                    @error="imagePreviewError = true"
                />
                <div v-else class="w-full h-full flex flex-col items-center justify-center text-on-surface-variant gap-2">
                    <span class="material-symbols-outlined text-[40px] opacity-40">image</span>
                    <p class="text-[10px]">Image non disponible</p>
                </div>
                <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent" />
                <button
                    type="button"
                    class="absolute top-3 right-3 w-8 h-8 rounded-full bg-black/40 text-white flex items-center justify-center hover:bg-black/60"
                    @click="closeViewer"
                >
                    <span class="material-symbols-outlined text-[18px]">close</span>
                </button>
                <div class="absolute bottom-0 left-0 right-0 p-5 text-white">
                    <div class="flex flex-wrap gap-2 mb-2">
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-white/20 backdrop-blur-sm">{{ viewedItem.category }}</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold" :class="statusStyles[viewedItem.status_label]">
                            {{ viewedItem.status_label }}
                        </span>
                    </div>
                    <h3 class="text-lg font-bold leading-snug">{{ viewedItem.title }}</h3>
                </div>
            </div>

            <div class="overflow-y-auto flex-1 p-5 space-y-4">
                <div class="flex flex-wrap gap-4 text-xs text-on-surface-variant border-b border-outline-variant pb-4">
                    <span class="inline-flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[16px]">person</span>
                        {{ viewedItem.author_name }}
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[16px]">calendar_today</span>
                        {{ viewedItem.published_at }}
                    </span>
                    <span v-if="viewedItem.status === 'scheduled'" class="inline-flex items-center gap-1.5 text-tertiary font-semibold">
                        <span class="material-symbols-outlined text-[16px]">schedule</span>
                        Publication automatique à cette date
                    </span>
                </div>

                <div v-if="viewedItem.excerpt" class="bg-surface-container-low rounded-xl p-4 border-l-4 border-primary">
                    <p class="text-[10px] font-bold uppercase text-on-surface-variant mb-1">Chapeau</p>
                    <p class="text-sm text-on-surface leading-relaxed italic">{{ viewedItem.excerpt }}</p>
                </div>

                <div v-if="viewedItem.body_html">
                    <p class="text-[10px] font-bold uppercase text-on-surface-variant mb-2">Contenu</p>
                    <div class="text-sm text-on-surface leading-relaxed space-y-3 article-body" v-html="viewedItem.body_html" />
                </div>
            </div>

            <div class="shrink-0 flex justify-end gap-2 p-4 border-t border-outline-variant bg-surface-container-low/50">
                <button class="px-4 py-2 text-xs font-bold border border-outline-variant rounded-lg bg-white" type="button" @click="closeViewer">
                    Fermer
                </button>
                <button class="px-4 py-2 text-xs font-bold bg-primary text-on-primary rounded-lg" type="button" @click="openEditor(viewedItem)">
                    Modifier
                </button>
            </div>
        </div>
    </div>

    <!-- Modal édition -->
    <div
        v-if="editorOpen"
        class="fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4"
        @click.self="closeEditor"
    >
        <div class="bg-white rounded-2xl w-full max-w-2xl max-h-[92vh] overflow-y-auto shadow-2xl">
            <div class="flex items-center justify-between p-5 border-b border-outline-variant sticky top-0 bg-white z-10">
                <h3 class="font-semibold text-sm">{{ editingId ? "Modifier l'article" : 'Nouvel article' }}</h3>
                <button type="button" @click="closeEditor">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>

            <form class="p-5 space-y-4" @submit.prevent="saveArticle">
                <div class="rounded-xl overflow-hidden border border-outline-variant bg-surface-container-low">
                    <div class="relative h-36 bg-surface-container-high">
                        <img
                            v-if="editorImageSrc && !editorImageError"
                            :alt="form.title || 'Aperçu'"
                            class="w-full h-full object-cover"
                            :src="editorImageSrc"
                            @error="editorImageError = true"
                        />
                        <div v-else class="w-full h-full flex flex-col items-center justify-center text-on-surface-variant gap-1">
                            <span class="material-symbols-outlined text-[32px] opacity-40">image</span>
                            <p class="text-[10px]">Aperçu de l'image</p>
                        </div>
                    </div>
                    <div class="p-3 space-y-3">
                        <label class="text-[11px] font-bold uppercase text-on-surface-variant block">Image de l'article</label>
                        <CamaFilePicker
                            ref="filePickerRef"
                            accept="image/jpeg,image/jpg,image/png,image/webp,image/gif,.jfif"
                            label="Choisir une image"
                            hint="JPEG, PNG, WebP, GIF — 5 Mo max. Aperçu immédiat après sélection."
                            :file-name="selectedFileName"
                            @change="onImageSelected"
                        >
                            <template #actions>
                                <button
                                    v-if="editorImageSrc"
                                    class="inline-flex items-center gap-1 px-3 py-2 text-xs font-bold border border-outline-variant rounded-lg text-error"
                                    type="button"
                                    @click="removeImage"
                                >
                                    <span class="material-symbols-outlined text-[16px]">delete</span>
                                    Retirer
                                </button>
                            </template>
                        </CamaFilePicker>
                        <p v-if="form.errors.image_file" class="text-error text-[10px]">{{ form.errors.image_file }}</p>
                        <details class="text-xs">
                            <summary class="cursor-pointer text-on-surface-variant hover:text-primary">Ou utiliser un chemin existant</summary>
                            <input
                                v-model="form.image_url"
                                class="w-full mt-2 px-3 py-2 text-xs border border-outline-variant rounded-lg bg-white"
                                placeholder="images/CAMA_8.jfif"
                                @input="editorImageError = false"
                            />
                        </details>
                    </div>
                </div>

                <div>
                    <label class="text-[11px] font-bold uppercase text-on-surface-variant block mb-1">Titre</label>
                    <input v-model="form.title" class="w-full px-3 py-2 text-xs border border-outline-variant rounded-lg" required />
                </div>

                <div>
                    <label class="text-[11px] font-bold uppercase text-on-surface-variant block mb-1">Catégorie</label>
                    <select v-model="form.category_id" class="w-full px-3 py-2 text-xs border border-outline-variant rounded-lg" required>
                        <option v-for="cat in categoryOptions" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                    </select>
                    <p v-if="form.errors.category_id" class="text-error text-[10px] mt-1">{{ form.errors.category_id }}</p>
                </div>

                <div>
                    <label class="text-[11px] font-bold uppercase text-on-surface-variant block mb-1">Chapeau (résumé)</label>
                    <textarea v-model="form.excerpt" class="w-full px-3 py-2 text-xs border border-outline-variant rounded-lg" rows="2" />
                </div>

                <div>
                    <label class="text-[11px] font-bold uppercase text-on-surface-variant block mb-1">Contenu</label>
                    <textarea v-model="form.body_html" class="w-full px-3 py-2 text-xs border border-outline-variant rounded-lg" rows="6" />
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-[11px] font-bold uppercase text-on-surface-variant block mb-1">Statut</label>
                        <select v-model="form.status" class="w-full px-3 py-2 text-xs border border-outline-variant rounded-lg">
                            <option v-for="(label, key) in statusOptions" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-[11px] font-bold uppercase text-on-surface-variant block mb-1">Date de publication</label>
                        <input v-model="form.published_at" class="w-full px-3 py-2 text-xs border border-outline-variant rounded-lg" type="date" />
                    </div>
                </div>

                <div v-if="isScheduled" class="flex items-start gap-2 p-3 rounded-lg bg-tertiary/10 border border-tertiary/20 text-xs text-tertiary">
                    <span class="material-symbols-outlined text-[18px] shrink-0 mt-0.5">schedule</span>
                    <p>
                        L'article sera publié automatiquement le jour choisi (à minuit, heure du serveur).
                        Aucune action manuelle ne sera nécessaire si le planificateur Laravel est actif.
                    </p>
                </div>

                <p v-if="form.errors.published_at" class="text-error text-[10px]">{{ form.errors.published_at }}</p>

                <div class="flex justify-end gap-2 pt-2">
                    <button class="px-4 py-2 text-xs font-bold border border-outline-variant rounded-lg" type="button" :disabled="form.processing" @click="closeEditor">Annuler</button>
                    <CamaLoadingButton type="submit" :loading="form.processing">
                        Enregistrer
                    </CamaLoadingButton>
                </div>
            </form>
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

<style scoped>
.article-body :deep(p) {
    margin-bottom: 0.75rem;
}
.article-body :deep(p:last-child) {
    margin-bottom: 0;
}
</style>
