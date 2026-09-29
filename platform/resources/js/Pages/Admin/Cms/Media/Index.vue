<script setup>
import CamaConfirmModal from '@/Components/CamaConfirmModal.vue';
import CamaFilePicker from '@/Components/CamaFilePicker.vue';
import CamaLoadingButton from '@/Components/CamaLoadingButton.vue';
import { useCamaConfirm } from '@/Composables/useCamaConfirm';
import CmsLayout from '@/Layouts/CmsLayout.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    items: { type: Array, default: () => [] },
    options: { type: Object, default: () => ({}) },
    permissions: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
});

const page = usePage();
const toast = computed(() => page.props.flash?.success ?? null);
const flashError = computed(() => page.props.flash?.error ?? null);

const search = ref(props.filters?.q ?? '');
const activeType = ref(props.filters?.type ?? '');
const activeFolder = ref(props.filters?.folder ?? '');
const activeCategory = ref(props.filters?.category ?? '');
const activeExtension = ref(props.filters?.extension ?? '');
const copiedUrl = ref('');
const fileName = ref('');
const editingItem = ref(null);
let searchTimer = null;

const uploadForm = useForm({
    file: null,
    title: '',
    alt_text: '',
    folder: 'general',
    category: '',
    tags: '',
    description: '',
});

const metadataForm = useForm({
    title: '',
    alt_text: '',
    folder: 'general',
    category: '',
    tags: '',
    description: '',
});

const { confirmState, askConfirm, confirm, cancel } = useCamaConfirm();

watch([activeType, activeFolder, activeCategory, activeExtension], applyFilters);

function applyFilters() {
    router.get(
        route('admin.cms.media'),
        {
            q: search.value || undefined,
            type: activeType.value || undefined,
            folder: activeFolder.value || undefined,
            category: activeCategory.value || undefined,
            extension: activeExtension.value || undefined,
        },
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        },
    );
}

function onSearchInput() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(applyFilters, 250);
}

function onFileChange(event) {
    const file = event.target.files?.[0] ?? null;
    uploadForm.file = file;
    fileName.value = file?.name ?? '';
}

function uploadMedia() {
    uploadForm.post(route('admin.cms.media.store'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            uploadForm.reset();
            uploadForm.folder = 'general';
            fileName.value = '';
        },
    });
}

function openMetadataEditor(item) {
    editingItem.value = item;
    metadataForm.clearErrors();
    metadataForm.title = item.title || item.name;
    metadataForm.alt_text = item.altText || '';
    metadataForm.folder = item.folder || 'general';
    metadataForm.category = item.category || '';
    metadataForm.tags = item.tagsText || '';
    metadataForm.description = item.description || '';
}

function closeMetadataEditor() {
    editingItem.value = null;
    metadataForm.reset();
    metadataForm.clearErrors();
}

function saveMetadata() {
    if (!editingItem.value) return;

    metadataForm.put(route('admin.cms.media.update', editingItem.value.id), {
        preserveScroll: true,
        onSuccess: () => closeMetadataEditor(),
    });
}

function copyUrl(item) {
    navigator.clipboard?.writeText(item.url);
    copiedUrl.value = item.url;
    setTimeout(() => {
        if (copiedUrl.value === item.url) copiedUrl.value = '';
    }, 1600);
}

function deleteMedia(item) {
    const usageMessage = item.usageCount
        ? ` Attention : ce média est utilisé ${item.usageCount} fois (${item.usages.map((usage) => usage.module).join(', ')}).`
        : '';

    askConfirm({
        title: 'Supprimer ce média ?',
        message: `Le fichier « ${item.originalName} » sera retiré de la médiathèque.${usageMessage}`,
        confirmLabel: 'Supprimer',
        variant: 'danger',
        onConfirm: () => router.delete(route('admin.cms.media.destroy', item.id), { preserveScroll: true }),
    });
}
</script>

