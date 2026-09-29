<script setup>
import CmsLayout from '@/Layouts/CmsLayout.vue';
import CamaConfirmModal from '@/Components/CamaConfirmModal.vue';
import CamaFilePicker from '@/Components/CamaFilePicker.vue';
import CamaLoadingButton from '@/Components/CamaLoadingButton.vue';
import CamaGoogleMap from '@/Components/CamaGoogleMap.vue';
import { useCamaConfirm } from '@/Composables/useCamaConfirm';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    items: Array,
    types: Array,
    activeType: { type: String, default: null },
});

const mapPoints = computed(() => (props.items || [])
    .filter(item => typeof item.lat === 'number' && typeof item.lon === 'number')
    .map(item => ({
        id: item.id,
        lat: item.lat,
        lon: item.lon,
        name: item.nom,
        type: item.type,
        city: item.ville,
        description: item.adresse || item.description,
        mapsUrl: item.mapsUrl,
        imageSrc: item.image_src,
    })));

const selectedMapId = ref(null);
const mapFocusPoint = computed(() => {
    const point = mapPoints.value.find(item => item.id === selectedMapId.value);
    if (!point) return null;
    return { lat: point.lat, lon: point.lon, zoom: 14, key: point.id };
});

function focusOnMap(item) {
    if (typeof item.lat !== 'number' || typeof item.lon !== 'number') return;
    selectedMapId.value = item.id;
}

const geolocatedCount = computed(() => mapPoints.value.length);
const missingCount = computed(() => (props.items?.length ?? 0) - geolocatedCount.value);

const page = usePage();
const googleMapsApiKey = computed(() => page.props.app?.googleMapsApiKey ?? '');
const editorOpen = ref(false);
const editingId = ref(null);
const filePickerRef = ref(null);
const selectedFileName = ref('');
const imagePreview = ref('');

const form = useForm({
    nom: '',
    type: 'Centre de santé',
    ville: '',
    adresse: '',
    telephone: '',
    email: '',
    horaires: '',
    description: '',
    image_url: '',
    image_file: null,
    lat: null,
    lon: null,
    mapsUrl: '',
    ordre: 1,
    publie: true,
});

const { confirmState, askConfirm, confirm, cancel } = useCamaConfirm();

const toast = computed(() => page.props.flash?.success ?? null);

const typePills = computed(() => ['Tous', ...(props.types ?? [])]);

function setTypeFilter(type) {
    router.get(
        route('admin.cms.partenaires'),
        type && type !== 'Tous' ? { type } : {},
        { preserveState: true, preserveScroll: true },
    );
}

function resolveImageSrc(url) {
    if (!url) return '';
    if (url.startsWith('http://') || url.startsWith('https://') || url.startsWith('data:')) return url;
    return '/' + url.replace(/^\/+/, '');
}

function clearImageFile() {
    form.image_file = null;
    selectedFileName.value = '';
    filePickerRef.value?.clear();
}

function onImageSelected(event) {
    const file = event.target.files?.[0];
    if (!file) return;

    if (file.size > 2 * 1024 * 1024) {
        askConfirm({
            title: 'Image trop volumineuse',
            message: 'Taille maximale : 2 Mo. Utilisez une URL externe pour les images plus lourdes.',
            variant: 'warning',
            alertOnly: true,
        });
        event.target.value = '';
        return;
    }

    form.image_file = file;
    selectedFileName.value = file.name;
    const reader = new FileReader();
    reader.onload = () => { imagePreview.value = reader.result; };
    reader.readAsDataURL(file);
}

function onImageUrlInput() {
    if (form.image_url) {
        clearImageFile();
        imagePreview.value = resolveImageSrc(form.image_url);
    } else {
        imagePreview.value = '';
    }
}

