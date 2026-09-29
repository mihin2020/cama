<script setup>
import CmsLayout from '@/Layouts/CmsLayout.vue';
import CamaConfirmModal from '@/Components/CamaConfirmModal.vue';
import CamaFilePicker from '@/Components/CamaFilePicker.vue';
import CamaLoadingButton from '@/Components/CamaLoadingButton.vue';
import CamaSimpleRichText from '@/Components/CamaSimpleRichText.vue';
import { useCamaConfirm } from '@/Composables/useCamaConfirm';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    slides: Array,
    banner: Object,
});

const page = usePage();
const editorOpen = ref(false);
const editingId = ref(null);
const filePickerRef = ref(null);
const localPreviewUrl = ref(null);
const editorImageError = ref(false);
const selectedFileName = ref('');

const bannerForm = useForm({
    active: props.banner.active,
    type: props.banner.type,
    message: props.banner.message,
});

const slideForm = useForm({
    title: '',
    subtitle: '',
    image_url: '',
    image_file: null,
    sort_order: 1,
    active: true,
});

const { confirmState, askConfirm, confirm, cancel } = useCamaConfirm();

const toast = computed(() => page.props.flash?.success ?? null);

const editorImageSrc = computed(() => {
    if (localPreviewUrl.value) return localPreviewUrl.value;
    return resolveImageSrc(slideForm.image_url);
});

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
    slideForm.image_file = null;
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

    slideForm.image_file = file;
    selectedFileName.value = file.name;
    localPreviewUrl.value = URL.createObjectURL(file);
    editorImageError.value = false;
}

function saveBanner() {
    bannerForm.put(route('admin.cms.banniere.bandeau'), { preserveScroll: true });
}

function openSlideEditor(item = null) {
    editingId.value = item?.id ?? null;
    slideForm.reset();
    slideForm.clearErrors();
    clearLocalPreview();
    editorImageError.value = false;

    if (item) {
        slideForm.title = item.title;
        slideForm.subtitle = item.subtitle ?? '';
        slideForm.image_url = item.image_url ?? '';
        slideForm.sort_order = item.sort_order;
        slideForm.active = item.active;
    } else {
        slideForm.sort_order = (props.slides?.length ?? 0) + 1;
        slideForm.active = true;
    }

    editorOpen.value = true;
}

function closeSlideEditor() {
    clearLocalPreview();
    editorOpen.value = false;
    editingId.value = null;
}

function removeSlideImage() {
    clearLocalPreview();
    slideForm.image_url = '';
    editorImageError.value = false;
}

function saveSlide() {
    const options = {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => closeSlideEditor(),
    };

    if (editingId.value) {
        slideForm.transform((data) => ({ ...data, _method: 'put' }))
            .post(route('admin.cms.banniere.slides.update', editingId.value), options);
    } else {
        slideForm.post(route('admin.cms.banniere.slides.store'), options);
    }
}

function removeSlide(id) {
    askConfirm({
        title: 'Supprimer ce slide ?',
        message: 'Ce slide sera retiré du carousel de la page d\'accueil.',
        confirmLabel: 'Supprimer',
        variant: 'danger',
        onConfirm: () => router.delete(route('admin.cms.banniere.slides.destroy', id), { preserveScroll: true }),
    });
}
</script>

