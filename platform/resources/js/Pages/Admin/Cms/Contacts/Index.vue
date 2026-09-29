<script setup>
import CmsLayout from '@/Layouts/CmsLayout.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    items: { type: Array, default: () => [] },
    pagination: { type: Object, default: () => ({ currentPage: 1, lastPage: 1, perPage: 50, total: 0 }) },
    filters: { type: Object, default: () => ({}) },
    statusOptions: { type: Object, default: () => ({}) },
    recipients: { type: Array, default: () => [] },
    fallbackRecipient: { type: String, default: '' },
    newCount: { type: Number, default: 0 },
});

const MESSAGE_PREVIEW_LENGTH = 180;

const page = usePage();
const toast = ref(null);
let toastTimer = null;
const search = ref(props.filters?.q ?? '');
const status = ref(props.filters?.status ?? '');
const showArchived = ref(Boolean(props.filters?.archived));
const perPage = ref(Number(props.filters?.per_page ?? 50));
const archiveDate = ref('');
const archiveForm = useForm({ before_date: '', ids: [] });
const purgeForm = useForm({});
const recipientEmails = ref([...(props.recipients ?? [])]);
const newEmailInput = ref('');
const newEmailError = ref('');
const latestCount = ref(props.newCount ?? 0);
const expandedIds = ref(new Set());
const viewingMessage = ref(null);
let timer = null;
let pollTimer = null;

function showToast(message) {
    if (!message) return;
    toast.value = message;
    if (toastTimer) window.clearTimeout(toastTimer);
    toastTimer = window.setTimeout(() => {
        toast.value = null;
    }, 3500);
}

watch(
    () => page.props.flash?.success,
    (message) => {
        if (message) showToast(message);
    },
    { immediate: true },
);

const editId = ref(null);
const form = useForm({
    status: 'new',
    admin_note: '',
});
const recipientsForm = useForm({
    recipients: recipientEmails.value,
});

watch(status, applyFilters);
watch(showArchived, applyFilters);
watch(perPage, applyFilters);

watch(
    () => props.recipients,
    (value) => {
        recipientEmails.value = [...(value ?? [])];
        recipientsForm.recipients = recipientEmails.value;
    },
    { deep: true },
);

watch(
    () => props.newCount,
    (value) => {
        latestCount.value = Number(value ?? 0);
    },
);

function onSearch() {
    clearTimeout(timer);
    timer = setTimeout(applyFilters, 250);
}

function applyFilters(pageNumber = 1) {
    router.get(route('admin.cms.contacts'), {
        q: search.value || undefined,
        status: status.value || undefined,
        archived: showArchived.value ? 1 : undefined,
        per_page: perPage.value,
        page: pageNumber > 1 ? pageNumber : undefined,
    }, { preserveState: true, preserveScroll: true, replace: true });
}

function goToPage(pageNumber) {
    applyFilters(pageNumber);
}

function archiveBeforeDate() {
    if (!archiveDate.value) return;
    archiveForm.before_date = archiveDate.value;
    archiveForm.post(route('admin.cms.contacts.archive'), { preserveScroll: true });
}

function purgeArchived() {
    if (!window.confirm('Supprimer définitivement tous les messages archivés ?')) return;
    purgeForm.delete(route('admin.cms.contacts.purge'), { preserveScroll: true });
}

function openEdit(item) {
    editId.value = item.id;
    form.status = item.status === 'new' ? 'in_progress' : item.status;
    form.admin_note = item.adminNote || '';
}

function treatButtonLabel(status) {
    if (status === 'new') return 'Prendre en charge';
    if (status === 'in_progress') return 'Finaliser';
    return 'Modifier';
}

function statusBadgeClass(status) {
    if (status === 'new') return 'bg-primary/10 text-primary';
    if (status === 'in_progress') return 'bg-tertiary/15 text-tertiary';
    return 'bg-secondary/15 text-secondary';
}

function saveHint(item) {
    if (item.status === 'new') {
        return 'Le message passera en « En cours » (ou directement « Traité » si la demande est close).';
    }
    if (item.status === 'in_progress') {
        return 'Clôturez la demande en passant le statut à « Traité ».';
    }
    return 'Rouvrez le dossier en « En cours » si un suivi complémentaire est nécessaire.';
}

function save(item) {
    form.put(route('admin.cms.contacts.update', item.id), {
        preserveScroll: true,
        onSuccess: () => {
            editId.value = null;
        },
    });
}

function isValidEmail(value) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(value).trim());
}

function persistRecipients() {
    recipientsForm.recipients = [...recipientEmails.value];
    recipientsForm.put(route('admin.cms.contacts.settings.recipients'), {
        preserveScroll: true,
    });
}

