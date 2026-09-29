<script setup>
import CmsLayout from '@/Layouts/CmsLayout.vue';
import CamaConfirmModal from '@/Components/CamaConfirmModal.vue';
import CamaLoadingButton from '@/Components/CamaLoadingButton.vue';
import { useCamaConfirm } from '@/Composables/useCamaConfirm';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    items: Array,
    pages: Array,
});

const page = usePage();
const toast = computed(() => page.props.flash?.success ?? null);

const localItems = ref([...(props.items ?? [])]);
const selectedPageIds = ref([]);
const draggedIndex = ref(null);
const editingId = ref(null);
const editingLabel = ref('');
const saveStatus = ref('saved');

const addPagesForm = useForm({
    page_ids: [],
});

const customForm = useForm({
    label: '',
    url: '',
});

const { confirmState, askConfirm, confirm, cancel } = useCamaConfirm();

const menuGroups = computed(() => {
    const groups = [];
    localItems.value.forEach((item) => {
        if (item.depth === 0 || !groups.length) {
            groups.push({ item: { ...item, depth: 0 }, children: [] });
            return;
        }
        groups[groups.length - 1].children.push(item);
    });
    return groups;
});

watch(
    () => props.items,
    (items) => {
        localItems.value = [...(items ?? [])];
    },
);

function statusLabel(status) {
    return status === 'published' ? 'Publié' : 'brouillon';
}

function saveStructure() {
    saveStatus.value = 'saving';

    router.put(
        route('admin.cms.menus.structure'),
        {
            items: localItems.value.map((item, index) => ({
                id: item.id,
                label: item.label,
                depth: index === 0 ? 0 : Number(item.depth || 0),
            })),
        },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => { saveStatus.value = 'saved'; },
            onError: () => { saveStatus.value = 'error'; },
        },
    );
}

function normalize() {
    if (localItems.value.length && localItems.value[0].depth > 0) {
        localItems.value[0].depth = 0;
    }
}

function moveItem(from, to) {
    if (from === to || from < 0 || to < 0 || from >= localItems.value.length || to >= localItems.value.length) {
        return;
    }

    const updated = [...localItems.value];
    const [item] = updated.splice(from, 1);
    updated.splice(to, 0, item);
    localItems.value = updated;
    normalize();
    saveStructure();
}

function indentItem(index) {
    if (index === 0) return;
    localItems.value[index].depth = 1;
    saveStructure();
}

function outdentItem(index) {
    localItems.value[index].depth = 0;
    saveStructure();
}

function startRename(item) {
    editingId.value = item.id;
    editingLabel.value = item.label;
}

function cancelRename() {
    editingId.value = null;
    editingLabel.value = '';
}

function applyRename(index) {
    const label = editingLabel.value.trim();
    if (!label) return;

    localItems.value[index].label = label;
    cancelRename();
    saveStructure();
}

function addSelectedPages() {
    if (!selectedPageIds.value.length) {
        askConfirm({
            title: 'Aucune page sélectionnée',
            message: 'Sélectionnez au moins une page dans la liste avant de l’ajouter au menu.',
            variant: 'warning',
            alertOnly: true,
        });
        return;
    }

    addPagesForm.page_ids = selectedPageIds.value;
    addPagesForm.post(route('admin.cms.menus.pages.store'), {
        preserveScroll: true,
        onSuccess: () => {
            selectedPageIds.value = [];
            addPagesForm.reset();
        },
    });
}

function addCustomLink() {
    customForm.label = customForm.label.trim();
    customForm.url = customForm.url.trim();

    if (!customForm.label) return;

    customForm.post(route('admin.cms.menus.custom.store'), {
        preserveScroll: true,
        onSuccess: () => customForm.reset(),
    });
}

function removeItem(item) {
    askConfirm({
        title: 'Retirer cet élément du menu ?',
        message: `« ${item.label} » sera supprimé de la navigation publique.`,
        confirmLabel: 'Retirer',
        variant: 'danger',
        onConfirm: () => router.delete(route('admin.cms.menus.destroy', item.id), { preserveScroll: true }),
    });
}

function onDragStart(index) {
    draggedIndex.value = index;
}

function onDrop(index) {
    if (draggedIndex.value === null) return;
    moveItem(draggedIndex.value, index);
    draggedIndex.value = null;
}
</script>

