<script setup>
import CmsLayout from '@/Layouts/CmsLayout.vue';
import CamaSimpleRichText from '@/Components/CamaSimpleRichText.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

const props = defineProps({
    campaign: { type: Object, default: null },
    templates: { type: Array, default: () => [] },
    preselectedTemplateId: { type: Number, default: null },
    segments: { type: Object, default: () => ({}) },
    batches: { type: Array, default: () => [] },
});

const page = usePage();
const toast = computed(() => page.props.flash?.success ?? page.props.flash?.error ?? null);
const testEmail = ref('');
const selectedTemplateId = ref(props.preselectedTemplateId ?? null);

const form = useForm({
    name: props.campaign?.name ?? '',
    subject: props.campaign?.subject ?? '',
    preheader: props.campaign?.preheader ?? '',
    content_html: props.campaign?.htmlBody ?? '',
    segment: props.campaign?.segment ?? '',
    batch_ids: props.campaign?.batchIds ?? [],
    subscriber_ids: props.campaign?.subscriberIds ?? [],
    template_id: props.preselectedTemplateId ?? null,
});

const sendForm = useForm({
    test_email: '',
    max_recipients: 50,
});

const isSent = computed(() => props.campaign?.status === 'sent');
const isEdit = computed(() => Boolean(props.campaign?.id));

onMounted(() => {
    if (props.campaign?.htmlBody) {
        return;
    }
    if (props.preselectedTemplateId) {
        const template = props.templates.find(t => t.id === props.preselectedTemplateId);
        if (template) applyTemplate(template);
        return;
    }
    if (props.templates.length && !form.content_html) {
        applyTemplate(props.templates[0]);
    }
});

function applyTemplate(template) {
    selectedTemplateId.value = template.id;
    form.template_id = template.id;
    form.content_html = template.html ?? '';
    if (!form.name) form.name = template.name;
    if (!form.subject) form.subject = template.name;
}

function startBlank() {
    selectedTemplateId.value = null;
    form.template_id = null;
    form.content_html = '<p>Bonjour {{prenom}},</p><p>Votre message ici.</p>';
}

function save() {
    if (isEdit.value) {
        form.put(route('admin.cms.newsletter.campaigns.update', props.campaign.id), { preserveScroll: true });
    } else {
        form.post(route('admin.cms.newsletter.campaigns.store'));
    }
}

function toggleBatch(id) {
    const index = form.batch_ids.indexOf(id);
    if (index >= 0) form.batch_ids.splice(index, 1);
    else form.batch_ids.push(id);
}

function sendTest() {
    sendForm.test_email = testEmail.value;
    sendForm.post(route('admin.cms.newsletter.campaigns.send', props.campaign.id), { preserveScroll: true });
}

function sendCampaign() {
    if (!window.confirm('Envoyer cette campagne aux abonnés ciblés ?')) return;
    sendForm.test_email = '';
    sendForm.post(route('admin.cms.newsletter.campaigns.send', props.campaign.id));
}
</script>