function addRecipientEmail() {
    newEmailError.value = '';
    const email = newEmailInput.value.trim().toLowerCase();
    if (!email) {
        newEmailError.value = 'Saisissez une adresse email.';
        return;
    }
    if (!isValidEmail(email)) {
        newEmailError.value = 'Adresse email invalide.';
        return;
    }
    if (recipientEmails.value.includes(email)) {
        newEmailError.value = 'Cette adresse est déjà dans la liste.';
        return;
    }
    recipientEmails.value.push(email);
    newEmailInput.value = '';
    persistRecipients();
}

function removeRecipientEmail(email) {
    recipientEmails.value = recipientEmails.value.filter((item) => item !== email);
    persistRecipients();
}

function messagePreview(text) {
    const value = String(text ?? '');
    if (value.length <= MESSAGE_PREVIEW_LENGTH) return value;
    return value.slice(0, MESSAGE_PREVIEW_LENGTH).trimEnd() + '…';
}

function isLongMessage(text) {
    return String(text ?? '').length > MESSAGE_PREVIEW_LENGTH;
}

function isExpanded(id) {
    return expandedIds.value.has(id);
}

function toggleExpand(id) {
    const next = new Set(expandedIds.value);
    if (next.has(id)) {
        next.delete(id);
    } else {
        next.add(id);
    }
    expandedIds.value = next;
}

function openMessageModal(item) {
    viewingMessage.value = item;
}

function closeMessageModal() {
    viewingMessage.value = null;
}

function playNotificationSound() {
    const AudioContext = window.AudioContext || window.webkitAudioContext;
    if (!AudioContext) return;
    const ctx = new AudioContext();
    const oscillator = ctx.createOscillator();
    const gain = ctx.createGain();
    oscillator.type = 'sine';
    oscillator.frequency.setValueAtTime(880, ctx.currentTime);
    gain.gain.setValueAtTime(0.0001, ctx.currentTime);
    gain.gain.exponentialRampToValueAtTime(0.2, ctx.currentTime + 0.02);
    gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.25);
    oscillator.connect(gain);
    gain.connect(ctx.destination);
    oscillator.start();
    oscillator.stop(ctx.currentTime + 0.26);
}

function pollUnread() {
    window.fetch(route('admin.cms.contacts.stats.unread'), { headers: { Accept: 'application/json' } })
        .then((response) => response.json())
        .then((payload) => {
            const count = Number(payload?.newCount ?? 0);
            if (count > latestCount.value) {
                showToast(`${count - latestCount.value} nouveau(x) message(s) contact reçu(s).`);
                playNotificationSound();
                router.reload({ only: ['items', 'newCount', 'recipients', 'pagination', 'app'] });
            }
            latestCount.value = count;
        })
        .catch(() => {});
}

onMounted(() => {
    pollTimer = window.setInterval(pollUnread, 15000);
});

onBeforeUnmount(() => {
    if (pollTimer) window.clearInterval(pollTimer);
    if (toastTimer) window.clearTimeout(toastTimer);
});
</script>

