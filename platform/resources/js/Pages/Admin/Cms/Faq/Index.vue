<script setup>
import CmsLayout from '@/Layouts/CmsLayout.vue';
import CamaConfirmModal from '@/Components/CamaConfirmModal.vue';
import CamaLoadingButton from '@/Components/CamaLoadingButton.vue';
import { useCamaConfirm } from '@/Composables/useCamaConfirm';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    items: Array,
    categoryList: Array,
    categoryOptions: Array,
    activeCategoryId: { type: Number, default: null },
});

const editorOpen = ref(false);
const viewerOpen = ref(false);
const editingId = ref(null);
const viewedItem = ref(null);

const newCategoryName = ref('');
const editingCategory = ref(null);

const categoryForm = useForm({
    name: '',
    sort_order: 1,
});

const form = useForm({
    question: '',
    answer: '',
    category_id: null,
    sort_order: 1,
    published: true,
});

const filterPills = computed(() => [
    { id: null, name: 'Toutes' },
    ...props.categoryList,
]);

const { confirmState, askConfirm, confirm, cancel } = useCamaConfirm();

function setCategoryFilter(categoryId) {
    router.get(
        route('admin.cms.faq'),
        categoryId ? { category: categoryId } : {},
        { preserveState: true, preserveScroll: true },
    );
}

function openViewer(item) {
    viewedItem.value = item;
    viewerOpen.value = true;
}

function closeViewer() {
    viewerOpen.value = false;
    viewedItem.value = null;
}

function openEditor(item = null) {
    closeViewer();
    editingId.value = item?.id ?? null;
    form.reset();
    form.clearErrors();

    if (item) {
        form.question = item.question;
        form.answer = item.answer;
        form.category_id = item.category_id;
        form.sort_order = item.sort_order;
        form.published = item.published;
    } else {
        form.category_id = props.categoryOptions[0]?.id ?? null;
        form.sort_order = 1;
        form.published = true;
    }

    editorOpen.value = true;
}

function closeEditor() {
    editorOpen.value = false;
    editingId.value = null;
}

function saveFaq() {
    if (editingId.value) {
        form.put(route('admin.cms.faq.update', editingId.value), {
            preserveScroll: true,
            onSuccess: () => closeEditor(),
        });
    } else {
        form.post(route('admin.cms.faq.store'), {
            preserveScroll: true,
            onSuccess: () => closeEditor(),
        });
    }
}

function removeFaq(id) {
    askConfirm({
        title: 'Supprimer cette question ?',
        message: 'Cette question sera définitivement retirée de la FAQ publique.',
        confirmLabel: 'Supprimer',
        variant: 'danger',
        onConfirm: () => router.delete(route('admin.cms.faq.destroy', id), { preserveScroll: true }),
    });
}