<template>
    <Head :title="isEdit ? 'Modifier campagne' : 'Nouvelle campagne'" />
    <CmsLayout active-nav="newsletter" :title="isEdit ? 'Modifier la campagne' : 'Nouvelle campagne'" subtitle="Rédigez votre message et envoyez">
        <div v-if="toast" class="rounded-xl border border-secondary/30 bg-secondary/10 px-4 py-3 text-sm font-semibold text-secondary mb-4">{{ toast }}</div>

        <div class="flex flex-wrap gap-2 mb-4">
            <Link :href="route('admin.cms.newsletter', { tab: 'campaigns' })" class="px-3 py-1.5 border border-outline-variant rounded-lg text-xs font-bold">← Retour newsletter</Link>
            <span v-if="isSent" class="px-3 py-1.5 rounded-lg bg-secondary/10 text-secondary text-xs font-bold">Campagne envoyée — lecture seule</span>
        </div>

        <!-- Modèles de départ (optionnels) -->
        <section v-if="!isSent && templates.length" class="assure-card p-4 mb-4">
            <p class="text-xs uppercase tracking-[0.18em] text-primary font-bold mb-1">Partir d'un modèle (optionnel)</p>
            <p class="text-sm text-on-surface-variant mb-3">Choisissez un modèle pour remplir le message, ou partez d'une page vierge.</p>
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="template in templates"
                    :key="template.id"
                    type="button"
                    class="px-3 py-2 rounded-lg border text-xs font-bold transition-all"
                    :class="selectedTemplateId === template.id ? 'border-primary bg-primary/10 text-primary' : 'border-outline-variant hover:border-primary'"
                    @click="applyTemplate(template)"
                >
                    {{ template.name }}
                </button>
                <button type="button" class="px-3 py-2 rounded-lg border border-dashed border-outline-variant text-xs font-bold hover:border-primary" @click="startBlank">
                    Page vierge
                </button>
            </div>
        </section>

        <!-- Paramètres -->
        <section class="assure-card p-4 space-y-3 mb-4">
            <p class="text-xs uppercase tracking-[0.18em] text-primary font-bold">Paramètres</p>
            <div class="grid md:grid-cols-2 gap-3">
                <div>
                    <label class="text-xs text-on-surface-variant font-semibold">Nom interne</label>
                    <input v-model="form.name" class="w-full mt-1 px-3 py-2 border border-outline-variant rounded-lg text-sm" placeholder="Ex. Newsletter juillet" :disabled="isSent" />
                </div>
                <div>
                    <label class="text-xs text-on-surface-variant font-semibold">Objet de l'email</label>
                    <input v-model="form.subject" class="w-full mt-1 px-3 py-2 border border-outline-variant rounded-lg text-sm" placeholder="Objet (variables : {{prenom}} {{nom}})" :disabled="isSent" />
                </div>
            </div>
            <div>
                <label class="text-xs text-on-surface-variant font-semibold">Pré-en-tête <span class="font-normal">(aperçu dans la boîte mail)</span></label>
                <input v-model="form.preheader" class="w-full mt-1 px-3 py-2 border border-outline-variant rounded-lg text-sm" placeholder="Court résumé affiché après l'objet" :disabled="isSent" />
            </div>
        </section>

        <!-- Éditeur simple -->
        <section class="assure-card p-4 mb-4">
            <p class="text-xs uppercase tracking-[0.18em] text-primary font-bold mb-1">Message</p>
            <p class="text-[11px] text-on-surface-variant mb-3">
                Écrivez votre texte, mettez-le en gras/italique, ajoutez un lien ou une image.
                Variables disponibles : <code v-pre>{{prenom}}</code>, <code v-pre>{{nom}}</code>, <code v-pre>{{email}}</code>.
            </p>
            <CamaSimpleRichText
                v-model="form.content_html"
                :allow-image="true"
                min-height="260px"
                placeholder="Bonjour {{prenom}}, écrivez votre message ici…"
            />
        </section>

        <!-- Destinataires -->
        <section v-if="!isSent" class="assure-card p-4 space-y-3 mb-4">
            <p class="text-xs uppercase tracking-[0.18em] text-primary font-bold">Destinataires</p>
            <div class="grid md:grid-cols-2 gap-3">
                <div>
                    <label class="text-xs text-on-surface-variant font-semibold">Segment</label>
                    <select v-model="form.segment" class="w-full mt-1 px-3 py-2 border border-outline-variant rounded-lg text-sm">
                        <option value="">Tous les abonnés actifs</option>
                        <option v-for="(label, key) in segments" :key="key" :value="key">{{ label }}</option>
                    </select>
                </div>
                <div v-if="batches.length">
                    <label class="text-xs text-on-surface-variant font-semibold">Lots ciblés</label>
                    <div class="flex flex-wrap gap-1.5 mt-1">
                        <button
                            v-for="batch in batches"
                            :key="batch.id"
                            type="button"
                            class="px-2 py-1 rounded-full text-[10px] font-bold border"
                            :class="form.batch_ids.includes(batch.id) ? 'border-primary bg-primary/10 text-primary' : 'border-outline-variant'"
                            @click="toggleBatch(batch.id)"
                        >
                            {{ batch.name }} ({{ batch.count }})
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <!-- Actions -->
        <div v-if="!isSent" class="flex flex-wrap gap-2 justify-end items-center">
            <button class="px-4 py-2 border border-outline-variant rounded-lg text-sm font-bold" :disabled="form.processing" type="button" @click="save">
                {{ form.processing ? 'Enregistrement…' : (isEdit ? 'Enregistrer' : 'Créer le brouillon') }}
            </button>
            <template v-if="isEdit">
                <input v-model="testEmail" class="px-3 py-2 border border-outline-variant rounded-lg text-sm" placeholder="Email de test" type="email" />
                <button class="px-4 py-2 border border-primary text-primary rounded-lg text-sm font-bold" :disabled="sendForm.processing || !testEmail" type="button" @click="sendTest">Envoyer un test</button>
                <label class="text-xs text-on-surface-variant">Max :</label>
                <input v-model.number="sendForm.max_recipients" type="number" min="1" max="2000" class="w-20 px-2 py-2 border border-outline-variant rounded-lg text-sm" title="Nombre max de destinataires" />
                <button class="px-4 py-2 bg-primary text-on-primary rounded-lg text-sm font-bold" :disabled="sendForm.processing" type="button" @click="sendCampaign">Envoyer la campagne</button>
            </template>
        </div>
        <p v-if="!isEdit" class="text-[11px] text-on-surface-variant text-right mt-2">Enregistrez d'abord le brouillon, puis vous pourrez envoyer un test et la campagne.</p>
    </CmsLayout>
</template>
