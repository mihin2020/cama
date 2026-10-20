<script setup>
import CamaConfirmModal from '@/Components/CamaConfirmModal.vue';
import CamaLoadingButton from '@/Components/CamaLoadingButton.vue';
import { useCamaConfirm } from '@/Composables/useCamaConfirm';
import CmsLayout from '@/Layouts/CmsLayout.vue';
import {
    BASE_COLORS,
    DEFAULT_BASE,
    DEFAULT_TOKENS,
    THEME_PRESETS,
    contrastRatio,
    deriveTheme,
    normalizeHex,
    themeCssVariables,
} from '@/Utils/siteTheme';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

const props = defineProps({
    logo: {
        type: Object,
        default: () => ({ url: '/images/logo_cama.png', isDefault: true }),
    },
    theme: {
        type: Object,
        default: () => ({ base: null, custom: false }),
    },
});

const { confirmState, askConfirm, confirm, cancel } = useCamaConfirm();

// ---- Logo ---------------------------------------------------------------------

const logoForm = useForm({ logo: null });
const logoPreview = ref(null);
const logoInput = ref(null);

function onLogoChange(event) {
    const file = event.target.files?.[0] ?? null;
    logoForm.logo = file;
    logoForm.clearErrors();
    if (logoPreview.value) URL.revokeObjectURL(logoPreview.value);
    logoPreview.value = file ? URL.createObjectURL(file) : null;
}

function resetLogoSelection() {
    logoForm.reset();
    if (logoPreview.value) URL.revokeObjectURL(logoPreview.value);
    logoPreview.value = null;
    if (logoInput.value) logoInput.value.value = '';
}

function uploadLogo() {
    logoForm.post(route('admin.cms.identity.logo'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: resetLogoSelection,
    });
}

function restoreDefaultLogo() {
    askConfirm({
        title: 'Rétablir le logo d’origine ?',
        message: 'Le logo envoyé sera supprimé et remplacé par le logo CAMA d’origine sur tout le site.',
        confirmLabel: 'Rétablir',
        variant: 'warning',
        onConfirm: () => router.delete(route('admin.cms.identity.logo.reset'), { preserveScroll: true }),
    });
}

// ---- Couleurs -----------------------------------------------------------------

const savedBase = ref({ ...DEFAULT_BASE, ...(props.theme.base ?? {}) });
const base = reactive({ ...savedBase.value });
// Saisie hexadécimale en cours (validée à la sortie du champ).
const hexDrafts = reactive({ ...base });
const saving = ref(false);

const GROUPS = [
    { title: 'Couleurs de marque', keys: ['primary', 'secondary', 'tertiary'] },
    { title: 'Fonds', keys: ['background', 'card'] },
    { title: 'Textes et bordures', keys: ['text', 'textMuted', 'border'] },
];
const colorDefs = Object.fromEntries(BASE_COLORS.map((color) => [color.key, color]));

const tokens = computed(() => deriveTheme(base));
const previewStyle = computed(() => themeCssVariables(tokens.value));
const dirty = computed(() => BASE_COLORS.some(({ key }) => base[key] !== savedBase.value[key]));
const isDefaultTheme = computed(() => BASE_COLORS.every(({ key }) => base[key] === DEFAULT_BASE[key]));
const activePreset = computed(() => THEME_PRESETS.find((preset) => BASE_COLORS.every(({ key }) => preset.base[key] === base[key]))?.name ?? null);

function setColor(key, value) {
    const hex = normalizeHex(value);
    if (!hex) return;
    base[key] = hex;
    hexDrafts[key] = hex;
}

function commitHexDraft(key) {
    const hex = normalizeHex(hexDrafts[key]);
    if (hex) setColor(key, hex);
    else hexDrafts[key] = base[key];
}

function applyPreset(preset) {
    BASE_COLORS.forEach(({ key }) => setColor(key, preset.base[key]));
}

function cancelChanges() {
    applyPreset({ base: savedBase.value });
}

