<script setup>
import CmsLayout from '@/Layouts/CmsLayout.vue';
import CamaConfirmModal from '@/Components/CamaConfirmModal.vue';
import CamaIconPicker from '@/Components/CamaIconPicker.vue';
import CamaLoadingButton from '@/Components/CamaLoadingButton.vue';
import { useCamaConfirm } from '@/Composables/useCamaConfirm';
import { legalAnchor, legalBlocks } from '@/Utils/legalText';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, nextTick, ref, watch } from 'vue';

const props = defineProps({
    notice: { type: Object, required: true },
    coordinates: { type: Object, default: () => ({ address: '', phones: [], emails: [] }) },
});

const BODY_MAX = 20000;
let uid = 0;

// _uid : clé d'affichage ; _anchorTouched : ancre saisie à la main (sinon elle suit le titre).
function toForm(notice) {
    return {
        title: notice.title ?? '',
        intro: notice.intro ?? '',
        articles: (notice.articles ?? []).map((article) => ({
            _uid: ++uid,
            _anchorTouched: Boolean(article.anchor) && article.anchor !== legalAnchor({ title: article.title }),
            anchor: article.anchor ?? '',
            icon: article.icon ?? 'article',
            title: article.title ?? '',
            body: article.body ?? '',
        })),
    };
}

const form = useForm(toForm(props.notice));
const opened = ref(new Set(form.articles.slice(0, 1).map((a) => a._uid)));
const { confirmState, askConfirm, confirm, cancel } = useCamaConfirm();

// Après publication ou retour au texte d'origine : le formulaire repart des données enregistrées.
watch(() => props.notice, (notice) => {
    const fresh = toForm(notice);
    form.defaults(fresh);
    form.reset();
    opened.value = new Set(fresh.articles.slice(0, 1).map((a) => a._uid));
}, { deep: true });

const lastUpdate = computed(() => {
    if (!props.notice.custom || !props.notice.updatedAt) return null;
    return new Date(props.notice.updatedAt).toLocaleString('fr-FR', { dateStyle: 'long', timeStyle: 'short' });
});

const anchors = computed(() => form.articles.map((article, index) => legalAnchor(article, index)));
const duplicateAnchors = computed(() => anchors.value.filter((anchor, index) => anchors.value.indexOf(anchor) !== index));

function isOpen(article) {
    return opened.value.has(article._uid);
}

function toggle(article) {
    const next = new Set(opened.value);
    next.has(article._uid) ? next.delete(article._uid) : next.add(article._uid);
    opened.value = next;
}

function onTitleInput(article) {
    if (!article._anchorTouched) article.anchor = legalAnchor({ title: article.title });
}

function onAnchorInput(article) {
    article._anchorTouched = article.anchor.trim() !== '';
}

function bodyRows(body) {
    return Math.min(18, Math.max(5, String(body ?? '').split('\n').length + 1));
}

