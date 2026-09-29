<script setup>
import CmsLayout from '@/Layouts/CmsLayout.vue';
import CamaConfirmModal from '@/Components/CamaConfirmModal.vue';
import CamaLoadingButton from '@/Components/CamaLoadingButton.vue';
import { useCamaConfirm } from '@/Composables/useCamaConfirm';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    items: Array,
    filters: Object,
    statusOptions: Object,
});

const page = usePage();
const toast = computed(() => page.props.flash?.success ?? null);
const flashError = computed(() => page.props.flash?.error ?? null);

const search = ref(props.filters?.q ?? '');
const activeStatus = ref(props.filters?.status ?? '');
const editorOpen = ref(false);
const editingPage = ref(null);
let searchTimer = null;

const form = useForm({
    title: '',
    slug: '',
    status: 'draft',
});

const { confirmState, askConfirm, confirm, cancel } = useCamaConfirm();

const filteredCountLabel = computed(() => `${props.items?.length ?? 0} page(s)`);

watch(activeStatus, applyFilters);

function applyFilters() {
    router.get(
        route('admin.cms.pages'),
        {
            q: search.value || undefined,
            status: activeStatus.value || undefined,
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

function statusBadgeClass(status) {
    return status === 'published'
        ? 'bg-secondary/15 text-secondary'
        : 'bg-tertiary-container/40 text-tertiary';
}

function statusDotClass(status) {
    return status === 'published' ? 'bg-secondary' : 'bg-tertiary';
}

function openEditor(item = null) {
    editingPage.value = item;
    form.reset();
    form.clearErrors();

    if (item) {
        form.title = item.title;
        form.slug = item.slug;
        form.status = item.status;
    } else {
        form.status = 'draft';
    }

    editorOpen.value = true;
}

function closeEditor() {
    editorOpen.value = false;
    editingPage.value = null;
    form.reset();
    form.clearErrors();
}

function savePage() {
    const options = {
        preserveScroll: true,
        onSuccess: () => closeEditor(),
    };

    if (editingPage.value) {
        form.put(route('admin.cms.pages.update', editingPage.value.id), options);
    } else {
        form.post(route('admin.cms.pages.store'), options);
    }
}

function toggleStatus(item) {
    router.put(route('admin.cms.pages.status', item.id), {}, { preserveScroll: true });
}

function duplicatePage(item) {
    router.post(route('admin.cms.pages.duplicate', item.id), {}, { preserveScroll: true });
}

function deletePage(item) {
    if (!item.canDelete) {
        askConfirm({
            title: 'Suppression impossible',
            message: 'La page d’accueil est nécessaire au site public et ne peut pas être supprimée.',
            variant: 'warning',
            alertOnly: true,
        });
        return;
    }

    askConfirm({
        title: 'Supprimer cette page ?',
        message: `La page « ${item.title} » sera supprimée et retirée automatiquement du menu si elle y figure.`,
        confirmLabel: 'Supprimer',
        variant: 'danger',
        onConfirm: () => router.delete(route('admin.cms.pages.destroy', item.id), { preserveScroll: true }),
    });
}
</script>

<template>
    <Head title="Pages" />

    <CmsLayout active-nav="pages" title="Pages" subtitle="Créez et gérez les pages du site institutionnel">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2">
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-2.5 top-1/2 -translate-y-1/2 text-[18px] text-on-surface-variant">search</span>
                    <input
                        v-model="search"
                        class="pl-9 pr-3 py-2 text-sm border border-outline-variant rounded-lg w-64 max-w-full focus:outline-none focus:border-primary"
                        placeholder="Rechercher une page…"
                        @input="onSearchInput"
                    />
                </div>
                <select v-model="activeStatus" class="text-sm border border-outline-variant rounded-lg px-2 py-2">
                    <option value="">Tous les statuts</option>
                    <option v-for="(label, value) in statusOptions" :key="value" :value="value">{{ label }}</option>
                </select>
            </div>
            <button
                class="bg-primary text-on-primary px-4 py-2.5 rounded-lg text-sm font-bold flex items-center gap-2 self-start"
                type="button"
                @click="openEditor()"
            >
                <span class="material-symbols-outlined text-[18px]">add</span>
                Ajouter une page
            </button>
        </div>

        <div class="assure-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-[11px] uppercase tracking-wider text-on-surface-variant border-b border-outline-variant bg-surface-container-low">
                            <th class="px-4 py-3 font-bold">Titre</th>
                            <th class="px-4 py-3 font-bold hidden md:table-cell">Adresse (slug)</th>
                            <th class="px-4 py-3 font-bold">Statut</th>
                            <th class="px-4 py-3 font-bold hidden lg:table-cell">Modifié le</th>
                            <th class="px-4 py-3 font-bold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in items" :key="item.id" class="border-b border-outline-variant last:border-0 hover:bg-surface-container-low">
                            <td class="px-4 py-3">
                                <a :href="item.builderUrl" class="font-bold text-on-surface hover:text-primary">{{ item.title }}</a>
                                <p class="text-[11px] text-on-surface-variant mt-0.5">
                                    {{ item.sectionCount }} section{{ item.sectionCount > 1 ? 's' : '' }}
                                </p>
                            </td>
                            <td class="px-4 py-3 hidden md:table-cell text-on-surface-variant">/{{ item.slug }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center gap-1 text-[11px] font-bold px-2 py-0.5 rounded-full" :class="statusBadgeClass(item.status)">
                                    <span class="w-1.5 h-1.5 rounded-full" :class="statusDotClass(item.status)" />
                                    {{ item.statusLabel }}
                                </span>
                            </td>
                            <td class="px-4 py-3 hidden lg:table-cell text-on-surface-variant text-[12px]">{{ item.updatedAt || '—' }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <a
                                        :href="item.builderUrl"
                                        class="w-8 h-8 flex items-center justify-center rounded-lg text-on-surface-variant hover:bg-primary/10 hover:text-primary"
                                        title="Modifier dans l'éditeur"
                                    >
                                        <span class="material-symbols-outlined text-[18px]">edit</span>
                                    </a>
                                    <button
                                        class="w-8 h-8 flex items-center justify-center rounded-lg text-on-surface-variant hover:bg-surface-container"
                                        type="button"
                                        title="Renommer / slug"
                                        @click="openEditor(item)"
                                    >
                                        <span class="material-symbols-outlined text-[18px]">drive_file_rename_outline</span>
                                    </button>
                                    <button
                                        class="w-8 h-8 flex items-center justify-center rounded-lg text-on-surface-variant hover:bg-surface-container"
                                        type="button"
                                        :title="item.status === 'published' ? 'Repasser en brouillon' : 'Publier'"
                                        @click="toggleStatus(item)"
                                    >
                                        <span class="material-symbols-outlined text-[18px]">{{ item.status === 'published' ? 'toggle_on' : 'toggle_off' }}</span>
                                    </button>
                                    <button
                                        class="w-8 h-8 flex items-center justify-center rounded-lg text-on-surface-variant hover:bg-surface-container"
                                        type="button"
                                        title="Dupliquer"
                                        @click="duplicatePage(item)"
                                    >
                                        <span class="material-symbols-outlined text-[18px]">content_copy</span>
                                    </button>
                                    <button
                                        class="w-8 h-8 flex items-center justify-center rounded-lg text-on-surface-variant hover:bg-error/10 hover:text-error"
                                        type="button"
                                        title="Supprimer"
                                        @click="deletePage(item)"
                                    >
                                        <span class="material-symbols-outlined text-[18px]">delete</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!items.length">
                            <td colspan="5" class="px-4 py-12 text-center text-on-surface-variant text-sm">Aucune page.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <p class="text-[11px] text-on-surface-variant">{{ filteredCountLabel }}</p>
    </CmsLayout>

    <div
        v-if="editorOpen"
        class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4"
        @click.self="closeEditor"
    >
        <div class="bg-white rounded-xl w-full max-w-md">
            <div class="flex items-center justify-between p-5 border-b border-outline-variant">
                <h3 class="font-semibold text-sm">{{ editingPage ? 'Modifier la page' : 'Nouvelle page' }}</h3>
                <button type="button" :disabled="form.processing" @click="closeEditor">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>

            <form class="p-5 space-y-4" @submit.prevent="savePage">
                <div>
                    <label class="text-[11px] font-bold uppercase text-on-surface-variant block mb-1">Titre</label>
                    <input v-model="form.title" class="w-full px-3 py-2 text-xs border border-outline-variant rounded-lg" required />
                    <p v-if="form.errors.title" class="text-error text-[10px] mt-1">{{ form.errors.title }}</p>
                </div>

                <div>
                    <label class="text-[11px] font-bold uppercase text-on-surface-variant block mb-1">Adresse (slug)</label>
                    <input v-model="form.slug" class="w-full px-3 py-2 text-xs border border-outline-variant rounded-lg" placeholder="laissé vide = généré automatiquement" />
                    <p class="text-[10px] text-on-surface-variant mt-1">Exemple : <span class="font-semibold">services</span> donnera l’adresse publique <span class="font-semibold">/services</span>.</p>
                    <p v-if="form.errors.slug" class="text-error text-[10px] mt-1">{{ form.errors.slug }}</p>
                </div>

                <div>
                    <label class="text-[11px] font-bold uppercase text-on-surface-variant block mb-1">Statut</label>
                    <select v-model="form.status" class="w-full px-3 py-2 text-xs border border-outline-variant rounded-lg">
                        <option v-for="(label, value) in statusOptions" :key="value" :value="value">{{ label }}</option>
                    </select>
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

    <div v-if="toast || flashError" class="fixed bottom-6 right-6 z-[70]">
        <div
            class="text-white px-5 py-3 rounded-lg shadow-xl flex items-center gap-2 text-xs font-semibold"
            :class="flashError ? 'bg-error' : 'bg-on-background'"
        >
            <span class="material-symbols-outlined text-[18px]">{{ flashError ? 'error' : 'check_circle' }}</span>
            {{ flashError || toast }}
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
