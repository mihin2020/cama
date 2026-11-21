<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import CamaConfirmModal from '@/Components/CamaConfirmModal.vue';
import { useCamaConfirm } from '@/Composables/useCamaConfirm';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    cibles: { type: Array, default: () => [] },
    types: { type: Array, default: () => [] },
    choiceTypes: { type: Array, default: () => [] },
    maxOptions: { type: Number, default: 30 },
});

const { confirmState, askConfirm, confirm, cancel } = useCamaConfirm();

const activeCible = ref(props.cibles[0]?.cible ?? 'assure');
const current = computed(() => props.cibles.find((c) => c.cible === activeCible.value) ?? props.cibles[0]);
const typeLabel = (value) => props.types.find((t) => t.value === value)?.label ?? value;

// Un seul message, en bas à droite, comme les autres écrans du back-office.
const toast = ref(null);
let toastTimer = null;
function showToast(message, icon = 'check_circle') {
    if (!message) return;
    toast.value = { message, icon };
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { toast.value = null; }, 3500);
}
const onSuccess = (page) => showToast(page.props.flash?.success);
const onError = (errors) => showToast(Object.values(errors)[0], 'error');

// ---------------------------------------------------------------- sections
const sectionOpen = ref(false);
const editingSection = ref(null);
const sectionForm = useForm({ titre: '', description: '', visible: true });

function openSection(section = null) {
    editingSection.value = section;
    sectionForm.titre = section?.titre ?? '';
    sectionForm.description = section?.description ?? '';
    sectionForm.visible = section?.visible ?? true;
    sectionForm.clearErrors();
    sectionOpen.value = true;
}

function saveSection() {
    const options = { preserveScroll: true, onSuccess: (page) => { sectionOpen.value = false; onSuccess(page); } };
    if (editingSection.value) {
        sectionForm.put(route('admin.fif.sections.update', editingSection.value.id), options);
    } else {
        sectionForm.post(route('admin.fif.sections.store', activeCible.value), options);
    }
}

function toggleSection(section) {
    router.put(route('admin.fif.sections.update', section.id), {
        titre: section.titre,
        description: section.description,
        visible: !section.visible,
    }, { preserveScroll: true, onSuccess, onError });
}

function moveSection(section, direction) {
    router.post(route('admin.fif.sections.move', section.id), { direction }, { preserveScroll: true, onError });
}

function askDeleteSection(section) {
    askConfirm({
        title: 'Supprimer cette section ?',
        message: `La section « ${section.titre} » et ses ${section.fields.length} champ(s) ne seront plus proposés ni imprimés. Les données déjà saisies sont conservées.`,
        confirmLabel: 'Supprimer',
        variant: 'danger',
        onConfirm: () => router.delete(route('admin.fif.sections.destroy', section.id), { preserveScroll: true, onSuccess, onError }),
    });
}

// ---------------------------------------------------------------- champs
const fieldOpen = ref(false);
const editingField = ref(null);
const targetSection = ref(null);
const fieldForm = useForm({
    libelle: '', type: 'text', optionsText: '', aide: '', obligatoire: false, visible: true, imprimer_pdf: true,
});

const isChoice = computed(() => props.choiceTypes.includes(fieldForm.type));

function openField(section, field = null) {
    targetSection.value = section;
    editingField.value = field;
    fieldForm.libelle = field?.libelle ?? '';
    fieldForm.type = field?.type ?? 'text';
    fieldForm.optionsText = (field?.options ?? []).join('\n');
    fieldForm.aide = field?.aide ?? '';
    fieldForm.obligatoire = field?.obligatoire ?? false;
    fieldForm.visible = field?.visible ?? true;
    fieldForm.imprimer_pdf = field?.imprimer_pdf ?? true;
    fieldForm.clearErrors();
    fieldOpen.value = true;
}

function saveField() {
    const options = { preserveScroll: true, onSuccess: (page) => { fieldOpen.value = false; onSuccess(page); } };
    fieldForm.transform((data) => ({
        libelle: data.libelle,
        type: data.type,
        options: props.choiceTypes.includes(data.type) ? data.optionsText.split('\n').map((o) => o.trim()).filter(Boolean) : [],
        aide: data.aide,
        obligatoire: data.obligatoire,
        visible: data.visible,
        imprimer_pdf: data.imprimer_pdf,
    }));

    if (editingField.value) {
        fieldForm.put(route('admin.fif.fields.update', editingField.value.id), options);
    } else {
        fieldForm.post(route('admin.fif.fields.store', targetSection.value.id), options);
    }
}