async function addArticle() {
    const article = { _uid: ++uid, _anchorTouched: false, anchor: '', icon: 'article', title: '', body: '' };
    form.articles.push(article);
    opened.value = new Set([...opened.value, article._uid]);
    await nextTick();
    document.getElementById(`legal-article-${article._uid}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    document.getElementById(`legal-title-${article._uid}`)?.focus();
}

function move(index, offset) {
    const target = index + offset;
    if (target < 0 || target >= form.articles.length) return;
    const list = [...form.articles];
    [list[index], list[target]] = [list[target], list[index]];
    form.articles = list;
}

function removeArticle(index) {
    const article = form.articles[index];
    askConfirm({
        title: 'Supprimer cette rubrique ?',
        message: `« ${article.title || 'Rubrique sans titre'} » sera retirée de la page après publication.`,
        confirmLabel: 'Supprimer',
        variant: 'danger',
        onConfirm: () => form.articles.splice(index, 1),
    });
}

function articleError(index, field) {
    return form.errors[`articles.${index}.${field}`];
}

function publish() {
    form
        .transform((data) => ({
            ...data,
            articles: data.articles.map(({ _uid, _anchorTouched, ...article }) => article),
        }))
        .put(route('admin.cms.legal.update'), {
            preserveScroll: true,
            onError: () => {
                // Ouvre les rubriques en erreur pour que le problème soit visible.
                const withErrors = form.articles.filter((_, index) => articleError(index, 'title') || articleError(index, 'body'));
                opened.value = new Set([...opened.value, ...withErrors.map((a) => a._uid)]);
            },
        });
}

function resetToOriginal() {
    askConfirm({
        title: 'Rétablir le texte d\'origine ?',
        message: 'Toutes les rubriques reviennent au texte fourni à la mise en ligne du site. Vos modifications seront perdues.',
        confirmLabel: 'Rétablir',
        variant: 'danger',
        onConfirm: () => router.delete(route('admin.cms.legal.reset'), { preserveScroll: true }),
    });
}
</script>

<template>
    <Head title="Mentions légales" />

    <CmsLayout active-nav="legal" title="Mentions légales" subtitle="Protection des données, droits des assurés et cookies — page /mention_legales du site public">
        <div class="flex flex-wrap items-center gap-3 -mt-2">
            <p class="text-xs text-on-surface-variant flex items-center gap-1.5 mr-auto">
                <span class="material-symbols-outlined text-[16px]">history</span>
                <template v-if="lastUpdate">Dernière publication : {{ lastUpdate }}</template>
                <template v-else>Texte d'origine, jamais modifié</template>
            </p>
            <a class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-outline-variant text-xs font-bold hover:bg-surface-container-low" href="/mention_legales" target="_blank" rel="noopener">
                <span class="material-symbols-outlined text-[16px]">open_in_new</span> Voir la page publique
            </a>
            <button v-if="notice.custom" type="button" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-outline-variant text-xs font-bold text-error hover:bg-error/5" @click="resetToOriginal">
                <span class="material-symbols-outlined text-[16px]">restart_alt</span> Rétablir le texte d'origine
            </button>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_420px] gap-6 items-start pb-24">
            <form class="space-y-5" @submit.prevent="publish">
                <!-- En-tête -->
                <section class="assure-card p-5 space-y-4">
                    <h2 class="text-sm font-bold flex items-center gap-2"><span class="material-symbols-outlined text-primary text-[20px]">title</span> En-tête de la page</h2>
                    <div>
                        <label class="text-[11px] font-bold uppercase text-on-surface-variant block mb-1" for="legal-page-title">Titre</label>
                        <input id="legal-page-title" v-model="form.title" class="w-full px-3 py-2 text-sm border border-outline-variant rounded-lg" maxlength="200" required />
                        <p v-if="form.errors.title" class="text-error text-[11px] mt-1">{{ form.errors.title }}</p>
                    </div>
                    <div>
                        <label class="text-[11px] font-bold uppercase text-on-surface-variant block mb-1" for="legal-intro">Introduction</label>
                        <textarea id="legal-intro" v-model="form.intro" class="w-full px-3 py-2 text-sm border border-outline-variant rounded-lg" rows="3" maxlength="2000" />
                    </div>
                </section>

                <!-- Rubriques -->
                <section class="assure-card p-5 space-y-3">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-sm font-bold flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary text-[20px]">toc</span>
                            Rubriques <span class="text-xs font-normal text-on-surface-variant">({{ form.articles.length }})</span>
                        </h2>
                        <button type="button" class="inline-flex items-center gap-1 text-xs font-bold text-primary hover:underline" @click="addArticle">
                            <span class="material-symbols-outlined text-[18px]">add_circle</span> Ajouter une rubrique
                        </button>
                    </div>
                    <p v-if="form.errors.articles" class="text-error text-[11px]">{{ form.errors.articles }}</p>

                    <article
                        v-for="(article, index) in form.articles"
                        :id="`legal-article-${article._uid}`"
                        :key="article._uid"
                        class="rounded-xl border bg-white transition-colors"
                        :class="articleError(index, 'title') || articleError(index, 'body') ? 'border-error' : 'border-outline-variant'"
                    >
                        <header class="flex items-center gap-2 px-3 py-2.5">
                            <button type="button" class="flex items-center gap-3 flex-1 min-w-0 text-left" :aria-expanded="isOpen(article)" @click="toggle(article)">
                                <span class="w-9 h-9 rounded-lg bg-primary/10 text-primary flex items-center justify-center shrink-0">
                                    <span class="material-symbols-outlined text-[20px]">{{ article.icon || 'article' }}</span>
                                </span>
                                <span class="min-w-0">
                                    <span class="block text-sm font-bold truncate">{{ article.title || 'Nouvelle rubrique' }}</span>
                                    <span class="block text-[11px] text-on-surface-variant font-mono truncate">/mention_legales#{{ anchors[index] }}</span>
                                </span>
                            </button>
                            <div class="flex items-center shrink-0">
                                <button type="button" class="w-8 h-8 rounded-lg hover:bg-surface-container-low disabled:opacity-30" :disabled="index === 0" title="Monter" @click="move(index, -1)">
                                    <span class="material-symbols-outlined text-[18px]">arrow_upward</span>
                                </button>
                                <button type="button" class="w-8 h-8 rounded-lg hover:bg-surface-container-low disabled:opacity-30" :disabled="index === form.articles.length - 1" title="Descendre" @click="move(index, 1)">
                                    <span class="material-symbols-outlined text-[18px]">arrow_downward</span>
                                </button>
                                <button type="button" class="w-8 h-8 rounded-lg hover:bg-error/5 text-error disabled:opacity-30" :disabled="form.articles.length <= 1" title="Supprimer" @click="removeArticle(index)">
                                    <span class="material-symbols-outlined text-[18px]">delete</span>
                                </button>
                                <button type="button" class="w-8 h-8 rounded-lg hover:bg-surface-container-low" :title="isOpen(article) ? 'Replier' : 'Déplier'" @click="toggle(article)">
                                    <span class="material-symbols-outlined text-[20px] transition-transform" :class="isOpen(article) ? 'rotate-180' : ''">expand_more</span>
                                </button>
                            </div>
                        </header>

                        <div v-show="isOpen(article)" class="px-3 pb-4 pt-1 space-y-3 border-t border-outline-variant">
                            <div class="grid grid-cols-1 sm:grid-cols-[1fr_auto] gap-3 pt-3">
                                <div>
                                    <label class="text-[11px] font-bold uppercase text-on-surface-variant block mb-1" :for="`legal-title-${article._uid}`">Titre de la rubrique</label>
                                    <input :id="`legal-title-${article._uid}`" v-model="article.title" class="w-full px-3 py-2 text-sm border border-outline-variant rounded-lg" maxlength="200" placeholder="Ex. : Protection des données personnelles" @input="onTitleInput(article)" />
                                    <p v-if="articleError(index, 'title')" class="text-error text-[11px] mt-1">{{ articleError(index, 'title') }}</p>
                                </div>
                                <CamaIconPicker v-model="article.icon" label="Icône" />
                            </div>

                            <div>
                                <label class="text-[11px] font-bold uppercase text-on-surface-variant block mb-1" :for="`legal-body-${article._uid}`">Texte</label>
                                <textarea
                                    :id="`legal-body-${article._uid}`"
                                    v-model="article.body"
                                    class="w-full px-3 py-2 text-sm border border-outline-variant rounded-lg leading-relaxed"
                                    :rows="bodyRows(article.body)"
                                    :maxlength="BODY_MAX"
                                />
                                <div class="flex flex-wrap justify-between gap-2 text-[11px] text-on-surface-variant mt-1">
                                    <span>Ligne vide = nouveau paragraphe · « - » en début de ligne = liste à puces</span>
                                    <span class="tabular-nums">{{ article.body.length.toLocaleString('fr-FR') }} / {{ BODY_MAX.toLocaleString('fr-FR') }}</span>
                                </div>
                                <p v-if="articleError(index, 'body')" class="text-error text-[11px] mt-1">{{ articleError(index, 'body') }}</p>
                            </div>

                            <details class="text-[11px]">
                                <summary class="cursor-pointer text-on-surface-variant hover:text-primary select-none">Adresse de la rubrique (lien direct)</summary>
                                <div class="mt-2 flex items-center gap-1">
                                    <span class="text-on-surface-variant font-mono">/mention_legales#</span>
                                    <input v-model="article.anchor" class="flex-1 px-2 py-1.5 text-xs font-mono border border-outline-variant rounded-lg" maxlength="80" :placeholder="anchors[index]" @input="onAnchorInput(article)" />
                                </div>
                                <p class="text-on-surface-variant mt-1">Utilisée par les liens du pied de page et du bandeau cookies (« #rgpd », « #cookies »). Évitez de la changer si des liens y pointent.</p>
                                <p v-if="duplicateAnchors.includes(anchors[index])" class="text-tertiary mt-1">Adresse déjà utilisée par une autre rubrique : un suffixe sera ajouté à la publication.</p>
                            </details>
                        </div>
                    </article>

                    <button type="button" class="w-full py-3 rounded-xl border-2 border-dashed border-outline-variant text-xs font-bold text-on-surface-variant hover:border-primary hover:text-primary transition-colors flex items-center justify-center gap-1.5" @click="addArticle">
                        <span class="material-symbols-outlined text-[18px]">add</span> Ajouter une rubrique
                    </button>
                </section>

                <!-- Coordonnées -->
                <section class="assure-card p-5">
                    <h2 class="text-sm font-bold flex items-center gap-2 mb-2"><span class="material-symbols-outlined text-primary text-[20px]">contact_mail</span> Coordonnées</h2>
                    <p class="text-xs text-on-surface-variant mb-3">
                        Affichées en bas de la page, elles sont reprises automatiquement du module « Pied de page » pour rester identiques partout sur le site.
                    </p>
                    <ul class="text-xs space-y-1 mb-3">
                        <li v-if="coordinates.address" class="flex gap-2"><span class="material-symbols-outlined text-[16px] text-on-surface-variant">location_on</span>{{ coordinates.address }}</li>
                        <li v-for="phone in coordinates.phones" :key="phone" class="flex gap-2"><span class="material-symbols-outlined text-[16px] text-on-surface-variant">call</span>{{ phone }}</li>
                        <li v-for="email in coordinates.emails" :key="email" class="flex gap-2"><span class="material-symbols-outlined text-[16px] text-on-surface-variant">mail</span>{{ email }}</li>
                    </ul>
                    <a :href="route('admin.cms.footer')" class="text-xs font-bold text-primary hover:underline inline-flex items-center gap-1">
                        Modifier dans « Pied de page » <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
                    </a>
                </section>
            </form>

            <!-- Aperçu en direct -->
            <aside class="xl:sticky xl:top-24 space-y-2">
                <p class="text-[11px] font-bold uppercase text-on-surface-variant flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-[16px]">visibility</span> Aperçu en direct
                </p>
                <div class="rounded-xl border border-outline-variant bg-surface overflow-hidden shadow-sm max-h-[75vh] overflow-y-auto">
                    <div class="bg-primary-container text-white p-5">
                        <h3 class="font-headline-md text-lg leading-tight">{{ form.title || 'Titre de la page' }}</h3>
                        <p v-if="form.intro" class="text-xs opacity-90 mt-2">{{ form.intro }}</p>
                    </div>
                    <nav class="flex flex-wrap gap-1.5 p-3 border-b border-outline-variant bg-surface-container-low" aria-label="Sommaire">
                        <span v-for="(article, index) in form.articles" :key="article._uid" class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-white border border-outline-variant text-[10px] font-semibold">
                            <span class="material-symbols-outlined text-[14px] text-primary">{{ article.icon || 'article' }}</span>{{ article.title || 'Sans titre' }}
                        </span>
                    </nav>
                    <div class="p-4 space-y-4">
                        <section v-for="article in form.articles" :key="article._uid">
                            <h4 class="text-sm font-bold flex items-center gap-1.5 mb-2">
                                <span class="material-symbols-outlined text-primary text-[18px]">{{ article.icon || 'article' }}</span>{{ article.title || 'Sans titre' }}
                            </h4>
                            <template v-for="(block, blockIndex) in legalBlocks(article.body)" :key="blockIndex">
                                <p v-if="block.type === 'p'" class="text-xs text-on-surface-variant leading-relaxed mb-2">{{ block.text }}</p>
                                <ul v-else class="list-disc pl-5 text-xs text-on-surface-variant space-y-1 mb-2">
                                    <li v-for="(item, itemIndex) in block.items" :key="itemIndex">{{ item }}</li>
                                </ul>
                            </template>
                            <p v-if="!article.body.trim()" class="text-xs italic text-on-surface-variant">Texte à rédiger…</p>
                        </section>
                    </div>
                </div>
            </aside>
        </div>

        <!-- Barre d'action : visible dès qu'un champ a été modifié. -->
        <div
            v-if="form.isDirty"
            class="fixed bottom-4 left-1/2 -translate-x-1/2 z-40 w-[calc(100%-2rem)] max-w-xl rounded-xl border border-outline-variant bg-white shadow-xl px-4 py-3 flex flex-wrap items-center gap-3"
            role="status"
        >
            <span class="material-symbols-outlined text-tertiary">edit</span>
            <p class="flex-1 text-xs text-on-surface"><strong>Modifications non publiées.</strong> <span class="text-on-surface-variant">La page publique n'a pas encore changé.</span></p>
            <button class="px-3 py-2 rounded-lg text-xs font-bold border border-outline-variant" type="button" :disabled="form.processing" @click="form.reset()">Annuler</button>
            <CamaLoadingButton :loading="form.processing" loading-text="Publication…" @click="publish">Publier</CamaLoadingButton>
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