<template>
    <Head title="Médiathèque" />

    <CmsLayout active-nav="media" title="Médiathèque" subtitle="Centralisez les images et fichiers utilisés dans le site public">
        <div v-if="toast" class="rounded-xl border border-secondary/30 bg-secondary/10 px-4 py-3 text-sm text-secondary font-semibold">
            {{ toast }}
        </div>
        <div v-if="flashError" class="rounded-xl border border-error/30 bg-error/10 px-4 py-3 text-sm text-error font-semibold">
            {{ flashError }}
        </div>

        <section class="assure-card p-5 space-y-4">
            <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-[0.18em] text-primary font-bold">Bibliothèque</p>
                    <h2 class="text-xl font-bold text-on-surface">Ajouter un média</h2>
                    <p class="text-sm text-on-surface-variant">Images, PDF et documents jusqu’à 10 Mo.</p>
                </div>
                <div class="flex flex-wrap items-end gap-3">
                    <CamaFilePicker
                        accept="image/*,.pdf,.doc,.docx,.xls,.xlsx"
                        label="Choisir un fichier"
                        :file-name="fileName"
                        hint="Formats courants acceptés : JPG, PNG, WEBP, PDF, DOC, XLS."
                        @change="onFileChange"
                    />
                    <CamaLoadingButton
                        class="bg-primary text-on-primary px-4 py-2.5 rounded-lg text-sm font-bold"
                        :loading="uploadForm.processing"
                        :disabled="!uploadForm.file"
                        @click="uploadMedia"
                    >
                        Importer
                    </CamaLoadingButton>
                </div>
            </div>
            <div class="grid md:grid-cols-3 gap-3">
                <div>
                    <label class="text-[10px] uppercase tracking-wide font-bold text-on-surface-variant">Titre</label>
                    <input v-model="uploadForm.title" class="mt-1 w-full text-sm border border-outline-variant rounded-lg px-3 py-2" placeholder="Titre lisible du média" />
                </div>
                <div>
                    <label class="text-[10px] uppercase tracking-wide font-bold text-on-surface-variant">Texte alternatif</label>
                    <input v-model="uploadForm.alt_text" class="mt-1 w-full text-sm border border-outline-variant rounded-lg px-3 py-2" placeholder="Description pour l’accessibilité" />
                </div>
                <div>
                    <label class="text-[10px] uppercase tracking-wide font-bold text-on-surface-variant">Dossier</label>
                    <input v-model="uploadForm.folder" class="mt-1 w-full text-sm border border-outline-variant rounded-lg px-3 py-2" placeholder="general, pages, bannieres…" />
                </div>
                <div>
                    <label class="text-[10px] uppercase tracking-wide font-bold text-on-surface-variant">Catégorie</label>
                    <input v-model="uploadForm.category" class="mt-1 w-full text-sm border border-outline-variant rounded-lg px-3 py-2" placeholder="Institutionnel, actualités…" />
                </div>
                <div>
                    <label class="text-[10px] uppercase tracking-wide font-bold text-on-surface-variant">Tags</label>
                    <input v-model="uploadForm.tags" class="mt-1 w-full text-sm border border-outline-variant rounded-lg px-3 py-2" placeholder="hero, cama, famille" />
                </div>
                <div>
                    <label class="text-[10px] uppercase tracking-wide font-bold text-on-surface-variant">Description</label>
                    <input v-model="uploadForm.description" class="mt-1 w-full text-sm border border-outline-variant rounded-lg px-3 py-2" placeholder="Note interne" />
                </div>
            </div>
            <p v-if="uploadForm.errors.file" class="text-xs text-error font-semibold">{{ uploadForm.errors.file }}</p>
        </section>

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2">
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-2.5 top-1/2 -translate-y-1/2 text-[18px] text-on-surface-variant">search</span>
                    <input
                        v-model="search"
                        class="pl-9 pr-3 py-2 text-sm border border-outline-variant rounded-lg w-64 max-w-full focus:outline-none focus:border-primary"
                        placeholder="Rechercher un média…"
                        @input="onSearchInput"
                    />
                </div>
                <select v-model="activeType" class="text-sm border border-outline-variant rounded-lg px-2 py-2">
                    <option value="">Tous les médias</option>
                    <option value="images">Images uniquement</option>
                    <option value="documents">Documents uniquement</option>
                </select>
                <select v-model="activeFolder" class="text-sm border border-outline-variant rounded-lg px-2 py-2">
                    <option value="">Tous les dossiers</option>
                    <option v-for="folder in options.folders ?? []" :key="folder" :value="folder">{{ folder }}</option>
                </select>
                <select v-model="activeCategory" class="text-sm border border-outline-variant rounded-lg px-2 py-2">
                    <option value="">Toutes les catégories</option>
                    <option v-for="category in options.categories ?? []" :key="category" :value="category">{{ category }}</option>
                </select>
                <select v-model="activeExtension" class="text-sm border border-outline-variant rounded-lg px-2 py-2">
                    <option value="">Toutes les extensions</option>
                    <option v-for="extension in options.extensions ?? []" :key="extension" :value="extension">{{ extension.toUpperCase() }}</option>
                </select>
            </div>
            <p class="text-sm text-on-surface-variant">{{ items.length }} média{{ items.length > 1 ? 's' : '' }}</p>
        </div>

        <section v-if="items.length" class="grid sm:grid-cols-2 xl:grid-cols-4 gap-4">
            <article v-for="item in items" :key="item.id" class="assure-card overflow-hidden">
                <div class="h-40 bg-surface-container-low border-b border-outline-variant flex items-center justify-center">
                    <img v-if="item.isImage" :src="item.url" :alt="item.originalName" class="w-full h-full object-cover" />
                    <div v-else class="text-center text-on-surface-variant">
                        <span class="material-symbols-outlined text-5xl">description</span>
                        <p class="text-xs font-bold">{{ item.extension }}</p>
                    </div>
                </div>
                <div class="p-4 space-y-3">
                    <div>
                        <h3 class="font-bold text-sm text-on-surface truncate" :title="item.title">{{ item.title }}</h3>
                        <p class="text-[11px] text-on-surface-variant truncate" :title="item.originalName">{{ item.originalName }}</p>
                        <p class="text-[11px] text-on-surface-variant">
                            {{ item.size }}
                            <span v-if="item.width && item.height"> · {{ item.width }}×{{ item.height }}</span>
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-1">
                        <span class="px-2 py-0.5 rounded-full bg-surface-container-low text-[10px] font-bold text-on-surface-variant">{{ item.folder }}</span>
                        <span v-if="item.category" class="px-2 py-0.5 rounded-full bg-primary/10 text-[10px] font-bold text-primary">{{ item.category }}</span>
                        <span v-if="item.usageCount" class="px-2 py-0.5 rounded-full bg-tertiary/10 text-[10px] font-bold text-tertiary">{{ item.usageCount }} usage(s)</span>
                    </div>
                    <p class="text-[11px] text-on-surface-variant">Ajouté le {{ item.createdAt }} par {{ item.uploadedBy }}</p>
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-1">
                            <button
                                class="inline-flex items-center gap-1 text-xs font-bold text-primary hover:underline"
                                type="button"
                                @click="copyUrl(item)"
                            >
                                <span class="material-symbols-outlined text-[16px]">{{ copiedUrl === item.url ? 'check' : 'content_copy' }}</span>
                                {{ copiedUrl === item.url ? 'Copié' : 'Copier URL' }}
                            </button>
                        </div>
                        <div class="flex items-center gap-1">
                            <button
                                class="w-8 h-8 flex items-center justify-center rounded-lg text-on-surface-variant hover:bg-primary/10 hover:text-primary"
                                type="button"
                                title="Métadonnées"
                                @click="openMetadataEditor(item)"
                            >
                                <span class="material-symbols-outlined text-[18px]">edit</span>
                            </button>
                            <button
                                v-if="permissions.canDeleteMedia"
                                class="w-8 h-8 flex items-center justify-center rounded-lg text-on-surface-variant hover:bg-error/10 hover:text-error"
                                type="button"
                                title="Supprimer"
                                @click="deleteMedia(item)"
                            >
                                <span class="material-symbols-outlined text-[18px]">delete</span>
                            </button>
                        </div>
                    </div>
                </div>
            </article>
        </section>

        <section v-else class="assure-card p-10 text-center">
            <span class="material-symbols-outlined text-5xl text-on-surface-variant">perm_media</span>
            <h2 class="mt-3 text-lg font-bold text-on-surface">Aucun média trouvé</h2>
            <p class="text-sm text-on-surface-variant">Importez votre premier fichier pour l’utiliser dans les pages et widgets.</p>
        </section>

        <div v-if="editingItem" class="fixed inset-0 z-[70] bg-black/50 flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl w-full max-w-2xl max-h-[90vh] overflow-auto shadow-2xl">
                <div class="px-5 py-4 border-b border-outline-variant flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-bold text-on-surface">Métadonnées du média</h2>
                        <p class="text-[11px] text-on-surface-variant">{{ editingItem.originalName }}</p>
                    </div>
                    <button class="text-on-surface-variant hover:text-primary" type="button" @click="closeMetadataEditor">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <div class="p-5 grid md:grid-cols-2 gap-4">
                    <div v-if="editingItem.isImage" class="md:col-span-2 rounded-xl overflow-hidden border border-outline-variant bg-surface-container-low">
                        <img :src="editingItem.url" :alt="metadataForm.alt_text || editingItem.originalName" class="w-full max-h-64 object-contain" />
                    </div>
                    <div>
                        <label class="text-[10px] uppercase tracking-wide font-bold text-on-surface-variant">Titre</label>
                        <input v-model="metadataForm.title" class="mt-1 w-full text-sm border border-outline-variant rounded-lg px-3 py-2" />
                    </div>
                    <div>
                        <label class="text-[10px] uppercase tracking-wide font-bold text-on-surface-variant">Texte alternatif</label>
                        <input v-model="metadataForm.alt_text" class="mt-1 w-full text-sm border border-outline-variant rounded-lg px-3 py-2" />
                    </div>
                    <div>
                        <label class="text-[10px] uppercase tracking-wide font-bold text-on-surface-variant">Dossier</label>
                        <input v-model="metadataForm.folder" class="mt-1 w-full text-sm border border-outline-variant rounded-lg px-3 py-2" />
                    </div>
                    <div>
                        <label class="text-[10px] uppercase tracking-wide font-bold text-on-surface-variant">Catégorie</label>
                        <input v-model="metadataForm.category" class="mt-1 w-full text-sm border border-outline-variant rounded-lg px-3 py-2" />
                    </div>
                    <div>
                        <label class="text-[10px] uppercase tracking-wide font-bold text-on-surface-variant">Tags</label>
                        <input v-model="metadataForm.tags" class="mt-1 w-full text-sm border border-outline-variant rounded-lg px-3 py-2" />
                    </div>
                    <div>
                        <label class="text-[10px] uppercase tracking-wide font-bold text-on-surface-variant">URL</label>
                        <input :value="editingItem.url" class="mt-1 w-full text-sm border border-outline-variant rounded-lg px-3 py-2 bg-surface-container-low" readonly />
                    </div>
                    <div class="md:col-span-2">
                        <label class="text-[10px] uppercase tracking-wide font-bold text-on-surface-variant">Description interne</label>
                        <textarea v-model="metadataForm.description" rows="3" class="mt-1 w-full text-sm border border-outline-variant rounded-lg px-3 py-2" />
                    </div>
                    <div class="md:col-span-2 rounded-xl border border-outline-variant bg-surface-container-low p-3">
                        <p class="text-xs font-bold text-on-surface mb-2">Utilisations détectées</p>
                        <div v-if="editingItem.usages.length" class="space-y-1">
                            <a
                                v-for="usage in editingItem.usages"
                                :key="`${usage.module}-${usage.label}`"
                                :href="usage.url"
                                class="flex items-center justify-between gap-2 text-xs bg-white rounded-lg px-3 py-2 hover:text-primary"
                            >
                                <span><strong>{{ usage.module }}</strong> · {{ usage.label }}</span>
                                <span class="material-symbols-outlined text-[16px]">open_in_new</span>
                            </a>
                        </div>
                        <p v-else class="text-xs text-on-surface-variant">Aucun usage détecté dans les pages, actualités, slides, partenaires ou ressources.</p>
                    </div>
                </div>
                <div class="px-5 py-4 border-t border-outline-variant bg-surface-container-low flex justify-end gap-2">
                    <button class="px-4 py-2 text-xs font-bold border border-outline-variant rounded-lg bg-white" type="button" @click="closeMetadataEditor">Annuler</button>
                    <CamaLoadingButton :loading="metadataForm.processing" loading-text="Mise à jour…" @click="saveMetadata">
                        Enregistrer
                    </CamaLoadingButton>
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
    </CmsLayout>
</template>