function toggleField(field) {
    router.put(route('admin.fif.fields.update', field.id), {
        libelle: field.libelle,
        type: field.type,
        options: field.options,
        aide: field.aide,
        obligatoire: field.obligatoire,
        visible: !field.visible,
        imprimer_pdf: field.imprimer_pdf,
    }, { preserveScroll: true, onSuccess, onError });
}

function moveField(field, direction) {
    router.post(route('admin.fif.fields.move', field.id), { direction }, { preserveScroll: true, onError });
}

function askDeleteField(field) {
    askConfirm({
        title: 'Supprimer ce champ ?',
        message: `Le champ « ${field.libelle} » ne sera plus proposé ni imprimé. Les données déjà saisies sont conservées.`,
        confirmLabel: 'Supprimer',
        variant: 'danger',
        onConfirm: () => router.delete(route('admin.fif.fields.destroy', field.id), { preserveScroll: true, onSuccess, onError }),
    });
}
</script>

<template>
    <Head title="Formulaire FIF" />

    <AdminLayout active-nav="fif" title="Formulaire FIF" subtitle="Sections et champs de la fiche d'identification famille">
        <p class="text-xs text-on-surface-variant max-w-3xl mb-4">
            Ajoutez, modifiez, masquez ou supprimez des sections et des champs. Ils apparaissent aussitôt dans les formulaires de l'assuré
            et sur la FIF imprimée. Un champ masqué ou supprimé n'est plus proposé, mais les données déjà saisies sont conservées.
        </p>

        <div class="flex flex-wrap gap-2 mb-5" role="tablist">
            <button
                v-for="cible in cibles"
                :key="cible.cible"
                type="button"
                role="tab"
                class="px-4 py-2 rounded-lg text-xs font-bold border transition-colors"
                :class="activeCible === cible.cible ? 'bg-primary text-on-primary border-primary' : 'bg-white border-outline-variant text-on-surface-variant hover:bg-surface-container-low'"
                @click="activeCible = cible.cible"
            >
                {{ cible.titre }}
                <span class="ml-1 opacity-70">({{ cible.sections.length }})</span>
            </button>
        </div>

        <div class="space-y-4">
            <section
                v-for="(section, sIndex) in current.sections"
                :key="section.id"
                class="assure-card overflow-hidden"
                :class="section.visible ? '' : 'opacity-70'"
            >
                <header class="flex flex-wrap items-center justify-between gap-2 px-5 py-3 bg-surface-container-low border-b border-outline-variant">
                    <div class="min-w-0">
                        <h2 class="font-bold text-sm flex items-center gap-2">
                            {{ section.titre }}
                            <span v-if="!section.visible" class="px-2 py-0.5 rounded-full bg-surface-container text-[10px] font-bold text-on-surface-variant">Masquée</span>
                        </h2>
                        <p v-if="section.description" class="text-[11px] text-on-surface-variant">{{ section.description }}</p>
                    </div>
                    <div class="flex items-center gap-1 text-on-surface-variant">
                        <button type="button" class="p-1 hover:text-primary disabled:opacity-30" title="Monter" :disabled="sIndex === 0" @click="moveSection(section, 'up')">
                            <span class="material-symbols-outlined text-[18px]">arrow_upward</span>
                        </button>
                        <button type="button" class="p-1 hover:text-primary disabled:opacity-30" title="Descendre" :disabled="sIndex === current.sections.length - 1" @click="moveSection(section, 'down')">
                            <span class="material-symbols-outlined text-[18px]">arrow_downward</span>
                        </button>
                        <button type="button" class="p-1 hover:text-primary" :title="section.visible ? 'Masquer la section' : 'Afficher la section'" @click="toggleSection(section)">
                            <span class="material-symbols-outlined text-[18px]">{{ section.visible ? 'visibility' : 'visibility_off' }}</span>
                        </button>
                        <button type="button" class="p-1 hover:text-primary" title="Modifier la section" @click="openSection(section)">
                            <span class="material-symbols-outlined text-[18px]">edit</span>
                        </button>
                        <button type="button" class="p-1 hover:text-error" title="Supprimer la section" @click="askDeleteSection(section)">
                            <span class="material-symbols-outlined text-[18px]">delete</span>
                        </button>
                    </div>
                </header>

                <ul class="divide-y divide-outline-variant">
                    <li
                        v-for="(field, fIndex) in section.fields"
                        :key="field.id"
                        class="flex flex-wrap items-center justify-between gap-2 px-5 py-2.5 text-xs"
                        :class="field.visible ? '' : 'opacity-60'"
                    >
                        <div class="min-w-0">
                            <p class="font-semibold">
                                {{ field.libelle }}
                                <span v-if="field.obligatoire" class="text-error">*</span>
                            </p>
                            <p class="text-[11px] text-on-surface-variant">
                                {{ typeLabel(field.type) }}
                                <span v-if="field.options.length"> — {{ field.options.join(', ') }}</span>
                            </p>
                        </div>
                        <div class="flex items-center gap-1.5 text-on-surface-variant">
                            <span v-if="!field.visible" class="px-2 py-0.5 rounded-full bg-surface-container text-[10px] font-bold">Masqué</span>
                            <span v-if="!field.imprimer_pdf" class="px-2 py-0.5 rounded-full bg-surface-container text-[10px] font-bold" title="Non imprimé sur la FIF">Hors PDF</span>
                            <button type="button" class="p-1 hover:text-primary disabled:opacity-30" title="Monter" :disabled="fIndex === 0" @click="moveField(field, 'up')">
                                <span class="material-symbols-outlined text-[18px]">arrow_upward</span>
                            </button>
                            <button type="button" class="p-1 hover:text-primary disabled:opacity-30" title="Descendre" :disabled="fIndex === section.fields.length - 1" @click="moveField(field, 'down')">
                                <span class="material-symbols-outlined text-[18px]">arrow_downward</span>
                            </button>
                            <button type="button" class="p-1 hover:text-primary" :title="field.visible ? 'Masquer le champ' : 'Afficher le champ'" @click="toggleField(field)">
                                <span class="material-symbols-outlined text-[18px]">{{ field.visible ? 'visibility' : 'visibility_off' }}</span>
                            </button>
                            <button type="button" class="p-1 hover:text-primary" title="Modifier le champ" @click="openField(section, field)">
                                <span class="material-symbols-outlined text-[18px]">edit</span>
                            </button>
                            <button type="button" class="p-1 hover:text-error" title="Supprimer le champ" @click="askDeleteField(field)">
                                <span class="material-symbols-outlined text-[18px]">delete</span>
                            </button>
                        </div>
                    </li>
                    <li v-if="!section.fields.length" class="px-5 py-4 text-xs text-on-surface-variant italic">Aucun champ dans cette section.</li>
                </ul>

                <footer class="px-5 py-2.5 border-t border-outline-variant">
                    <button type="button" class="text-xs font-bold text-primary hover:underline flex items-center gap-1" @click="openField(section)">
                        <span class="material-symbols-outlined text-[16px]">add</span> Ajouter un champ
                    </button>
                </footer>
            </section>

            <p v-if="!current.sections.length" class="assure-card px-5 py-8 text-center text-sm text-on-surface-variant">
                Aucune section personnalisée pour « {{ current.titre }} ». Les champs d'origine du formulaire restent affichés.
            </p>

            <button type="button" class="bg-primary text-on-primary px-4 py-2.5 rounded-lg text-xs font-bold flex items-center gap-1.5 hover:opacity-90" @click="openSection()">
                <span class="material-symbols-outlined text-[16px]">add</span> Ajouter une section
            </button>
        </div>

        <!-- Section -->
        <div v-if="sectionOpen" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="sectionOpen = false">
            <form class="bg-white rounded-xl w-full max-w-lg p-6" @submit.prevent="saveSection">
                <h3 class="font-title-lg text-title-lg mb-4">{{ editingSection ? 'Modifier la section' : 'Nouvelle section' }}</h3>
                <label class="text-[11px] font-bold uppercase text-on-surface-variant block mb-1" for="fif-section-titre">Titre <span class="text-error">*</span></label>
                <input id="fif-section-titre" v-model="sectionForm.titre" class="w-full px-3 py-2 text-xs border border-outline-variant rounded-lg" maxlength="150" placeholder="Ex. Informations médicales" type="text" />
                <p v-if="sectionForm.errors.titre" class="text-[10px] text-error mt-1">{{ sectionForm.errors.titre }}</p>

                <label class="text-[11px] font-bold uppercase text-on-surface-variant block mb-1 mt-3" for="fif-section-desc">Description</label>
                <input id="fif-section-desc" v-model="sectionForm.description" class="w-full px-3 py-2 text-xs border border-outline-variant rounded-lg" maxlength="500" placeholder="Texte d'aide affiché sous le titre" type="text" />

                <label class="mt-3 flex items-center gap-2 text-xs cursor-pointer">
                    <input v-model="sectionForm.visible" class="rounded border-outline-variant text-primary" type="checkbox" /> Section affichée
                </label>

                <div class="flex justify-end gap-2 mt-6">
                    <button class="px-4 py-2 text-xs rounded-lg border border-outline" type="button" @click="sectionOpen = false">Annuler</button>
                    <button class="px-4 py-2 text-xs rounded-lg bg-primary text-on-primary font-bold disabled:opacity-60" type="submit" :disabled="sectionForm.processing">
                        {{ editingSection ? 'Enregistrer' : 'Ajouter' }}
                    </button>
                </div>
            </form>
        </div>

        <!-- Champ -->
        <div v-if="fieldOpen" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="fieldOpen = false">
            <form class="bg-white rounded-xl w-full max-w-lg max-h-[92vh] overflow-y-auto p-6" @submit.prevent="saveField">
                <h3 class="font-title-lg text-title-lg mb-1">{{ editingField ? 'Modifier le champ' : 'Nouveau champ' }}</h3>
                <p class="text-xs text-on-surface-variant mb-4">Section « {{ targetSection?.titre }} »</p>

                <label class="text-[11px] font-bold uppercase text-on-surface-variant block mb-1" for="fif-field-libelle">Libellé <span class="text-error">*</span></label>
                <input id="fif-field-libelle" v-model="fieldForm.libelle" class="w-full px-3 py-2 text-xs border border-outline-variant rounded-lg" maxlength="150" placeholder="Ex. Numéro de permis de conduire" type="text" />
                <p v-if="fieldForm.errors.libelle" class="text-[10px] text-error mt-1">{{ fieldForm.errors.libelle }}</p>

                <label class="text-[11px] font-bold uppercase text-on-surface-variant block mb-1 mt-3" for="fif-field-type">Type de champ</label>
                <select id="fif-field-type" v-model="fieldForm.type" class="w-full px-3 py-2 text-xs border border-outline-variant rounded-lg bg-white">
                    <option v-for="type in types" :key="type.value" :value="type.value">{{ type.label }}</option>
                </select>

                <div v-if="isChoice" class="mt-3">
                    <label class="text-[11px] font-bold uppercase text-on-surface-variant block mb-1" for="fif-field-options">Choix proposés (un par ligne) <span class="text-error">*</span></label>
                    <textarea id="fif-field-options" v-model="fieldForm.optionsText" class="w-full px-3 py-2 text-xs border border-outline-variant rounded-lg" rows="4" :placeholder="`Au moins 2 choix, ${maxOptions} au plus`" />
                    <p v-if="fieldForm.errors.options" class="text-[10px] text-error mt-1">{{ fieldForm.errors.options }}</p>
                </div>

                <label class="text-[11px] font-bold uppercase text-on-surface-variant block mb-1 mt-3" for="fif-field-aide">Aide (facultatif)</label>
                <input id="fif-field-aide" v-model="fieldForm.aide" class="w-full px-3 py-2 text-xs border border-outline-variant rounded-lg" maxlength="300" placeholder="Texte d'aide affiché sous le champ" type="text" />

                <div class="mt-4 space-y-2 text-xs">
                    <label class="flex items-center gap-2 cursor-pointer"><input v-model="fieldForm.obligatoire" class="rounded border-outline-variant text-primary" type="checkbox" /> Champ obligatoire</label>
                    <label class="flex items-center gap-2 cursor-pointer"><input v-model="fieldForm.visible" class="rounded border-outline-variant text-primary" type="checkbox" /> Champ affiché dans le formulaire</label>
                    <label class="flex items-center gap-2 cursor-pointer"><input v-model="fieldForm.imprimer_pdf" class="rounded border-outline-variant text-primary" type="checkbox" /> Imprimé sur la FIF (PDF)</label>
                </div>

                <div class="flex justify-end gap-2 mt-6">
                    <button class="px-4 py-2 text-xs rounded-lg border border-outline" type="button" @click="fieldOpen = false">Annuler</button>
                    <button class="px-4 py-2 text-xs rounded-lg bg-primary text-on-primary font-bold disabled:opacity-60" type="submit" :disabled="fieldForm.processing">
                        {{ editingField ? 'Enregistrer' : 'Ajouter' }}
                    </button>
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