<template>
    <Head title="Messages contact" />
    <CmsLayout active-nav="contacts" title="Messages contact" subtitle="Demandes reçues depuis le formulaire public">
        <section class="assure-card p-4 space-y-4">
            <div class="flex items-center justify-between gap-3 flex-wrap">
                <div>
                    <p class="text-xs uppercase tracking-[0.18em] text-primary font-bold">Destinataires notification email</p>
                    <p class="text-sm text-on-surface-variant mt-1">Ces adresses reçoivent un email à chaque nouveau message contact.</p>
                </div>
                <span class="inline-flex items-center gap-1 rounded-full bg-primary/10 text-primary text-xs font-bold px-2.5 py-1">
                    <span class="material-symbols-outlined text-[14px]">mark_email_unread</span>
                    {{ newCount }} nouveau(x)
                </span>
            </div>

            <div v-if="recipientEmails.length" class="flex flex-wrap gap-2">
                <span
                    v-for="email in recipientEmails"
                    :key="email"
                    class="inline-flex items-center gap-1.5 rounded-full border border-outline-variant bg-surface-container-low px-3 py-1.5 text-sm"
                >
                    <span class="material-symbols-outlined text-[16px] text-primary">mail</span>
                    <span class="font-medium">{{ email }}</span>
                    <button
                        class="ml-0.5 rounded-full p-0.5 text-on-surface-variant hover:bg-error-container hover:text-error transition-colors"
                        type="button"
                        title="Retirer"
                        @click="removeRecipientEmail(email)"
                    >
                        <span class="material-symbols-outlined text-[16px]">close</span>
                    </button>
                </span>
            </div>
            <p v-else class="text-sm text-on-surface-variant italic">Aucun destinataire configuré pour le moment.</p>

            <div class="flex flex-col sm:flex-row gap-2">
                <input
                    v-model="newEmailInput"
                    class="flex-1 px-3 py-2 border border-outline-variant rounded-lg text-sm"
                    placeholder="Ajouter une adresse email…"
                    type="email"
                    @keydown.enter.prevent="addRecipientEmail"
                />
                <button
                    class="px-4 py-2 bg-primary text-on-primary rounded-lg text-sm font-bold disabled:opacity-60 shrink-0"
                    :disabled="recipientsForm.processing"
                    type="button"
                    @click="addRecipientEmail"
                >
                    Ajouter
                </button>
            </div>
            <p v-if="newEmailError" class="text-xs text-error font-medium">{{ newEmailError }}</p>

            <p v-if="fallbackRecipient" class="text-xs text-on-surface-variant border-t border-outline-variant pt-3">
                Repli automatique (.env) : <span class="font-semibold">{{ fallbackRecipient }}</span> — utilisé si aucun destinataire n'est configuré ci-dessus.
            </p>
        </section>

        <div class="flex flex-wrap items-center gap-2">
            <input v-model="search" class="px-3 py-2 border border-outline-variant rounded-lg text-sm w-64" placeholder="Rechercher…" @input="onSearch" />
            <select v-model="status" class="px-3 py-2 border border-outline-variant rounded-lg text-sm">
                <option value="">Tous statuts</option>
                <option v-for="(label, key) in statusOptions" :key="key" :value="key">{{ label }}</option>
            </select>
            <label class="inline-flex items-center gap-2 text-sm text-on-surface-variant px-2">
                <input v-model="showArchived" class="rounded border-outline-variant text-primary" type="checkbox" />
                Archivés
            </label>
            <select v-model.number="perPage" class="px-3 py-2 border border-outline-variant rounded-lg text-sm">
                <option :value="25">25 / page</option>
                <option :value="50">50 / page</option>
                <option :value="100">100 / page</option>
            </select>
        </div>

        <section v-if="!showArchived" class="assure-card p-4 flex flex-wrap items-end gap-3">
            <div>
                <label class="text-xs font-bold uppercase text-on-surface-variant block mb-1">Archiver avant le</label>
                <input v-model="archiveDate" class="px-3 py-2 border border-outline-variant rounded-lg text-sm" type="date" />
            </div>
            <button class="px-4 py-2 border border-outline-variant rounded-lg text-sm font-bold" :disabled="archiveForm.processing || !archiveDate" type="button" @click="archiveBeforeDate">
                Archiver par date
            </button>
            <button class="px-4 py-2 border border-error/40 text-error rounded-lg text-sm font-bold ml-auto" :disabled="purgeForm.processing" type="button" @click="purgeArchived">
                Vider les archivés
            </button>
        </section>

        <section class="assure-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-surface-container-low border-b border-outline-variant text-on-surface-variant">
                        <tr>
                            <th class="px-4 py-3 text-left w-[240px]">Demande</th>
                            <th class="px-4 py-3 text-left">Message</th>
                            <th class="px-4 py-3 text-left w-[140px]">Statut</th>
                            <th class="px-4 py-3 text-left w-[220px]">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in items" :key="item.id" class="border-b border-outline-variant align-top">
                            <td class="px-4 py-3">
                                <p class="font-bold">{{ item.fullName }}</p>
                                <p class="text-xs text-on-surface-variant mt-0.5">{{ item.subject }}</p>
                                <p class="text-xs text-on-surface-variant">{{ item.email || '—' }} · {{ item.phone || '—' }}</p>
                                <p class="text-[11px] text-on-surface-variant mt-1">{{ item.createdAt }}</p>
                            </td>
                            <td class="px-4 py-3 max-w-md">
                                <div class="rounded-lg bg-surface-container-low border border-outline-variant/60 p-3">
                                    <p
                                        class="whitespace-pre-wrap break-words text-on-surface leading-relaxed"
                                        :class="!isExpanded(item.id) && isLongMessage(item.message) ? 'line-clamp-4' : ''"
                                    >
                                        {{ isExpanded(item.id) || !isLongMessage(item.message) ? item.message : messagePreview(item.message) }}
                                    </p>
                                    <div v-if="isLongMessage(item.message)" class="flex flex-wrap gap-2 mt-2">
                                        <button
                                            class="text-xs font-bold text-primary hover:underline"
                                            type="button"
                                            @click="toggleExpand(item.id)"
                                        >
                                            {{ isExpanded(item.id) ? 'Réduire' : 'Afficher plus' }}
                                        </button>
                                        <button
                                            class="text-xs font-bold text-on-surface-variant hover:underline"
                                            type="button"
                                            @click="openMessageModal(item)"
                                        >
                                            Voir en plein écran
                                        </button>
                                    </div>
                                </div>
                                <p v-if="item.adminNote" class="mt-2 text-xs text-primary">Note : {{ item.adminNote }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-xs px-2 py-1 rounded-full font-bold" :class="statusBadgeClass(item.status)">{{ item.statusLabel }}</span>
                                <p v-if="item.handler" class="text-[11px] text-on-surface-variant mt-2">Par {{ item.handler }}</p>
                                <p v-if="item.handledAt" class="text-[11px] text-on-surface-variant">Traité le {{ item.handledAt }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <div v-if="editId !== item.id">
                                    <button class="px-3 py-1.5 border border-outline-variant rounded-lg text-xs font-bold" type="button" @click="openEdit(item)">
                                        {{ treatButtonLabel(item.status) }}
                                    </button>
                                </div>
                                <div v-else class="space-y-2">
                                    <select v-model="form.status" class="w-full px-2 py-2 border border-outline-variant rounded-lg text-xs">
                                        <option v-for="(label, key) in item.selectableStatuses" :key="key" :value="key">{{ label }}</option>
                                    </select>
                                    <p class="text-[11px] text-on-surface-variant leading-snug">{{ saveHint(item) }}</p>
                                    <textarea v-model="form.admin_note" rows="3" class="w-full px-2 py-2 border border-outline-variant rounded-lg text-xs" placeholder="Note interne" />
                                    <div class="flex gap-2">
                                        <button class="px-3 py-1.5 bg-primary text-on-primary rounded-lg text-xs font-bold" :disabled="form.processing" type="button" @click="save(item)">Enregistrer</button>
                                        <button class="px-3 py-1.5 border border-outline-variant rounded-lg text-xs font-bold" type="button" @click="editId = null">Annuler</button>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!items.length">
                            <td colspan="4" class="px-4 py-8 text-center text-on-surface-variant">Aucun message contact pour le moment.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-if="pagination.lastPage > 1" class="flex items-center justify-between px-4 py-3 border-t border-outline-variant text-sm">
                <p class="text-on-surface-variant">{{ pagination.total }} message(s) · page {{ pagination.currentPage }} / {{ pagination.lastPage }}</p>
                <div class="flex gap-2">
                    <button class="px-3 py-1.5 border border-outline-variant rounded-lg text-xs font-bold disabled:opacity-50" :disabled="pagination.currentPage <= 1" type="button" @click="goToPage(pagination.currentPage - 1)">Précédent</button>
                    <button class="px-3 py-1.5 border border-outline-variant rounded-lg text-xs font-bold disabled:opacity-50" :disabled="pagination.currentPage >= pagination.lastPage" type="button" @click="goToPage(pagination.currentPage + 1)">Suivant</button>
                </div>
            </div>
        </section>

        <Teleport to="body">
            <div
                v-if="viewingMessage"
                class="fixed inset-0 z-[80] flex items-center justify-center p-4 bg-black/50"
                @click.self="closeMessageModal"
            >
                <div class="w-full max-w-2xl max-h-[85vh] overflow-hidden rounded-2xl bg-surface shadow-xl flex flex-col">
                    <div class="flex items-start justify-between gap-4 border-b border-outline-variant px-5 py-4">
                        <div>
                            <p class="text-xs uppercase tracking-[0.18em] text-primary font-bold">Message complet</p>
                            <p class="font-bold text-lg mt-1">{{ viewingMessage.fullName }}</p>
                            <p class="text-sm text-on-surface-variant">{{ viewingMessage.subject }} · {{ viewingMessage.createdAt }}</p>
                        </div>
                        <button class="rounded-lg p-2 hover:bg-surface-container-low" type="button" @click="closeMessageModal">
                            <span class="material-symbols-outlined">close</span>
                        </button>
                    </div>
                    <div class="overflow-y-auto px-5 py-4 space-y-3">
                        <div class="grid sm:grid-cols-2 gap-2 text-sm">
                            <p><span class="text-on-surface-variant">Email :</span> {{ viewingMessage.email || '—' }}</p>
                            <p><span class="text-on-surface-variant">Téléphone :</span> {{ viewingMessage.phone || '—' }}</p>
                        </div>
                        <div class="rounded-xl bg-surface-container-low border border-outline-variant p-4">
                            <p class="whitespace-pre-wrap break-words leading-relaxed">{{ viewingMessage.message }}</p>
                        </div>
                    </div>
                    <div class="border-t border-outline-variant px-5 py-3 flex justify-end">
                        <button class="px-4 py-2 rounded-lg bg-primary text-on-primary text-sm font-bold" type="button" @click="closeMessageModal">Fermer</button>
                    </div>
                </div>
            </div>
        </Teleport>

        <div v-if="toast" class="fixed bottom-6 right-6 z-[70]">
            <div class="bg-on-background text-white px-5 py-3 rounded-lg shadow-xl flex items-center gap-2 text-sm font-semibold">
                <span class="material-symbols-outlined text-[18px]">check_circle</span>
                {{ toast }}
            </div>
        </div>
    </CmsLayout>
</template>
