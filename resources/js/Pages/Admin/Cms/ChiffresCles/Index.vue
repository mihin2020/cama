<script setup>
import CmsLayout from '@/Layouts/CmsLayout.vue';
import CamaConfirmModal from '@/Components/CamaConfirmModal.vue';
import CamaIcon from '@/Components/CamaIcon.vue';
import CamaIconPicker from '@/Components/CamaIconPicker.vue';
import CamaLoadingButton from '@/Components/CamaLoadingButton.vue';
import { useCamaConfirm } from '@/Composables/useCamaConfirm';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    items: Array,
});

const page = usePage();
const editorOpen = ref(false);
const editingId = ref(null);

const form = useForm({
    valeur: '',
    suffixe: '',
    libelle: '',
    icone: '',
    ordre: 1,
});

const { confirmState, askConfirm, confirm, cancel } = useCamaConfirm();

const toast = computed(() => page.props.flash?.success ?? null);

const editorTitle = computed(() => (editingId.value ? 'Modifier le chiffre clé' : 'Chiffre clé'));

function formatDisplayValue(item) {
    const suffix = item.suffixe ?? '';
    const valeur = item.valeur;

    if (suffix === '%' || suffix === '+' || suffix === 'k') {
        return `${valeur}${suffix}`;
    }

    return `${valeur}${suffix}`;
}

function openEditor(item = null) {
    editingId.value = item?.id ?? null;
    form.reset();
    form.clearErrors();

    if (item) {
        form.valeur = item.valeur;
        form.suffixe = item.suffixe ?? '';
        form.libelle = item.libelle;
        form.icone = item.icone ?? '';
        form.ordre = item.ordre;
    } else {
        form.ordre = (props.items?.length ?? 0) + 1;
    }

    editorOpen.value = true;
}

function closeEditor() {
    editorOpen.value = false;
    editingId.value = null;
}

function saveFigure() {
    const options = {
        preserveScroll: true,
        onSuccess: () => closeEditor(),
    };

    if (editingId.value) {
        form.put(route('admin.cms.chiffres_cles.update', editingId.value), options);
    } else {
        form.post(route('admin.cms.chiffres_cles.store'), options);
    }
}

function removeFigure(id) {
    askConfirm({
        title: 'Supprimer ce chiffre ?',
        message: 'Cet indicateur sera retiré de la section « Chiffres clés » de l\'accueil.',
        confirmLabel: 'Supprimer',
        variant: 'danger',
        onConfirm: () => router.delete(route('admin.cms.chiffres_cles.destroy', id), { preserveScroll: true }),
    });
}
</script>

<template>
    <Head title="Chiffres clés" />

    <CmsLayout active-nav="chiffres-cles" title="Chiffres clés" subtitle="Indicateurs affichés sur la page d'accueil">
        <div class="flex justify-between items-center">
            <p class="text-xs text-on-surface-variant">
                Ces chiffres apparaissent dans la section « Chiffres clés » de l'accueil.
            </p>
            <button
                class="bg-primary text-on-primary px-4 py-2 rounded-lg text-xs font-bold flex items-center gap-1.5"
                type="button"
                @click="openEditor()"
            >
                <span class="material-symbols-outlined text-[16px]">add</span>
                Ajouter
            </button>
        </div>

        <div class="assure-card overflow-x-auto mt-4">
            <table class="w-full text-xs">
                <thead>
                    <tr class="border-b border-outline-variant text-left text-[10px] uppercase text-on-surface-variant">
                        <th class="p-3">Valeur</th>
                        <th class="p-3">Suffixe</th>
                        <th class="p-3">Libellé</th>
                        <th class="p-3">Icône</th>
                        <th class="p-3">Ordre</th>
                        <th class="p-3" />
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="item in items"
                        :key="item.id"
                        class="border-b border-outline-variant last:border-0"
                    >
                        <td class="p-3 font-bold text-primary text-base">{{ formatDisplayValue(item) }}</td>
                        <td class="p-3 text-on-surface-variant">{{ item.suffixe || '—' }}</td>
                        <td class="p-3 font-semibold">{{ item.libelle }}</td>
                        <td class="p-3">
                            <CamaIcon :name="item.icone" size="sm" />
                        </td>
                        <td class="p-3 text-on-surface-variant">{{ item.ordre }}</td>
                        <td class="p-3 text-right">
                            <button type="button" title="Modifier" @click="openEditor(item)">
                                <span class="material-symbols-outlined text-[18px]">edit</span>
                            </button>
                            <button type="button" class="ml-2" title="Supprimer" @click="removeFigure(item.id)">
                                <span class="material-symbols-outlined text-[18px] text-error">delete</span>
                            </button>
                        </td>
                    </tr>
                    <tr v-if="!items.length">
                        <td class="p-6 text-center text-on-surface-variant" colspan="6">Aucun chiffre clé. Ajoutez-en un.</td>
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
        <div class="bg-white rounded-xl w-full max-w-md">
            <div class="flex items-center justify-between p-5 border-b border-outline-variant">
                <h3 class="font-semibold text-sm">{{ editorTitle }}</h3>
                <button type="button" :disabled="form.processing" @click="closeEditor">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>

            <form class="p-5 space-y-4" @submit.prevent="saveFigure">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-[11px] font-bold uppercase text-on-surface-variant block mb-1">Valeur</label>
                        <input v-model.number="form.valeur" class="w-full px-3 py-2 text-xs border border-outline-variant rounded-lg" required step="any" type="number" />
                        <p v-if="form.errors.valeur" class="text-error text-[10px] mt-1">{{ form.errors.valeur }}</p>
                    </div>
                    <div>
                        <label class="text-[11px] font-bold uppercase text-on-surface-variant block mb-1">Suffixe</label>
                        <input v-model="form.suffixe" class="w-full px-3 py-2 text-xs border border-outline-variant rounded-lg" placeholder="%, k, +" />
                    </div>
                </div>

                <div>
                    <label class="text-[11px] font-bold uppercase text-on-surface-variant block mb-1">Libellé</label>
                    <input v-model="form.libelle" class="w-full px-3 py-2 text-xs border border-outline-variant rounded-lg" required />
                    <p v-if="form.errors.libelle" class="text-error text-[10px] mt-1">{{ form.errors.libelle }}</p>
                </div>

                <div>
                    <CamaIconPicker v-model="form.icone" label="Icône" />
                    <p v-if="form.errors.icone" class="text-error text-[10px] mt-1">{{ form.errors.icone }}</p>
                </div>

                <div>
                    <label class="text-[11px] font-bold uppercase text-on-surface-variant block mb-1">Ordre</label>
                    <input v-model.number="form.ordre" class="w-full px-3 py-2 text-xs border border-outline-variant rounded-lg" min="1" type="number" />
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