// Contrôles de lisibilité (WCAG : 4,5 pour du texte courant, 3 pour les gros titres et éléments graphiques).
const checks = computed(() => {
    const t = tokens.value;
    return [
        { label: 'Texte principal sur le fond des pages', fg: t['on-surface'], bg: t.background, min: 4.5 },
        { label: 'Texte principal sur les cartes', fg: t['on-surface'], bg: t['surface-container-lowest'], min: 4.5 },
        { label: 'Texte secondaire sur les cartes', fg: t['on-surface-variant'], bg: t['surface-container-lowest'], min: 4.5 },
        { label: 'Texte des boutons principaux', fg: t['on-primary'], bg: t.primary, min: 4.5 },
        { label: 'Liens (couleur principale) sur les cartes', fg: t.primary, bg: t['surface-container-lowest'], min: 3 },
        { label: 'Couleur secondaire sur les cartes', fg: t.secondary, bg: t['surface-container-lowest'], min: 3 },
        { label: "Couleur d'accent sur les cartes", fg: t.tertiary, bg: t['surface-container-lowest'], min: 3 },
    ].map((check) => {
        const ratio = contrastRatio(check.fg, check.bg);
        return { ...check, ratio, level: ratio >= check.min ? 'ok' : ratio >= 3 && check.min > 3 ? 'warn' : 'bad' };
    });
});
const problems = computed(() => checks.value.filter((check) => check.level !== 'ok'));

// Le <style> injecté par le serveur ne change qu'au rechargement complet : on applique
// tout de suite les couleurs publiées au back-office ouvert.
function applyToCurrentPage(tokenMap) {
    Object.entries(themeCssVariables(tokenMap)).forEach(([name, value]) => document.documentElement.style.setProperty(name, value));
}

function publish() {
    // Revenir exactement aux couleurs d'origine = supprimer le thème personnalisé.
    if (isDefaultTheme.value) {
        saving.value = true;
        router.delete(route('admin.cms.identity.theme.reset'), {
            preserveScroll: true,
            onSuccess: () => {
                savedBase.value = { ...DEFAULT_BASE };
                applyToCurrentPage(DEFAULT_TOKENS);
            },
            onFinish: () => { saving.value = false; },
        });
        return;
    }

    const send = () => {
        saving.value = true;
        router.put(route('admin.cms.identity.theme'), { base: { ...base }, tokens: tokens.value }, {
            preserveScroll: true,
            onSuccess: () => {
                savedBase.value = { ...base };
                applyToCurrentPage(tokens.value);
            },
            onFinish: () => { saving.value = false; },
        });
    };

    if (problems.value.some((check) => check.level === 'bad')) {
        askConfirm({
            title: 'Certains textes risquent d’être illisibles',
            message: 'Le contrôle de lisibilité signale des contrastes trop faibles. Publier ces couleurs quand même ?',
            confirmLabel: 'Publier quand même',
            variant: 'warning',
            onConfirm: send,
        });
        return;
    }
    send();
}

function restoreDefaultTheme() {
    askConfirm({
        title: 'Rétablir les couleurs d’origine ?',
        message: 'Toutes les couleurs du site reviendront à la charte CAMA d’origine.',
        confirmLabel: 'Rétablir',
        variant: 'warning',
        onConfirm: () => {
            router.delete(route('admin.cms.identity.theme.reset'), {
                preserveScroll: true,
                onSuccess: () => {
                    savedBase.value = { ...DEFAULT_BASE };
                    applyPreset({ base: DEFAULT_BASE });
                    applyToCurrentPage(DEFAULT_TOKENS);
                },
            });
        },
    });
}

function ratioLabel(ratio) {
    return `${ratio.toFixed(1).replace('.', ',')} : 1`;
}
</script>