<template>
    <Head title="Bannière d'accueil" />

    <CmsLayout active-nav="banniere" title="Bannière d'accueil" subtitle="Carousel accueil & bandeau d'alerte site public">
        <section class="assure-card p-5 space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-bold text-on-surface">Bandeau d'information (site public)</h2>
                <label class="flex items-center gap-2 text-xs font-semibold">
                    <input v-model="bannerForm.active" class="rounded border-outline-variant text-primary" type="checkbox" />
                    Actif
                </label>
            </div>

            <div class="max-w-xl">
                <label class="text-[11px] font-bold uppercase text-on-surface-variant block mb-1">Type</label>
                <select v-model="bannerForm.type" class="w-full px-3 py-2 text-xs border border-outline-variant rounded-lg">
                    <option value="info">Information</option>
                    <option value="warning">Alerte</option>
                </select>
            </div>

            <div>
                <label class="text-[11px] font-bold uppercase text-on-surface-variant block mb-1">Message</label>
                <p class="text-[10px] text-on-surface-variant mb-2">
                    Saisissez le texte du bandeau. Sélectionnez une portion pour la mettre en <strong>gras</strong>, en <em>italique</em>, ajuster la taille ou insérer un <span class="underline">lien</span>.
                </p>
                <CamaSimpleRichText v-model="bannerForm.message" placeholder="Ex. Campagne d'enrôlement 2026 : créez votre espace assuré…" />
                <p v-if="bannerForm.errors.message" class="text-error text-[10px] mt-1">{{ bannerForm.errors.message }}</p>
            </div>

            <div v-if="bannerForm.message" class="rounded-lg border border-outline-variant overflow-hidden">
                <p class="text-[10px] uppercase font-bold text-on-surface-variant px-3 py-1.5 bg-surface-container-low/60 border-b border-outline-variant">
                    Aperçu sur le site
                </p>
                <div
                    class="px-4 py-2.5 text-xs flex items-center gap-2"
                    :class="bannerForm.type === 'warning' ? 'bg-tertiary text-white' : 'bg-primary text-on-primary'"
                >
                    <span class="material-symbols-outlined text-[18px] shrink-0">
                        {{ bannerForm.type === 'warning' ? 'warning' : 'campaign' }}
                    </span>
                    <div class="banner-preview min-w-0 flex-1" v-html="bannerForm.message" />
                </div>
            </div>

            <CamaLoadingButton
                type="button"
                :loading="bannerForm.processing"
                loading-text="Publication…"
                @click="saveBanner"
            >
                Publier le bandeau sur le site
            </CamaLoadingButton>
        </section>

        <section class="flex items-center justify-between mt-6">
            <h2 class="text-sm font-bold text-on-surface">Slides du carousel (page d'accueil)</h2>
            <button class="bg-primary text-on-primary px-4 py-2 rounded-lg text-xs font-bold flex items-center gap-1.5" type="button" @click="openSlideEditor()">
                <span class="material-symbols-outlined text-[16px]">add</span>
                Nouveau slide
            </button>
        </section>

        <div v-if="slides.length" class="grid grid-cols-1 lg:grid-cols-2 gap-4 mt-4">
            <div v-for="slide in slides" :key="slide.id" class="assure-card overflow-hidden">
                <div
                    class="aspect-[16/6] bg-cover bg-center bg-surface-container-high"
                    :style="slide.image_src ? { backgroundImage: `linear-gradient(rgba(0,0,0,.4),rgba(0,0,0,.4)),url('${slide.image_src}')` } : {}"
                />
                <div class="p-4 space-y-2">
                    <div class="flex items-start justify-between gap-2">
                        <h3 class="text-sm font-bold text-on-surface">{{ slide.title }}</h3>
                        <span
                            class="text-[10px] px-2 py-0.5 rounded-full shrink-0"
                            :class="slide.active ? 'bg-secondary text-on-secondary' : 'bg-surface-container-high text-on-surface-variant'"
                        >
                            {{ slide.active ? 'Publié' : 'Brouillon' }}
                        </span>
                    </div>
                    <p class="text-xs text-on-surface-variant line-clamp-2">{{ slide.subtitle }}</p>
                    <div class="flex justify-between items-center pt-2">
                        <span class="text-[10px] text-on-surface-variant">Ordre {{ slide.sort_order }}</span>
                        <div>
                            <button class="text-on-surface-variant hover:text-primary" type="button" title="Modifier" @click="openSlideEditor(slide)">
                                <span class="material-symbols-outlined text-[18px]">edit</span>
                            </button>
                            <button class="text-on-surface-variant hover:text-error ml-2" type="button" title="Supprimer" @click="removeSlide(slide.id)">
                                <span class="material-symbols-outlined text-[18px]">delete</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <p v-else class="text-sm text-on-surface-variant mt-4">Aucun slide. Ajoutez-en un.</p>
    </CmsLayout>

    <!-- Éditeur slide -->
    <div
        v-if="editorOpen"
        class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4"
        @click.self="closeSlideEditor"
    >
        <div class="bg-white rounded-xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between p-5 border-b border-outline-variant">
                <h3 class="font-semibold text-sm">{{ editingId ? 'Modifier le slide' : 'Nouveau slide' }}</h3>
                <button type="button" @click="closeSlideEditor">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>

            <form class="p-5 space-y-4" @submit.prevent="saveSlide">
                <div>
                    <label class="text-[11px] font-bold uppercase text-on-surface-variant block mb-1">Titre</label>
                    <input v-model="slideForm.title" class="w-full px-3 py-2 text-xs border border-outline-variant rounded-lg" required />
                    <p v-if="slideForm.errors.title" class="text-error text-[10px] mt-1">{{ slideForm.errors.title }}</p>
                </div>

                <div>
                    <label class="text-[11px] font-bold uppercase text-on-surface-variant block mb-1">Sous-titre</label>
                    <textarea v-model="slideForm.subtitle" class="w-full px-3 py-2 text-xs border border-outline-variant rounded-lg" rows="2" />
                </div>

                <div>
                    <label class="text-[11px] font-bold uppercase text-on-surface-variant block mb-1">Image</label>
                    <div v-if="editorImageSrc && !editorImageError" class="mb-2 rounded-lg overflow-hidden border border-outline-variant">
                        <img :src="editorImageSrc" alt="Aperçu" class="w-full h-28 object-cover" @error="editorImageError = true" />
                    </div>
                    <CamaFilePicker
                        ref="filePickerRef"
                        accept="image/jpeg,image/jpg,image/png,image/webp,.jfif"
                        label="Choisir une image"
                        hint="JPEG, PNG, WebP — 5 Mo max. Aperçu immédiat après sélection."
                        :file-name="selectedFileName"
                        @change="onImageSelected"
                    >
                        <template #actions>
                            <button
                                v-if="editorImageSrc"
                                class="inline-flex items-center gap-1 px-3 py-2 text-xs font-bold border border-outline-variant rounded-lg text-error"
                                type="button"
                                @click="removeSlideImage"
                            >
                                <span class="material-symbols-outlined text-[16px]">delete</span>
                                Retirer
                            </button>
                        </template>
                    </CamaFilePicker>
                    <details class="text-xs mt-2">
                        <summary class="cursor-pointer text-on-surface-variant hover:text-primary">Ou utiliser un chemin existant</summary>
                        <input
                            v-model="slideForm.image_url"
                            class="w-full mt-2 px-3 py-2 text-xs border border-outline-variant rounded-lg bg-white"
                            placeholder="images/CAMA_8.jfif"
                            @input="editorImageError = false"
                        />
                    </details>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-[11px] font-bold uppercase text-on-surface-variant block mb-1">Ordre</label>
                        <input v-model.number="slideForm.sort_order" class="w-full px-3 py-2 text-xs border border-outline-variant rounded-lg" min="1" type="number" />
                    </div>
                    <div class="flex items-end pb-2">
                        <label class="flex items-center gap-2 text-xs font-semibold">
                            <input v-model="slideForm.active" class="rounded border-outline-variant text-primary" type="checkbox" />
                            Publié
                        </label>
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button class="px-4 py-2 text-xs font-bold border border-outline-variant rounded-lg" type="button" :disabled="slideForm.processing" @click="closeSlideEditor">Annuler</button>
                    <CamaLoadingButton type="submit" :loading="slideForm.processing">
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

<style scoped>
.banner-preview :deep(a) {
    color: inherit;
    text-decoration: underline;
    font-weight: 600;
}
</style>