function openEditor(item = null) {
    editingId.value = item?.id ?? null;
    form.reset();
    form.clearErrors();
    clearImageFile();
    imagePreview.value = '';

    if (item) {
        form.nom = item.nom;
        form.type = item.type;
        form.ville = item.ville ?? '';
        form.adresse = item.adresse ?? '';
        form.telephone = item.telephone ?? '';
        form.email = item.email ?? '';
        form.horaires = item.horaires ?? '';
        form.description = item.description ?? '';
        form.image_url = item.image ?? '';
        form.lat = (typeof item.lat === 'number') ? item.lat : null;
        form.lon = (typeof item.lon === 'number') ? item.lon : null;
        form.mapsUrl = item.mapsUrl ?? '';
        form.ordre = item.ordre;
        form.publie = item.publie;
        imagePreview.value = item.image_src || resolveImageSrc(item.image);
        focusOnMap(item);
    } else {
        form.type = 'Centre de santé';
        form.ordre = (props.items?.length ?? 0) + 1;
        form.publie = true;
    }

    editorOpen.value = true;
}

function closeEditor() {
    editorOpen.value = false;
    editingId.value = null;
    clearImageFile();
    imagePreview.value = '';
}

function savePartner() {
    const options = {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => closeEditor(),
    };

    if (editingId.value) {
        form.transform((data) => ({ ...data, _method: 'put' }))
            .post(route('admin.cms.partenaires.update', editingId.value), options);
    } else {
        form.post(route('admin.cms.partenaires.store'), options);
    }
}

function removePartner(item) {
    askConfirm({
        title: 'Supprimer cette entrée ?',
        message: `« ${item.nom} » sera retiré du site et de la carte des partenaires.`,
        confirmLabel: 'Supprimer',
        variant: 'danger',
        onConfirm: () => router.delete(route('admin.cms.partenaires.destroy', item.id), { preserveScroll: true }),
    });
}
</script>