<template>
    <Head title="Identité visuelle" />

    <CmsLayout active-nav="identity" title="Identité visuelle" subtitle="Logo et couleurs appliqués au site public, au back-office, à l'espace assuré et aux e-mails">
        <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_380px] gap-6 items-start pb-24">
            <div class="space-y-6 min-w-0">
                <!-- Logo -->
                <form class="assure-card p-6" @submit.prevent="uploadLogo">
                    <h2 class="text-sm font-bold text-on-surface mb-1 flex items-center gap-2">
                        <span class="material-symbols-outlined text-[18px] text-primary">image</span>
                        Logo du site
                    </h2>
                    <p class="text-xs text-on-surface-variant mb-4">PNG, JPG ou WebP, 2 Mo maximum ; une image carrée sur fond transparent rend le mieux.</p>
                    <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                        <div class="flex items-center gap-3 shrink-0">
                            <div class="text-center">
                                <img :src="logo.url" alt="Logo actuel" class="h-20 w-20 object-contain rounded-lg border border-outline-variant bg-white p-1" />
                                <p class="text-[10px] text-on-surface-variant mt-1">{{ logo.isDefault ? 'Logo d’origine' : 'Logo actuel' }}</p>
                            </div>
                            <template v-if="logoPreview">
                                <span class="material-symbols-outlined text-on-surface-variant">arrow_forward</span>
                                <div class="text-center">
                                    <img :src="logoPreview" alt="Nouveau logo" class="h-20 w-20 object-contain rounded-lg border-2 border-primary bg-white p-1" />
                                    <p class="text-[10px] text-primary font-semibold mt-1">Nouveau</p>
                                </div>
                            </template>
                        </div>
                        <div class="flex-1 min-w-0 space-y-2">
                            <input
                                ref="logoInput"
                                class="block w-full text-xs file:mr-3 file:rounded-lg file:border-0 file:bg-primary/10 file:px-3 file:py-2 file:text-xs file:font-bold file:text-primary"
                                type="file"
                                accept="image/png,image/jpeg,image/webp"
                                @change="onLogoChange"
                            />
                            <p v-if="logoForm.errors.logo" class="text-error text-xs">{{ logoForm.errors.logo }}</p>
                            <div class="flex flex-wrap gap-2">
                                <CamaLoadingButton type="submit" :loading="logoForm.processing" :disabled="!logoForm.logo">Publier ce logo</CamaLoadingButton>
                                <button v-if="logoPreview" class="px-3 py-2 rounded-lg text-xs font-bold border border-outline-variant" type="button" @click="resetLogoSelection">Annuler</button>
                                <button v-if="!logo.isDefault && !logoPreview" class="px-3 py-2 rounded-lg text-xs font-bold border border-outline-variant text-on-surface-variant hover:text-primary" type="button" @click="restoreDefaultLogo">Rétablir le logo d’origine</button>
                            </div>
                        </div>
                    </div>
                </form>

                <!-- Couleurs -->
                <section class="assure-card p-6 space-y-6">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="text-sm font-bold text-on-surface flex items-center gap-2">
                                <span class="material-symbols-outlined text-[18px] text-primary">palette</span>
                                Couleurs du site
                            </h2>
                            <p class="text-xs text-on-surface-variant mt-1">
                                Choisissez 8 couleurs : les nuances (survols, fonds clairs, bordures…) sont calculées automatiquement.
                                Rien n’est visible par les internautes avant « Publier ».
                            </p>
                        </div>
                        <span
                            class="shrink-0 px-2.5 py-0.5 rounded-full text-[11px] font-semibold"
                            :class="theme.custom ? 'bg-primary/10 text-primary' : 'bg-surface-container-low text-on-surface-variant'"
                        >{{ theme.custom ? 'Couleurs personnalisées' : 'Couleurs d’origine' }}</span>
                    </div>

                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-wide text-on-surface-variant mb-2">Partir d’un préréglage</p>
                        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2">
                            <button
                                v-for="preset in THEME_PRESETS"
                                :key="preset.name"
                                type="button"
                                class="text-left rounded-lg border p-2.5 transition hover:border-primary"
                                :class="activePreset === preset.name ? 'border-primary ring-2 ring-primary/20' : 'border-outline-variant'"
                                @click="applyPreset(preset)"
                            >
                                <span class="flex h-6 overflow-hidden rounded-md border border-black/10">
                                    <span v-for="key in ['primary', 'secondary', 'tertiary', 'background', 'text']" :key="key" class="flex-1" :style="{ backgroundColor: preset.base[key] }" />
                                </span>
                                <span class="block text-[11px] font-semibold text-on-surface mt-1.5 truncate">{{ preset.name }}</span>
                            </button>
                        </div>
                    </div>

                    <div v-for="group in GROUPS" :key="group.title">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-on-surface-variant mb-2">{{ group.title }}</p>
                        <div class="divide-y divide-outline-variant/60 rounded-lg border border-outline-variant">
                            <div v-for="key in group.keys" :key="key" class="flex items-center gap-3 p-3">
                                <label class="relative shrink-0 h-10 w-10 rounded-lg border border-black/10 shadow-inner cursor-pointer overflow-hidden" :style="{ backgroundColor: base[key] }" :title="`Choisir : ${colorDefs[key].label}`">
                                    <input class="absolute inset-0 opacity-0 cursor-pointer" type="color" :value="base[key]" :aria-label="colorDefs[key].label" @input="setColor(key, $event.target.value)" />
                                </label>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-on-surface">{{ colorDefs[key].label }}</p>
                                    <p class="text-[11px] text-on-surface-variant truncate">{{ colorDefs[key].hint }}</p>
                                </div>
                                <input
                                    v-model="hexDrafts[key]"
                                    class="w-24 shrink-0 rounded-lg border border-outline-variant px-2 py-1.5 font-mono text-xs uppercase"
                                    maxlength="7"
                                    spellcheck="false"
                                    :aria-label="`Code couleur : ${colorDefs[key].label}`"
                                    @blur="commitHexDraft(key)"
                                    @keydown.enter.prevent="commitHexDraft(key)"
                                />
                                <button
                                    class="shrink-0 w-8 h-8 rounded-lg flex items-center justify-center text-on-surface-variant hover:bg-surface-container-low hover:text-primary disabled:opacity-0"
                                    type="button"
                                    :disabled="base[key] === colorDefs[key].default"
                                    :title="`Revenir à la couleur d’origine (${colorDefs[key].default})`"
                                    @click="setColor(key, colorDefs[key].default)"
                                >
                                    <span class="material-symbols-outlined text-[18px]">restart_alt</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-lg border p-4" :class="problems.length ? 'border-tertiary/40 bg-tertiary/5' : 'border-secondary/30 bg-secondary/5'">
                        <p class="text-sm font-semibold flex items-center gap-2" :class="problems.length ? 'text-tertiary' : 'text-secondary'">
                            <span class="material-symbols-outlined text-[18px]">{{ problems.length ? 'warning' : 'verified' }}</span>
                            {{ problems.length ? `Lisibilité : ${problems.length} point(s) à vérifier` : 'Lisibilité : tous les textes sont bien lisibles' }}
                        </p>
                        <ul class="mt-3 space-y-1.5">
                            <li v-for="check in checks" :key="check.label" class="flex items-center gap-2 text-xs">
                                <span class="shrink-0 inline-flex h-6 min-w-[2.25rem] items-center justify-center rounded px-1 text-[11px] font-bold" :style="{ color: check.fg, backgroundColor: check.bg, boxShadow: 'inset 0 0 0 1px rgba(0,0,0,.08)' }">Aa</span>
                                <span class="flex-1 text-on-surface-variant">{{ check.label }}</span>
                                <span class="tabular-nums text-on-surface-variant">{{ ratioLabel(check.ratio) }}</span>
                                <span
                                    class="material-symbols-outlined text-[16px]"
                                    :class="check.level === 'ok' ? 'text-secondary' : check.level === 'warn' ? 'text-tertiary' : 'text-error'"
                                    :title="check.level === 'ok' ? 'Contraste suffisant' : `Contraste recommandé : ${check.min} : 1 minimum`"
                                >{{ check.level === 'ok' ? 'check_circle' : check.level === 'warn' ? 'error' : 'cancel' }}</span>
                            </li>
                        </ul>
                    </div>

                    <div v-if="theme.custom" class="pt-1">
                        <button class="text-xs font-semibold text-on-surface-variant hover:text-primary inline-flex items-center gap-1" type="button" @click="restoreDefaultTheme">
                            <span class="material-symbols-outlined text-[16px]">restart_alt</span>
                            Rétablir toutes les couleurs d’origine
                        </button>
                    </div>
                </section>
            </div>

            <!-- Aperçu en direct : les variables du thème ne s'appliquent qu'à ce bloc tant que rien n'est publié. -->
            <aside class="xl:sticky xl:top-24 space-y-2">
                <p class="text-[11px] font-bold uppercase tracking-wide text-on-surface-variant">Aperçu en direct</p>
                <div class="rounded-xl overflow-hidden border border-outline-variant shadow-sm bg-background text-on-surface" :style="previewStyle">
                    <div class="flex items-center justify-between gap-2 px-4 py-3 bg-surface-container-lowest border-b border-outline-variant">
                        <div class="flex items-center gap-2 min-w-0">
                            <img :src="logoPreview || logo.url" alt="" class="h-8 w-8 object-contain" />
                            <span class="font-headline font-extrabold text-sm text-on-surface">CAMA</span>
                        </div>
                        <div class="flex items-center gap-3 text-[11px] font-semibold">
                            <span class="text-primary border-b-2 border-primary pb-0.5">Accueil</span>
                            <span class="text-on-surface-variant">Services</span>
                            <span class="hidden sm:inline bg-primary text-on-primary px-2.5 py-1 rounded-md">Espace assuré</span>
                        </div>
                    </div>

                    <div class="bg-primary text-on-primary px-4 py-5">
                        <p class="text-[10px] uppercase tracking-widest opacity-80">CAMA Burkina Faso</p>
                        <p class="font-headline font-bold text-lg leading-tight mt-1">La santé de nos héros, notre priorité</p>
                        <span class="inline-block mt-3 bg-surface-container-lowest text-primary text-[11px] font-bold px-3 py-1.5 rounded-md">Commencer l’enrôlement</span>
                    </div>

                    <div class="p-4 space-y-3">
                        <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-4">
                            <div class="flex items-start gap-3">
                                <span class="w-9 h-9 shrink-0 rounded-lg bg-primary/10 text-primary flex items-center justify-center">
                                    <span class="material-symbols-outlined text-[20px]">health_and_safety</span>
                                </span>
                                <div class="min-w-0">
                                    <p class="font-bold text-sm text-on-surface">Prise en charge des soins</p>
                                    <p class="text-xs text-on-surface-variant mt-0.5">Remboursement des frais médicaux pour les militaires et leurs ayants droit.</p>
                                    <span class="inline-block text-xs font-bold text-primary mt-2">En savoir plus →</span>
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-1.5 mt-3">
                                <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-secondary/10 text-secondary">Dossier validé</span>
                                <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-tertiary/10 text-tertiary">En attente</span>
                                <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-surface-container text-on-surface-variant">Brouillon</span>
                            </div>
                        </div>

                        <div class="bg-surface-container-low rounded-xl p-3 space-y-2">
                            <span class="block text-[10px] font-bold uppercase text-on-surface-variant">E-mail</span>
                            <span class="block w-full rounded-lg border border-outline-variant bg-surface-container-lowest px-3 py-2 text-xs text-outline">nom@exemple.bf</span>
                            <div class="flex gap-2">
                                <span class="flex-1 text-center bg-secondary text-on-secondary text-[11px] font-bold py-2 rounded-lg">Valider</span>
                                <span class="flex-1 text-center border border-outline-variant text-on-surface text-[11px] font-bold py-2 rounded-lg">Annuler</span>
                            </div>
                        </div>
                    </div>

                    <div class="bg-inverse-surface text-inverse-on-surface px-4 py-3 text-[10px]">© CAMA — Caisse d’Assurance Maladie des Armées</div>
                </div>
                <p class="text-[10px] text-on-surface-variant">Les erreurs et alertes système gardent leur rouge habituel, pour rester reconnaissables.</p>
            </aside>
        </div>

        <!-- Barre d'action : visible dès qu'une couleur a été modifiée. -->
        <div
            v-if="dirty"
            class="fixed bottom-4 left-1/2 -translate-x-1/2 z-40 w-[calc(100%-2rem)] max-w-xl rounded-xl border border-outline-variant bg-white shadow-xl px-4 py-3 flex flex-wrap items-center gap-3"
            role="status"
        >
            <span class="material-symbols-outlined text-tertiary">edit</span>
            <p class="flex-1 text-xs text-on-surface">
                <strong>Modifications non publiées.</strong>
                <span class="text-on-surface-variant">{{ isDefaultTheme ? 'Retour aux couleurs d’origine.' : 'Seul l’aperçu est modifié.' }}</span>
            </p>
            <button class="px-3 py-2 rounded-lg text-xs font-bold border border-outline-variant" type="button" @click="cancelChanges">Annuler</button>
            <CamaLoadingButton :loading="saving" loading-text="Publication…" @click="publish">Publier les couleurs</CamaLoadingButton>
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