<template>
    <Head title="Menus du site" />

    <CmsLayout active-nav="menus" title="Menus du site" subtitle="Organisez les onglets de navigation du header public">
        <div class="assure-card p-4">
            <p class="text-[11px] uppercase font-bold text-on-surface-variant tracking-wider mb-3">Aperçu du header public</p>
            <div class="border border-outline-variant rounded-xl overflow-hidden">
                <div class="flex items-center justify-between px-5 py-3 bg-white border-b border-outline-variant">
                    <div class="flex items-center gap-2">
                        <img src="/images/logo_cama.png" class="h-8 w-8 object-contain" alt="Logo CAMA" />
                        <span class="font-extrabold tracking-tight font-headline-lg">CAMA</span>
                    </div>
                    <nav class="hidden md:flex items-center gap-1">
                        <template v-if="menuGroups.length">
                            <div v-for="group in menuGroups" :key="group.item.id" class="relative group">
                                <a
                                    v-if="!group.children.length"
                                    :href="group.item.href"
                                    class="px-3 py-2 text-sm font-semibold text-on-surface hover:text-primary"
                                >
                                    {{ group.item.label }}
                                </a>
                                <button
                                    v-else
                                    type="button"
                                    class="px-3 py-2 text-sm font-semibold text-on-surface hover:text-primary flex items-center gap-1"
                                >
                                    {{ group.item.label }}
                                    <span class="material-symbols-outlined text-[16px]">expand_more</span>
                                </button>
                                <div
                                    v-if="group.children.length"
                                    class="absolute left-0 top-full hidden group-hover:block bg-white border border-outline-variant rounded-lg shadow-lg py-1 min-w-[180px] z-10"
                                >
                                    <a
                                        v-for="child in group.children"
                                        :key="child.id"
                                        :href="child.href"
                                        class="block px-4 py-2 text-sm text-on-surface hover:bg-surface-container-low hover:text-primary"
                                    >
                                        {{ child.label }}
                                    </a>
                                </div>
                            </div>
                        </template>
                        <span v-else class="text-xs text-on-surface-variant italic">Menu vide</span>
                    </nav>
                    <span class="material-symbols-outlined md:hidden text-on-surface-variant">menu</span>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="space-y-4">
                <div class="assure-card p-4">
                    <p class="text-sm font-bold mb-3 flex items-center gap-2">
                        <span class="material-symbols-outlined text-[18px] text-primary">description</span>
                        Ajouter des pages
                    </p>
                    <div class="space-y-1 max-h-64 overflow-y-auto no-scrollbar border border-outline-variant rounded-lg p-2">
                        <label
                            v-for="cmsPage in pages"
                            :key="cmsPage.id"
                            class="flex items-center gap-2 px-2 py-1.5 rounded hover:bg-surface-container-low cursor-pointer text-sm"
                        >
                            <input
                                v-model="selectedPageIds"
                                :value="cmsPage.id"
                                type="checkbox"
                                class="rounded border-outline-variant text-primary focus:ring-primary"
                            />
                            <span class="flex-1 truncate">{{ cmsPage.title }}</span>
                            <span
                                v-if="cmsPage.status !== 'published'"
                                class="text-[9px] text-tertiary font-bold"
                            >
                                {{ statusLabel(cmsPage.status) }}
                            </span>
                        </label>
                        <p v-if="!pages.length" class="text-xs text-on-surface-variant px-1 py-2">Aucune page disponible.</p>
                    </div>
                    <CamaLoadingButton class="mt-3 w-full justify-center" :loading="addPagesForm.processing" @click="addSelectedPages">
                        Ajouter au menu
                    </CamaLoadingButton>
                    <p v-if="addPagesForm.errors.page_ids" class="text-error text-[10px] mt-2">{{ addPagesForm.errors.page_ids }}</p>
                </div>

                <div class="assure-card p-4">
                    <p class="text-sm font-bold mb-3 flex items-center gap-2">
                        <span class="material-symbols-outlined text-[18px] text-primary">link</span>
                        Lien personnalisé
                    </p>
                    <input
                        v-model="customForm.label"
                        class="w-full mb-2 px-3 py-2 text-sm border border-outline-variant rounded-lg focus:outline-none focus:border-primary"
                        placeholder="Texte du lien (ex. Espace assuré)"
                    />
                    <input
                        v-model="customForm.url"
                        class="w-full mb-2 px-3 py-2 text-sm border border-outline-variant rounded-lg focus:outline-none focus:border-primary"
                        placeholder="URL (ex. /espace-assure)"
                        @keyup.enter="addCustomLink"
                    />
                    <button
                        type="button"
                        class="w-full border border-primary text-primary py-2 rounded-lg text-xs font-bold disabled:opacity-60"
                        :disabled="customForm.processing || !customForm.label.trim()"
                        @click="addCustomLink"
                    >
                        Ajouter au menu
                    </button>
                    <p v-if="customForm.errors.label" class="text-error text-[10px] mt-2">{{ customForm.errors.label }}</p>
                    <p v-if="customForm.errors.url" class="text-error text-[10px] mt-2">{{ customForm.errors.url }}</p>
                </div>
            </div>

            <div class="assure-card p-4 lg:col-span-2">
                <div class="flex items-center justify-between mb-1">
                    <p class="text-sm font-bold flex items-center gap-2">
                        <span class="material-symbols-outlined text-[18px] text-primary">reorder</span>
                        Structure du menu
                    </p>
                    <span class="text-[11px] text-on-surface-variant flex items-center gap-1">
                        <span class="material-symbols-outlined text-[14px]">
                            {{ saveStatus === 'saving' ? 'cloud_sync' : saveStatus === 'error' ? 'error' : 'cloud_done' }}
                        </span>
                        {{ saveStatus === 'saving' ? 'Enregistrement…' : saveStatus === 'error' ? 'Erreur' : 'Enregistré' }}
                    </span>
                </div>
                <p class="text-[11px] text-on-surface-variant mb-3">Glissez-déposez pour réordonner. Utilisez « décaler » pour créer un sous-menu déroulant.</p>

                <div class="space-y-1.5">
                    <div
                        v-for="(item, index) in localItems"
                        :key="item.id"
                        class="menu-item flex items-center gap-2 bg-white border border-outline-variant rounded-lg px-2.5 py-2"
                        :class="{ 'ml-8': item.depth > 0, 'opacity-40': draggedIndex === index }"
                        draggable="true"
                        @dragstart="onDragStart(index)"
                        @dragover.prevent
                        @drop="onDrop(index)"
                    >
                        <span class="material-symbols-outlined text-[18px] text-on-surface-variant cursor-grab">drag_indicator</span>
                        <span v-if="item.depth > 0" class="material-symbols-outlined text-[16px] text-on-surface-variant">subdirectory_arrow_right</span>

                        <input
                            v-if="editingId === item.id"
                            v-model="editingLabel"
                            class="flex-1 px-2 py-1 text-sm border border-outline-variant rounded"
                            autofocus
                            @keyup.enter="applyRename(index)"
                            @keyup.esc="cancelRename"
                            @blur="applyRename(index)"
                        />
                        <span v-else class="font-semibold text-sm flex-1 truncate">{{ item.label }}</span>

                        <span
                            class="text-[9px] font-bold uppercase px-1.5 py-0.5 rounded"
                            :class="item.type === 'page' ? 'bg-primary/10 text-primary' : 'bg-tertiary-container/40 text-tertiary'"
                        >
                            {{ item.type === 'page' ? 'Page' : 'Lien' }}
                        </span>

                        <div class="flex items-center gap-0.5">
                            <button
                                type="button"
                                class="w-7 h-7 flex items-center justify-center rounded text-on-surface-variant hover:bg-surface-container disabled:opacity-25"
                                title="Promouvoir"
                                :disabled="item.depth === 0"
                                @click="outdentItem(index)"
                            >
                                <span class="material-symbols-outlined text-[17px]">format_indent_decrease</span>
                            </button>
                            <button
                                type="button"
                                class="w-7 h-7 flex items-center justify-center rounded text-on-surface-variant hover:bg-surface-container disabled:opacity-25"
                                title="Mettre en sous-menu"
                                :disabled="index === 0"
                                @click="indentItem(index)"
                            >
                                <span class="material-symbols-outlined text-[17px]">format_indent_increase</span>
                            </button>
                            <button
                                type="button"
                                class="w-7 h-7 flex items-center justify-center rounded text-on-surface-variant hover:bg-surface-container disabled:opacity-25"
                                title="Monter"
                                :disabled="index === 0"
                                @click="moveItem(index, index - 1)"
                            >
                                <span class="material-symbols-outlined text-[17px]">arrow_upward</span>
                            </button>
                            <button
                                type="button"
                                class="w-7 h-7 flex items-center justify-center rounded text-on-surface-variant hover:bg-surface-container disabled:opacity-25"
                                title="Descendre"
                                :disabled="index === localItems.length - 1"
                                @click="moveItem(index, index + 1)"
                            >
                                <span class="material-symbols-outlined text-[17px]">arrow_downward</span>
                            </button>
                            <button
                                type="button"
                                class="w-7 h-7 flex items-center justify-center rounded text-on-surface-variant hover:bg-surface-container"
                                title="Renommer"
                                @click="startRename(item)"
                            >
                                <span class="material-symbols-outlined text-[17px]">edit</span>
                            </button>
                            <button
                                type="button"
                                class="w-7 h-7 flex items-center justify-center rounded text-on-surface-variant hover:bg-error/10 hover:text-error"
                                title="Retirer"
                                @click="removeItem(item)"
                            >
                                <span class="material-symbols-outlined text-[17px]">close</span>
                            </button>
                        </div>
                    </div>

                    <div v-if="!localItems.length" class="text-center text-sm text-on-surface-variant py-8 border-2 border-dashed border-outline-variant rounded-lg">
                        Aucun élément. Ajoutez des pages depuis la gauche.
                    </div>
                </div>
            </div>
        </div>
    </CmsLayout>

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