<template>
    <Head title="Partenaires & centres" />

    <CmsLayout
        active-nav="partenaires"
        title="Partenaires & centres de santé"
        subtitle="Antennes CAMA, centres de santé et partenaires — affichés sur la page Contact"
    >
        <div class="assure-card p-5 mb-5">
            <div class="flex items-center justify-between mb-3">
                <div>
                    <h2 class="font-title-lg text-sm font-bold flex items-center gap-2">
                        <span class="material-symbols-outlined text-[18px] text-primary">map</span>
                        Cartographie des partenaires
                    </h2>
                    <p class="text-[11px] text-on-surface-variant mt-1">
                        {{ geolocatedCount }} entité(s) géolocalisée(s)
                        <span v-if="missingCount > 0" class="text-tertiary"> · {{ missingCount }} sans coordonnées</span>
                    </p>
                </div>
                <div class="hidden md:flex gap-3 text-[11px] flex-wrap">
                    <span class="inline-flex items-center gap-1.5">
                        <span class="w-3 h-3 rounded-full inline-block" style="background:#1a3a6b"></span>
                        Siège CAMA
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <span class="w-3 h-3 rounded-full inline-block" style="background:#5c403f"></span>
                        Antennes CAMA
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <span class="w-3 h-3 rounded-full inline-block" style="background:#9e001f"></span>
                        Centres de santé
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <span class="w-3 h-3 rounded-full inline-block" style="background:#006e27"></span>
                        Partenaires
                    </span>
                </div>
            </div>
            <CamaGoogleMap :api-key="googleMapsApiKey" :points="mapPoints" :focus-point="mapFocusPoint" height="420px" />
        </div>

        <div class="flex flex-wrap gap-2 items-center justify-between">
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="pill in typePills"
                    :key="pill"
                    type="button"
                    class="px-3 py-1.5 rounded-full text-[11px] font-semibold border"
                    :class="((pill === 'Tous' && !activeType) || pill === activeType) ? 'bg-primary text-on-primary border-primary' : 'bg-white text-on-surface-variant border-outline-variant'"
                    @click="setTypeFilter(pill)"
                >
                    {{ pill }}
                </button>
            </div>
            <button
                type="button"
                class="bg-primary text-on-primary px-4 py-2 rounded-lg text-xs font-bold flex items-center gap-1.5"
                @click="openEditor()"
            >
                <span class="material-symbols-outlined text-[16px]">add</span>
                Nouvelle entrée
            </button>
        </div>

        <div class="assure-card overflow-x-auto mt-4">
            <table class="w-full text-xs">
                <thead>
                    <tr class="border-b border-outline-variant text-left text-[10px] uppercase text-on-surface-variant">
                        <th class="p-3">Nom</th>
                        <th class="p-3">Type</th>
                        <th class="p-3">Ville</th>
                        <th class="p-3">Carte</th>
                        <th class="p-3">Statut</th>
                        <th class="p-3" />
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="item in items" :key="item.id" class="border-b border-outline-variant last:border-0 cursor-pointer hover:bg-surface-container-low/60" :class="selectedMapId === item.id ? 'bg-primary/5' : ''" @click="focusOnMap(item)">
                        <td class="p-3 font-semibold max-w-xs truncate">{{ item.nom }}</td>
                        <td class="p-3 text-on-surface-variant">{{ item.type }}</td>
                        <td class="p-3 text-on-surface-variant">{{ item.ville || '—' }}</td>
                        <td class="p-3">
                            <span
                                v-if="typeof item.lat === 'number' && typeof item.lon === 'number'"
                                class="material-symbols-outlined text-[16px] text-secondary"
                                title="Géolocalisé"
                            >check_circle</span>
                            <span v-else class="text-on-surface-variant">—</span>
                        </td>
                        <td class="p-3">
                            <span
                                class="px-2 py-0.5 rounded-full text-[10px] font-semibold"
                                :class="item.publie ? 'bg-secondary text-on-secondary' : 'bg-surface-container-high text-on-surface-variant'"
                            >
                                {{ item.publie ? 'Publié' : 'Brouillon' }}
                            </span>
                        </td>
                        <td class="p-3 text-right whitespace-nowrap" @click.stop>
                            <button type="button" title="Modifier" @click="openEditor(item)">
                                <span class="material-symbols-outlined text-[18px]">edit</span>
                            </button>
                            <button type="button" class="ml-2" title="Supprimer" @click="removePartner(item)">
                                <span class="material-symbols-outlined text-[18px] text-error">delete</span>
                            </button>
                        </td>
                    </tr>
                    <tr v-if="!items.length">
                        <td class="p-6 text-center text-on-surface-variant" colspan="6">Aucune entrée.</td>
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
                <h3 class="font-semibold text-sm">{{ editingId ? 'Modifier l\'entrée' : 'Nouvelle entrée' }}</h3>
                <button type="button" :disabled="form.processing" @click="closeEditor">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>

            <form class="p-5 space-y-4" @submit.prevent="savePartner">
                <div>
                    <label class="text-[11px] font-bold uppercase text-on-surface-variant block mb-1">Nom</label>
                    <input v-model="form.nom" class="w-full px-3 py-2 text-xs border border-outline-variant rounded-lg" required />
                    <p v-if="form.errors.nom" class="text-error text-[10px] mt-1">{{ form.errors.nom }}</p>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-[11px] font-bold uppercase text-on-surface-variant block mb-1">Type</label>
                        <select v-model="form.type" class="w-full px-3 py-2 text-xs border border-outline-variant rounded-lg">
                            <option v-for="t in types" :key="t" :value="t">{{ t }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-[11px] font-bold uppercase text-on-surface-variant block mb-1">Ville</label>
                        <input v-model="form.ville" class="w-full px-3 py-2 text-xs border border-outline-variant rounded-lg" />
                    </div>
                </div>

                <div>
                    <label class="text-[11px] font-bold uppercase text-on-surface-variant block mb-1">Adresse</label>
                    <input v-model="form.adresse" class="w-full px-3 py-2 text-xs border border-outline-variant rounded-lg" placeholder="Ex : Camp militaire, Bobo-Dioulasso" />
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-[11px] font-bold uppercase text-on-surface-variant block mb-1">Téléphone</label>
                        <input v-model="form.telephone" class="w-full px-3 py-2 text-xs border border-outline-variant rounded-lg" placeholder="+226 …" />
                    </div>
                    <div>
                        <label class="text-[11px] font-bold uppercase text-on-surface-variant block mb-1">Email</label>
                        <input v-model="form.email" class="w-full px-3 py-2 text-xs border border-outline-variant rounded-lg" placeholder="contact@cama.bf" type="email" />
                    </div>
                </div>

                <div>
                    <label class="text-[11px] font-bold uppercase text-on-surface-variant block mb-1">Horaires</label>
                    <input v-model="form.horaires" class="w-full px-3 py-2 text-xs border border-outline-variant rounded-lg" placeholder="Lun - Ven : 08h00 - 15h00" />
                </div>

                <div>
                    <label class="text-[11px] font-bold uppercase text-on-surface-variant block mb-1">Description complémentaire</label>
                    <textarea v-model="form.description" class="w-full px-3 py-2 text-xs border border-outline-variant rounded-lg" rows="2" />
                </div>

                <div>
                    <label class="text-[11px] font-bold uppercase text-on-surface-variant block mb-1">Image</label>
                    <CamaFilePicker
                        ref="filePickerRef"
                        accept=".png,.jpg,.jpeg,.webp"
                        label="Choisir une image"
                        :file-name="selectedFileName"
                        @change="onImageSelected"
                    />
                    <input
                        v-model="form.image_url"
                        class="w-full px-3 py-2 text-xs border border-outline-variant rounded-lg mt-2"
                        placeholder="… ou chemin/URL d'image (ex : images/CAMA_1.jfif)"
                        type="text"
                        @input="onImageUrlInput"
                    />
                    <div v-if="imagePreview" class="mt-2">
                        <img :src="imagePreview" class="h-20 rounded-lg border border-outline-variant object-cover" alt="Aperçu" />
                    </div>
                    <p v-if="form.errors.image_file" class="text-error text-[10px] mt-1">{{ form.errors.image_file }}</p>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-[11px] font-bold uppercase text-on-surface-variant block mb-1">Latitude</label>
                        <input v-model.number="form.lat" class="w-full px-3 py-2 text-xs border border-outline-variant rounded-lg" placeholder="12.3714" step="any" type="number" />
                    </div>
                    <div>
                        <label class="text-[11px] font-bold uppercase text-on-surface-variant block mb-1">Longitude</label>
                        <input v-model.number="form.lon" class="w-full px-3 py-2 text-xs border border-outline-variant rounded-lg" placeholder="-1.5197" step="any" type="number" />
                    </div>
                </div>
                <p class="text-[10px] text-on-surface-variant -mt-2">Renseignez latitude et longitude pour afficher l'entité sur la cartographie de la page Contact.</p>

                <div>
                    <label class="text-[11px] font-bold uppercase text-on-surface-variant block mb-1">Lien Google Maps</label>
                    <input v-model="form.mapsUrl" class="w-full px-3 py-2 text-xs border border-outline-variant rounded-lg" placeholder="https://maps.google.com/…" type="url" />
                    <p class="text-[10px] text-on-surface-variant mt-1">
                        Lien direct vers la fiche Google Maps (bouton « Google Maps » côté public). À défaut, un lien sera généré depuis les coordonnées.
                    </p>
                    <p v-if="form.errors.mapsUrl" class="text-error text-[10px] mt-1">{{ form.errors.mapsUrl }}</p>
                </div>

                <div class="grid grid-cols-2 gap-3 items-end">
                    <div>
                        <label class="text-[11px] font-bold uppercase text-on-surface-variant block mb-1">Ordre</label>
                        <input v-model.number="form.ordre" class="w-full px-3 py-2 text-xs border border-outline-variant rounded-lg" min="1" type="number" />
                    </div>
                    <div>
                        <label class="flex items-center gap-2 text-xs font-semibold pb-2">
                            <input v-model="form.publie" class="rounded border-outline-variant text-primary" type="checkbox" />
                            Publié
                        </label>
                    </div>
                </div>

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