function addCategory() {
    const name = newCategoryName.value.trim();
    if (!name) return;

    categoryForm.name = name;
    categoryForm.sort_order = (props.categoryList?.length ?? 0) + 1;
    categoryForm.post(route('admin.cms.faq.categories.store'), {
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
    categoryForm.put(route('admin.cms.faq.categories.update', catId), {
        preserveScroll: true,
        onSuccess: () => cancelEditCategory(),
    });
}

function removeCategory(cat) {
    if (cat.faq_count > 0) {
        askConfirm({
            title: 'Suppression impossible',
            message: `${cat.faq_count} question(s) utilisent « ${cat.name} ». Réaffectez ou supprimez ces questions avant de retirer la catégorie.`,
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
        onConfirm: () => router.delete(route('admin.cms.faq.categories.destroy', cat.id), { preserveScroll: true }),
    });
}
</script>

<template>
    <Head title="FAQ" />

    <CmsLayout active-nav="faq" title="FAQ" subtitle="Questions fréquentes par catégorie">
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
                        <span class="text-on-surface-variant">({{ cat.faq_count }})</span>
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

        <div class="flex flex-wrap gap-2 items-center justify-between">
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
                Nouvelle question
            </button>
        </div>

        <div class="assure-card overflow-x-auto">
            <table class="w-full text-xs">
                <thead>
                    <tr class="border-b border-outline-variant text-left text-[10px] uppercase text-on-surface-variant">
                        <th class="p-3">Question</th>
                        <th class="p-3">Catégorie</th>
                        <th class="p-3">Statut</th>
                        <th class="p-3">Ordre</th>
                        <th class="p-3" />
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="item in items" :key="item.id" class="border-b border-outline-variant last:border-0">
                        <td class="p-3 font-semibold max-w-xs">
                            <button class="text-left truncate block max-w-xs hover:text-primary" type="button" @click="openViewer(item)">
                                {{ item.question }}
                            </button>
                        </td>
                        <td class="p-3 text-on-surface-variant">{{ item.category_name }}</td>
                        <td class="p-3">
                            <span
                                class="px-2 py-0.5 rounded-full text-[10px] font-semibold"
                                :class="item.published ? 'bg-secondary text-on-secondary' : 'bg-surface-container-high text-on-surface-variant'"
                            >
                                {{ item.published ? 'Publié' : 'Brouillon' }}
                            </span>
                        </td>
                        <td class="p-3 text-on-surface-variant">{{ item.sort_order }}</td>
                        <td class="p-3 text-right whitespace-nowrap">
                            <button type="button" title="Voir" @click="openViewer(item)">
                                <span class="material-symbols-outlined text-[18px]">visibility</span>
                            </button>
                            <button type="button" class="ml-2" title="Modifier" @click="openEditor(item)">
                                <span class="material-symbols-outlined text-[18px]">edit</span>
                            </button>
                            <button type="button" class="ml-2" title="Supprimer" @click="removeFaq(item.id)">
                                <span class="material-symbols-outlined text-[18px] text-error">delete</span>
                            </button>
                        </td>
                    </tr>
                    <tr v-if="!items.length">
                        <td class="p-6 text-center text-on-surface-variant" colspan="5">Aucune question dans cette catégorie.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            v-if="viewerOpen && viewedItem"
            class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4"
            @click.self="closeViewer"
        >
            <div class="bg-white rounded-xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between p-5 border-b border-outline-variant">
                    <h3 class="font-semibold text-sm">Détail de la question</h3>
                    <button type="button" @click="closeViewer">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <div class="p-5 space-y-4">
                    <div>
                        <p class="text-[11px] font-bold uppercase text-on-surface-variant mb-1">Question</p>
                        <p class="text-sm text-on-surface">{{ viewedItem.question }}</p>
                    </div>
                    <div>
                        <p class="text-[11px] font-bold uppercase text-on-surface-variant mb-1">Réponse</p>
                        <p class="text-sm text-on-surface whitespace-pre-wrap">{{ viewedItem.answer }}</p>
                    </div>
                    <div class="grid grid-cols-3 gap-3 text-xs">
                        <div>
                            <p class="text-[10px] uppercase text-on-surface-variant mb-1">Catégorie</p>
                            <p class="font-semibold">{{ viewedItem.category_name }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] uppercase text-on-surface-variant mb-1">Ordre</p>
                            <p class="font-semibold">{{ viewedItem.sort_order }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] uppercase text-on-surface-variant mb-1">Statut</p>
                            <span
                                class="px-2 py-0.5 rounded-full text-[10px] font-semibold"
                                :class="viewedItem.published ? 'bg-secondary text-on-secondary' : 'bg-surface-container-high text-on-surface-variant'"
                            >
                                {{ viewedItem.published ? 'Publié' : 'Brouillon' }}
                            </span>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button class="px-4 py-2 text-xs font-bold border border-outline-variant rounded-lg" type="button" @click="closeViewer">Fermer</button>
                        <button class="px-4 py-2 text-xs font-bold bg-primary text-on-primary rounded-lg" type="button" @click="openEditor(viewedItem)">Modifier</button>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-if="editorOpen"
            class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4"
            @click.self="closeEditor"
        >
            <div class="bg-white rounded-xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between p-5 border-b border-outline-variant">
                    <h3 class="font-semibold text-sm">{{ editingId ? 'Modifier la question' : 'Nouvelle question' }}</h3>
                    <button type="button" @click="closeEditor">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <form class="p-5 space-y-4" @submit.prevent="saveFaq">
                    <div>
                        <label class="text-[11px] font-bold uppercase text-on-surface-variant block mb-1">Question</label>
                        <input v-model="form.question" class="w-full px-3 py-2 text-xs border border-outline-variant rounded-lg" required />
                        <p v-if="form.errors.question" class="text-error text-[10px] mt-1">{{ form.errors.question }}</p>
                    </div>
                    <div>
                        <label class="text-[11px] font-bold uppercase text-on-surface-variant block mb-1">Réponse</label>
                        <textarea v-model="form.answer" class="w-full px-3 py-2 text-xs border border-outline-variant rounded-lg" required rows="4" />
                        <p v-if="form.errors.answer" class="text-error text-[10px] mt-1">{{ form.errors.answer }}</p>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-[11px] font-bold uppercase text-on-surface-variant block mb-1">Catégorie</label>
                            <select v-model="form.category_id" class="w-full px-3 py-2 text-xs border border-outline-variant rounded-lg" required>
                                <option v-for="cat in categoryOptions" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                            </select>
                            <p v-if="form.errors.category_id" class="text-error text-[10px] mt-1">{{ form.errors.category_id }}</p>
                        </div>
                        <div>
                            <label class="text-[11px] font-bold uppercase text-on-surface-variant block mb-1">Ordre</label>
                            <input v-model.number="form.sort_order" class="w-full px-3 py-2 text-xs border border-outline-variant rounded-lg" min="1" type="number" />
                        </div>
                    </div>
                    <div>
                        <label class="flex items-center gap-2 text-xs font-semibold">
                            <input v-model="form.published" class="rounded border-outline-variant text-primary" type="checkbox" />
                            Publié
                        </label>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button class="px-4 py-2 text-xs font-bold border border-outline-variant rounded-lg" type="button" :disabled="form.processing" @click="closeEditor">Annuler</button>
                        <CamaLoadingButton type="submit" :loading="form.processing">
                            Enregistrer
                        </CamaLoadingButton>
                    </div>
                </form>
            </div>
        </div>
    </CmsLayout>

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
