<script setup>
import CamaConfirmModal from '@/Components/CamaConfirmModal.vue';
import CamaIconPicker from '@/Components/CamaIconPicker.vue';
import SourceModuleNote from '@/Components/PageBuilder/SourceModuleNote.vue';
import VersionHistoryModal from '@/Components/PageBuilder/VersionHistoryModal.vue';
import { useCamaConfirm } from '@/Composables/useCamaConfirm';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, ref, watch } from 'vue';
import { PALETTE, ANIMATIONS, ELEMENTOR_TABS, ELEMENTOR_ADVANCED_SECTIONS, ELEMENTOR_WIDGET_SCHEMAS, WIDGETS, SYSTEM_PAGE_WIDGETS, DEVICE_PRESETS } from './builderConstants';
import { createBuilderRender } from './builderRender';
import { SECTION_LIBRARY, SECTION_LIBRARY_CATEGORIES, buildSectionFromTemplate } from './builderSectionLibrary';

const props = defineProps({
    currentPage: Object,
    pageOptions: Array,
    statusOptions: Object,
    systemPreviewData: {
        type: Object,
        default: () => ({}),
    },
    mediaItems: {
        type: Array,
        default: () => [],
    },
    versions: {
        type: Array,
        default: () => [],
    },
    cmsPermissions: {
        type: Object,
        default: () => ({}),
    },
});

const page = usePage();
const toast = computed(() => page.props.flash?.success ?? null);

const builderRender = createBuilderRender(() => props.systemPreviewData);
const { styleBox, sectionStyle, columnStyle, renderWidgetHtml, fullHtml, animationClass } = builderRender;

const pageId = ref(props.currentPage.id);
const loadedPageId = ref(props.currentPage.id);
const sections = ref(normalizeSections(props.currentPage.sections ?? []));
const selected = ref(null);
const device = ref('desktop');
const previewDevice = ref('desktop');
const toolsOpen = ref(false);
const sectionLibraryOpen = ref(false);
const sectionLibraryCategory = ref('Tous');
const copiedWidgetStyle = ref(null);
const styleCopiedHint = ref('');
let styleCopiedTimer = null;
const saveState = ref('saved');
const previewOpen = ref(false);
const publicPreviewOpen = ref(false);
const publicPreviewKey = ref(0);
const exportOpen = ref(false);
const mediaOpen = ref(false);
const versionOpen = ref(false);
const mediaTarget = ref(null);
const builderMediaFileName = ref('');
const versionComment = ref('');
const versionPreview = ref(null);
const versionCompare = ref(null);
const structureOpen = ref(false);
const draggedWidget = ref(null);
const history = ref([]);
const historyIndex = ref(-1);
const elementorPanelTab = ref('content');
let saveTimer = null;

const builderMediaForm = useForm({
    file: null,
    title: '',
    alt_text: '',
    folder: 'page-builder',
    category: '',
    tags: '',
    description: '',
});

const { confirmState, askConfirm, confirm, cancel } = useCamaConfirm();

const widgetGroups = computed(() => {
    const groups = {};
    Object.entries(WIDGETS).forEach(([type, widget]) => {
        if (widget.page && widget.page !== currentPageOption.value.slug) return;
        if (!props.cmsPermissions.canUseHtmlWidget && ['html', 'legal_content', 'accessibility_content'].includes(type)) return;
        groups[widget.category] ??= [];
        groups[widget.category].push({ type, ...widget });
    });
    return groups;
});

const currentPageOption = computed(() => props.pageOptions.find((p) => p.id === pageId.value) ?? props.currentPage);
const selectedNode = computed(() => findNode(selected.value));
const previewHtml = computed(() => fullHtml(sections.value));
const publicPreviewUrl = computed(() => currentPageOption.value.href ?? (currentPageOption.value.slug === 'accueil' ? '/' : `/${currentPageOption.value.slug}`));
const canvasWidth = computed(() => device.value === 'mobile' ? '390px' : device.value === 'tablet' ? '768px' : '1100px');
const previewFrame = computed(() => {
    const preset = DEVICE_PRESETS[previewDevice.value] ?? DEVICE_PRESETS.desktop;
    const framed = previewDevice.value !== 'desktop';
    return {
        framed,
        label: preset.label,
        dimensions: framed ? `${preset.width} × ${preset.height}` : 'Pleine largeur',
        style: framed
            ? { width: `${preset.width}px`, height: `${preset.height}px`, maxWidth: '100%' }
            : { width: '100%', maxWidth: '1280px', height: '100%' },
    };
});

const filteredSectionLibrary = computed(() => {
    if (sectionLibraryCategory.value === 'Tous') return SECTION_LIBRARY;
    return SECTION_LIBRARY.filter(t => t.category === sectionLibraryCategory.value);
});

const canPasteStyle = computed(() => copiedWidgetStyle.value !== null);

watch(
    () => props.currentPage,
    (value) => {
        pageId.value = value.id;
        loadedPageId.value = value.id;
        sections.value = normalizeSections(value.sections ?? []);
        selected.value = null;
        clearTimeout(saveTimer);
        saveState.value = 'saved';
        resetHistory();
    },
);

resetHistory();

function uid() {
    return `e${Date.now().toString(36)}${Math.random().toString(36).slice(2, 7)}`;
}

function deep(value) {
    return JSON.parse(JSON.stringify(value ?? null));
}

function defaultElementorAdvanced() {
    return {
        animation: 'none',
        animationDuration: '',
        animationDelay: '',
        title: '',
        elementWidth: '',
        customWidth: '',
        position: '',
        zIndex: '',
        elementId: '',
        cssClasses: '',
        customAttributes: '',
        customCss: '',
        flexGrow: 1,
        flexShrink: 1,
        offsetX: 0,
        offsetY: 0,
        rotate: '',
        translateX: '',
        translateY: '',
        scale: '',
        skewX: '',
        skewY: '',
        backgroundType: '',
        backgroundColor: '',
        backgroundImage: '',
        borderType: '',
        borderWidth: '',
        borderColor: '',
        borderRadius: '',
        boxShadow: '',
        maskSwitch: '',
        maskShape: 'circle',
        maskImage: '',
        maskSize: 'contain',
        maskPosition: 'center center',
        hideDesktop: '',
        hideTablet: '',
        hideMobile: '',
    };
}

function spacing(value = {}, fallback = { t: 0, r: 0, b: 0, l: 0 }) {
    return { ...fallback, ...(value ?? {}) };
}

function normalizeSections(rawSections) {
    const source = Array.isArray(rawSections) && rawSections.length ? rawSections : [createSection([100])];
    return source.map((section) => ({
        uid: section.uid ?? uid(),
        type: 'section',
        settings: {
            contentWidth: 'boxed',
            bgColor: 'transparent',
            padding: { t: 48, r: 16, b: 48, l: 16 },
            margin: { t: 0, r: 0, b: 0, l: 0 },
            radius: 0,
            bgImage: '',
            minHeight: 0,
            animation: 'none',
            hidden: false,
            ...(section.settings ?? {}),
            padding: spacing(section.settings?.padding, { t: 48, r: 16, b: 48, l: 16 }),
            margin: spacing(section.settings?.margin),
        },
        columns: (section.columns?.length ? section.columns : [{ width: 100, widgets: section.widgets ?? [] }]).map((column) => ({
            uid: column.uid ?? uid(),
            type: 'column',
            width: Number(column.width ?? 100),
            settings: {
                padding: { t: 0, r: 0, b: 0, l: 0 },
                margin: { t: 0, r: 0, b: 0, l: 0 },
                bgColor: '',
                radius: 0,
                animation: 'none',
                hidden: false,
                ...(column.settings ?? {}),
                padding: spacing(column.settings?.padding),
                margin: spacing(column.settings?.margin),
            },
            widgets: (column.widgets ?? []).map(normalizeWidget),
        })),
    }));
}

function normalizeWidget(widget) {
    const type = WIDGETS[widget.type] ? widget.type : 'text';
    const defaults = WIDGETS[type].defaults();
    return {
        uid: widget.uid ?? uid(),
        type,
        content: { ...(defaults.content ?? {}), ...(widget.content ?? {}) },
        style: {
            textShadow: { horizontal: 0, vertical: 0, blur: 10, color: 'rgba(0,0,0,0.3)' },
            padding: { t: 0, r: 0, b: 0, l: 0 },
            margin: { t: 0, r: 0, b: 0, l: 0 },
            radius: 0,
            bgColor: '',
            animation: 'none',
            hidden: false,
            ...(defaults.style ?? {}),
            ...(widget.style ?? {}),
            padding: spacing(widget.style?.padding),
            margin: spacing(widget.style?.margin),
            textShadow: { horizontal: 0, vertical: 0, blur: 10, color: 'rgba(0,0,0,0.3)', ...(defaults.style?.textShadow ?? {}), ...(widget.style?.textShadow ?? {}) },
        },
        advanced: { ...(defaults.advanced ?? {}), ...(widget.advanced ?? {}) },
    };
}

function createSection(widths) {
    return {
        uid: uid(),
        type: 'section',
        settings: { contentWidth: 'boxed', bgColor: 'transparent', padding: { t: 48, r: 16, b: 48, l: 16 }, margin: { t: 0, r: 0, b: 0, l: 0 }, radius: 0, bgImage: '', minHeight: 0, animation: 'none', hidden: false },
        columns: widths.map((width) => ({ uid: uid(), type: 'column', width, settings: { padding: { t: 0, r: 0, b: 0, l: 0 }, margin: { t: 0, r: 0, b: 0, l: 0 }, bgColor: '', radius: 0, animation: 'none', hidden: false }, widgets: [] })),
    };
}

function createSystemSection(widgetType) {
    return normalizeSections([{
        uid: uid(),
        type: 'section',
        settings: { contentWidth: 'full', bgColor: 'transparent', padding: { t: 0, r: 0, b: 0, l: 0 }, margin: { t: 0, r: 0, b: 0, l: 0 }, hidden: false },
        columns: [{
            uid: uid(),
            type: 'column',
            width: 100,
            settings: { padding: { t: 0, r: 0, b: 0, l: 0 }, margin: { t: 0, r: 0, b: 0, l: 0 }, hidden: false },
            widgets: [createWidget(widgetType)],
        }],
    }])[0];
}

function systemTemplateSections(slug) {
    return (SYSTEM_PAGE_WIDGETS[slug] ?? []).map(createSystemSection);
}

function createWidget(type) {
    return normalizeWidget(WIDGETS[type].defaults());
}

function findNode(sel) {
    if (!sel) return null;
    for (let si = 0; si < sections.value.length; si += 1) {
        const section = sections.value[si];
        if (sel.kind === 'section' && section.uid === sel.uid) return { kind: 'section', node: section, sectionIndex: si };
        for (let ci = 0; ci < section.columns.length; ci += 1) {
            const column = section.columns[ci];
            if (sel.kind === 'column' && column.uid === sel.uid) return { kind: 'column', node: column, section, sectionIndex: si, columnIndex: ci };
            for (let wi = 0; wi < column.widgets.length; wi += 1) {
                const widget = column.widgets[wi];
                if (sel.kind === 'widget' && widget.uid === sel.uid) return { kind: 'widget', node: widget, section, column, sectionIndex: si, columnIndex: ci, widgetIndex: wi };
            }
        }
    }
    return null;
}

function select(kind, uidValue) {
    selected.value = { kind, uid: uidValue };
}

function commit(skipSnapshot = false) {
    if (!skipSnapshot) pushHistory();
    markDirty();
}

function resetHistory() {
    history.value = [JSON.stringify(sections.value)];
    historyIndex.value = 0;
}

function pushHistory() {
    history.value = history.value.slice(0, historyIndex.value + 1);
    history.value.push(JSON.stringify(sections.value));
    historyIndex.value = history.value.length - 1;
    if (history.value.length > 60) {
        history.value.shift();
        historyIndex.value -= 1;
    }
}

function undo() {
    if (historyIndex.value <= 0) return;
    historyIndex.value -= 1;
    sections.value = JSON.parse(history.value[historyIndex.value]);
    selected.value = null;
    markDirty();
}

function redo() {
    if (historyIndex.value >= history.value.length - 1) return;
    historyIndex.value += 1;
    sections.value = JSON.parse(history.value[historyIndex.value]);
    selected.value = null;
    markDirty();
}

function markDirty() {
    clearTimeout(saveTimer);
    if (saveState.value !== 'saving') {
        saveState.value = 'dirty';
    }
}

function saveNow(options = {}) {
    clearTimeout(saveTimer);
    saveState.value = 'saving';
    return router.put(
        route('admin.cms.page_builder.sections', loadedPageId.value),
        { sections: sections.value, version_comment: versionComment.value || undefined },
        {
            preserveScroll: true,
            preserveState: true,
            ...options,
            onSuccess: () => {
                saveState.value = 'saved';
                versionComment.value = '';
                options.onSuccess?.();
            },
            onError: () => {
                saveState.value = 'error';
                options.onError?.();
            },
        },
    );
}

function publishPage() {
    clearTimeout(saveTimer);
    saveState.value = 'saving';
    router.put(
        route('admin.cms.page_builder.publish', loadedPageId.value),
        { sections: sections.value, version_comment: versionComment.value || undefined },
        {
            preserveScroll: true,
            onSuccess: () => {
                saveState.value = 'saved';
                versionComment.value = '';
            },
            onError: () => { saveState.value = 'error'; },
        },
    );
}

function openPublicPreview() {
    publicPreviewKey.value += 1;
    publicPreviewOpen.value = true;
}

function openBlockPreview() {
    previewDevice.value = device.value;
    previewOpen.value = true;
    toolsOpen.value = false;
}

function closeToolsMenu() {
    toolsOpen.value = false;
}

function openMediaPicker(root = 'content', key = 'src') {
    if (!selectedNode.value?.node) return;
    mediaTarget.value = { root, key };
    mediaOpen.value = true;
}

function chooseMedia(item) {
    if (!mediaTarget.value || !selectedNode.value?.node) return;
    const targetRoot = selectedNode.value.node[mediaTarget.value.root];
    if (!targetRoot) return;

    setPath(targetRoot, mediaTarget.value.key, item.url);
    if (mediaTarget.value.root === 'content' && mediaTarget.value.key === 'src') {
        selectedNode.value.node.content.alt = item.altText || item.title || item.name;
    }
    updateSelected();
    mediaOpen.value = false;
}

function onBuilderMediaFileChange(event) {
    const file = event.target.files?.[0] ?? null;
    builderMediaForm.file = file;
    builderMediaFileName.value = file?.name ?? '';
    if (file && !builderMediaForm.title) {
        builderMediaForm.title = file.name.replace(/\.[^.]+$/, '');
    }
}

function uploadBuilderMedia() {
    builderMediaForm.post(route('admin.cms.media.store'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            builderMediaForm.reset();
            builderMediaForm.folder = 'page-builder';
            builderMediaFileName.value = '';
        },
    });
}

function restoreVersion(version) {
    if (!window.confirm(`Restaurer la version ${version.versionNumber} du ${version.createdAt} ? Les changements non enregistrés seront remplacés.`)) {
        return;
    }

    router.post(
        route('admin.cms.page_builder.versions.restore', {
            page: loadedPageId.value,
            version: version.id,
        }),
        {},
        { preserveScroll: true },
    );
}

function duplicateVersion(version) {
    router.post(
        route('admin.cms.page_builder.versions.duplicate', {
            page: loadedPageId.value,
            version: version.id,
        }),
        {},
        { preserveScroll: true },
    );
}

function openVersionPreview(version) {
    versionPreview.value = version;
}

function openVersionCompare(version) {
    versionCompare.value = version;
}

function countWidgets(sourceSections) {
    return (sourceSections ?? []).reduce((total, section) => total + (section.columns ?? []).reduce((columnTotal, column) => columnTotal + (column.widgets ?? []).length, 0), 0);
}

function widgetTypes(sourceSections) {
    return (sourceSections ?? [])
        .flatMap((section) => section.columns ?? [])
        .flatMap((column) => column.widgets ?? [])
        .map((widget) => widget.type);
}

function versionDiff(version) {
    const currentTypes = widgetTypes(sections.value);
    const versionTypes = widgetTypes(version.sections ?? []);
    const currentSet = new Set(currentTypes);
    const versionSet = new Set(versionTypes);

    return {
        currentSections: sections.value.length,
        versionSections: version.sectionCount,
        currentWidgets: countWidgets(sections.value),
        versionWidgets: version.widgetCount,
        addedTypes: [...currentSet].filter((type) => !versionSet.has(type)),
        removedTypes: [...versionSet].filter((type) => !currentSet.has(type)),
    };
}

function changePage() {
    const targetPageId = pageId.value;

    if (saveState.value === 'dirty' && !window.confirm('Des modifications ne sont pas enregistrées. Changer de page sans enregistrer ?')) {
        pageId.value = loadedPageId.value;
        return;
    }

    clearTimeout(saveTimer);
    router.get(route('admin.cms.page_builder'), { page: targetPageId }, { preserveScroll: true, preserveState: false });
}

function newPage() {
    if (saveState.value === 'dirty' && !window.confirm('Des modifications ne sont pas enregistrées. Créer une nouvelle page sans enregistrer ?')) {
        return;
    }

    const title = window.prompt('Titre de la nouvelle page :', 'Nouvelle page');
    if (title === null) return;

    router.post(
        route('admin.cms.pages.store'),
        { title: title.trim() || 'Nouvelle page', slug: '', status: 'draft', from_builder: true },
        { preserveScroll: true },
    );
}

function addSection(widths) {
    sections.value.push(createSection(widths));
    structureOpen.value = false;
    commit();
}

function insertSectionTemplate(template) {
    const section = buildSectionFromTemplate(template, uid, createWidget, spacing);
    sections.value.push(section);
    selected.value = { kind: 'section', uid: section.uid };
    sectionLibraryOpen.value = false;
    commit();
}

function copyWidgetStyle(widget) {
    copiedWidgetStyle.value = deep({ style: widget.style, advanced: widget.advanced });
    styleCopiedHint.value = 'Style copié — sélectionnez un autre widget puis « Coller ».';
    if (styleCopiedTimer) clearTimeout(styleCopiedTimer);
    styleCopiedTimer = setTimeout(() => { styleCopiedHint.value = ''; }, 3500);
}

function pasteWidgetStyle(widget) {
    if (!copiedWidgetStyle.value) return;
    widget.style = deep({ ...widget.style, ...copiedWidgetStyle.value.style });
    widget.advanced = deep({ ...widget.advanced, ...copiedWidgetStyle.value.advanced });
    updateSelected();
    commit();
}

function duplicateSection(index) {
    const copy = deep(sections.value[index]);
    reuid(copy);
    sections.value.splice(index + 1, 0, copy);
    commit();
}

function deleteSection(index) {
    askConfirm({
        title: 'Supprimer cette section ?',
        message: 'La section et tous ses éléments seront retirés de la page.',
        confirmLabel: 'Supprimer',
        variant: 'danger',
        onConfirm: () => {
            sections.value.splice(index, 1);
            selected.value = null;
            commit();
        },
    });
}

function resetCurrentPageFromTemplate() {
    const template = systemTemplateSections(currentPageOption.value.slug);
    if (!template.length) {
        window.alert('Cette page ne possède pas encore de maquette système.');
        return;
    }

    askConfirm({
        title: 'Réinitialiser depuis la maquette ?',
        message: 'Les sections actuelles seront remplacées par la structure officielle de cette page.',
        confirmLabel: 'Réinitialiser',
        variant: 'danger',
        onConfirm: () => {
            sections.value = template;
            selected.value = null;
            commit();
        },
    });
}

function toggleSectionHidden(section) {
    section.settings.hidden = !section.settings.hidden;
    commit();
}

function moveSection(index, dir) {
    const next = index + dir;
    if (next < 0 || next >= sections.value.length) return;
    [sections.value[index], sections.value[next]] = [sections.value[next], sections.value[index]];
    commit();
}

function addColumn(section) {
    const count = section.columns.length + 1;
    section.columns.forEach((column) => { column.width = Math.round(100 / count); });
    section.columns.push({ uid: uid(), type: 'column', width: Math.round(100 / count), settings: { padding: { t: 0, r: 0, b: 0, l: 0 } }, widgets: [] });
    commit();
}

function deleteColumn(section, index) {
    if (section.columns.length <= 1) return;
    section.columns.splice(index, 1);
    const width = Math.round(100 / section.columns.length);
    section.columns.forEach((column) => { column.width = width; });
    selected.value = null;
    commit();
}

function toggleColumnHidden(column) {
    column.settings.hidden = !column.settings.hidden;
    commit();
}

function addWidget(column, type) {
    const widget = createWidget(type);
    column.widgets.push(widget);
    selected.value = { kind: 'widget', uid: widget.uid };
    commit();
}

function duplicateWidget(column, index) {
    const copy = deep(column.widgets[index]);
    reuid(copy);
    column.widgets.splice(index + 1, 0, copy);
    commit();
}

function deleteWidget(column, index) {
    column.widgets.splice(index, 1);
    selected.value = null;
    commit();
}

function toggleWidgetHidden(widget) {
    widget.style.hidden = !widget.style.hidden;
    commit();
}

function onDropWidget(column) {
    if (!draggedWidget.value) return;
    addWidget(column, draggedWidget.value);
    draggedWidget.value = null;
}

function reuid(node) {
    node.uid = uid();
    node.columns?.forEach(reuid);
    node.widgets?.forEach(reuid);
}

function updateSelected() {
    commit(true);
}

function isElementorWidget(widget) {
    return widget?.type?.startsWith('elementor_');
}

function elementorSchema(widget) {
    return ELEMENTOR_WIDGET_SCHEMAS[widget?.type] ?? { content: [], style: [] };
}

function pathRoot(control, tab) {
    if (control.target) return control.target;
    if (tab === 'advanced') return 'advanced';
    if (tab === 'style') return 'style';
    return 'content';
}

function getPath(root, path) {
    return String(path).split('.').reduce((value, key) => value?.[key], root);
}

function setPath(root, path, value) {
    const keys = String(path).split('.');
    let cursor = root;
    keys.slice(0, -1).forEach((key) => {
        cursor[key] ??= {};
        cursor = cursor[key];
    });
    cursor[keys[keys.length - 1]] = value;
}

function controlValue(widget, tab, control) {
    return getPath(widget[pathRoot(control, tab)], control.key);
}

function setControlValue(widget, tab, control, value) {
    setPath(widget[pathRoot(control, tab)], control.key, value);
    updateSelected();
}

function controlItems(widget, tab, control) {
    const value = controlValue(widget, tab, control);
    if (Array.isArray(value)) return value;
    setControlValue(widget, tab, control, []);
    return controlValue(widget, tab, control);
}

function addControlItem(widget, tab, control) {
    const item = {};
    (control.fields ?? ['title', 'content']).forEach((field) => {
        item[field] = field === 'image' ? '/images/CAMA_1.jfif' : field === 'type' ? 'text' : '';
    });
    controlItems(widget, tab, control).push(item);
    updateSelected();
}

function removeControlItem(widget, tab, control, index) {
    controlItems(widget, tab, control).splice(index, 1);
    updateSelected();
}

function addRepeaterItem(key, template) {
    selectedNode.value.node.content[key] ??= [];
    selectedNode.value.node.content[key].push(deep(template));
    updateSelected();
}

function removeRepeaterItem(key, index) {
    selectedNode.value.node.content[key].splice(index, 1);
    updateSelected();
}

async function copyExport() {
    await navigator.clipboard.writeText(previewHtml.value);
    exportOpen.value = false;
}
</script>

<template>
    <Head title="Éditeur de pages" />

    <div class="bg-surface-container-low text-on-background font-body-md h-screen overflow-hidden flex flex-col">
        <header class="border-b border-outline-variant bg-white shrink-0 z-30">
            <!-- Ligne 1 : navigation page + actions principales -->
            <div class="flex flex-wrap items-center gap-2 px-3 py-2 min-h-[52px]">
                <a class="flex items-center justify-center w-9 h-9 rounded-lg text-on-surface-variant hover:bg-surface-container-low shrink-0" :href="route('admin.cms.dashboard')" title="Retour au studio CMS">
                    <span class="material-symbols-outlined text-[20px]">arrow_back</span>
                </a>

                <div class="flex items-center gap-2 min-w-0 flex-1">
                    <select v-model="pageId" class="text-sm font-bold border border-outline-variant rounded-lg px-2.5 py-2 bg-white min-w-[140px] max-w-[220px]" title="Page en cours d'édition" @change="changePage">
                        <option v-for="option in pageOptions" :key="option.id" :value="option.id">
                            {{ option.title }}{{ option.status === 'draft' ? ' (brouillon)' : '' }}
                        </option>
                    </select>
                    <span
                        class="text-[10px] font-bold uppercase px-2 py-1 rounded shrink-0"
                        :class="currentPageOption.status === 'published' ? 'bg-secondary/15 text-secondary' : 'bg-tertiary-container/40 text-tertiary'"
                    >
                        {{ currentPageOption.statusLabel }}
                    </span>
                    <span class="hidden lg:inline text-[11px] text-on-surface-variant truncate max-w-[180px]" :title="publicPreviewUrl">
                        <span class="material-symbols-outlined text-[14px] align-middle mr-0.5">language</span>{{ publicPreviewUrl }}
                    </span>
                </div>

                <div class="flex items-center gap-1.5 shrink-0 ml-auto">
                    <div class="flex items-center gap-0.5 bg-surface-container-low rounded-lg p-1">
                        <button class="device-btn px-2 py-1.5 rounded-md" :class="{ 'is-active': device === 'desktop' }" type="button" title="Bureau" @click="device = 'desktop'"><span class="material-symbols-outlined text-[18px]">desktop_windows</span></button>
                        <button class="device-btn px-2 py-1.5 rounded-md" :class="{ 'is-active': device === 'tablet' }" type="button" title="Tablette" @click="device = 'tablet'"><span class="material-symbols-outlined text-[18px]">tablet_mac</span></button>
                        <button class="device-btn px-2 py-1.5 rounded-md" :class="{ 'is-active': device === 'mobile' }" type="button" title="Mobile" @click="device = 'mobile'"><span class="material-symbols-outlined text-[18px]">smartphone</span></button>
                    </div>

                    <span class="hidden sm:inline text-[11px] text-on-surface-variant px-2" :title="saveState">
                        <span class="material-symbols-outlined text-[15px] align-middle">{{ saveState === 'saving' ? 'cloud_sync' : saveState === 'error' ? 'error' : saveState === 'dirty' ? 'edit' : 'cloud_done' }}</span>
                        <span class="hidden md:inline ml-0.5">{{ saveState === 'saving' ? '…' : saveState === 'dirty' ? 'Modifié' : '' }}</span>
                    </span>

                    <button class="px-3 py-2 rounded-lg text-sm font-bold border border-primary text-primary disabled:opacity-50 shrink-0" type="button" :disabled="saveState === 'saving'" title="Enregistrer le brouillon" @click="saveNow()">
                        <span class="material-symbols-outlined text-[18px] align-middle">save</span>
                        <span class="hidden sm:inline ml-1">Enregistrer</span>
                    </button>
                    <button class="px-3 py-2 rounded-lg text-sm font-bold bg-primary text-on-primary disabled:opacity-50 shrink-0" type="button" :disabled="!cmsPermissions.canPublishPages" title="Publier sur le site" @click="publishPage">
                        <span class="material-symbols-outlined text-[18px] align-middle">cloud_upload</span>
                        <span class="hidden sm:inline ml-1">Publier</span>
                    </button>
                </div>
            </div>

            <!-- Ligne 2 : outils secondaires -->
            <div class="flex flex-wrap items-center gap-1.5 px-3 py-1.5 bg-surface-container-low/50 border-t border-outline-variant/60">
                <button class="pb-tool-btn" type="button" title="Annuler" :disabled="historyIndex <= 0" @click="undo"><span class="material-symbols-outlined text-[18px]">undo</span></button>
                <button class="pb-tool-btn" type="button" title="Rétablir" :disabled="historyIndex >= history.length - 1" @click="redo"><span class="material-symbols-outlined text-[18px]">redo</span></button>
                <span class="w-px h-5 bg-outline-variant mx-0.5" />

                <button class="pb-tool-btn" type="button" title="Nouvelle page" @click="newPage"><span class="material-symbols-outlined text-[18px]">add_circle</span></button>
                <a class="pb-tool-btn" :href="route('admin.cms.pages')" title="Toutes les pages"><span class="material-symbols-outlined text-[18px]">description</span></a>
                <a class="pb-tool-btn" :href="route('admin.cms.menus')" title="Menus"><span class="material-symbols-outlined text-[18px]">menu</span></a>
                <span class="w-px h-5 bg-outline-variant mx-0.5" />

                <button class="pb-action-btn" type="button" @click="openBlockPreview"><span class="material-symbols-outlined text-[16px]">visibility</span><span class="hidden md:inline">Aperçu</span></button>
                <button class="pb-action-btn text-primary border-primary/30" type="button" @click="openPublicPreview"><span class="material-symbols-outlined text-[16px]">web_asset</span><span class="hidden md:inline">Site public</span></button>
                <button class="pb-action-btn" type="button" @click="sectionLibraryOpen = true"><span class="material-symbols-outlined text-[16px]">dashboard</span><span class="hidden md:inline">Sections</span></button>

                <div class="relative ml-auto">
                    <button class="pb-action-btn" type="button" :class="{ 'border-primary text-primary bg-primary/5': toolsOpen }" @click="toolsOpen = !toolsOpen">
                        <span class="material-symbols-outlined text-[16px]">more_horiz</span>
                        <span class="hidden sm:inline">Outils</span>
                    </button>
                    <div v-if="toolsOpen" class="fixed inset-0 z-40" @click="closeToolsMenu" />
                    <div v-if="toolsOpen" class="absolute right-0 top-full mt-1 z-50 w-56 bg-white border border-outline-variant rounded-xl shadow-xl py-1.5 text-sm">
                        <button class="pb-menu-item" type="button" @click="mediaOpen = true; closeToolsMenu()"><span class="material-symbols-outlined text-[18px]">perm_media</span>Médiathèque</button>
                        <button class="pb-menu-item" type="button" @click="versionOpen = true; closeToolsMenu()"><span class="material-symbols-outlined text-[18px]">history</span>Versions</button>
                        <button class="pb-menu-item" type="button" @click="resetCurrentPageFromTemplate(); closeToolsMenu()"><span class="material-symbols-outlined text-[18px]">restart_alt</span>Restaurer maquette</button>
                        <button class="pb-menu-item" type="button" @click="exportOpen = true; closeToolsMenu()"><span class="material-symbols-outlined text-[18px]">code</span>Exporter HTML</button>
                        <div class="border-t border-outline-variant my-1.5 mx-2" />
                        <div class="px-3 py-2">
                            <label class="text-[10px] font-bold uppercase text-on-surface-variant">Commentaire version</label>
                            <input v-model="versionComment" class="mt-1 w-full text-xs border border-outline-variant rounded-lg px-2 py-1.5" placeholder="Optionnel…" @keydown.enter="closeToolsMenu()" />
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <div class="flex-1 flex overflow-hidden">
            <aside class="w-64 border-r border-outline-variant bg-white flex flex-col shrink-0">
                <div class="p-3 border-b border-outline-variant flex items-center gap-2">
                    <span class="material-symbols-outlined text-[18px] text-primary">widgets</span>
                    <p class="text-[11px] uppercase font-bold text-on-surface-variant tracking-wider">Éléments</p>
                </div>
                <div class="flex-1 overflow-y-auto no-scrollbar p-3">
                    <template v-for="(items, category) in widgetGroups" :key="category">
                        <p class="text-[10px] uppercase font-bold text-on-surface-variant tracking-wider mb-2 mt-1">{{ category }}</p>
                        <div class="grid grid-cols-2 gap-2 mb-4">
                            <div
                                v-for="widget in items"
                                :key="widget.type"
                                class="widget-card border border-outline-variant rounded-lg p-3 flex flex-col items-center gap-1.5 text-center hover:border-primary hover:bg-primary/5"
                                draggable="true"
                                @dragstart="draggedWidget = widget.type"
                                @dragend="draggedWidget = null"
                            >
                                <span class="material-symbols-outlined text-primary text-[22px]">{{ widget.icon }}</span>
                                <span class="text-[10px] font-semibold leading-tight">{{ widget.label }}</span>
                            </div>
                        </div>
                    </template>
                </div>
            </aside>

            <main class="flex-1 overflow-y-auto bg-surface-container p-6 flex justify-center">
                <div class="bg-white shadow-md transition-all w-full mb-24" :style="{ maxWidth: canvasWidth }">
                    <div class="border-b border-outline-variant bg-white sticky top-0 z-10">
                        <div class="h-16 px-6 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <img alt="Logo CAMA" class="h-10 w-10 object-contain" src="/images/logo_cama.png" />
                                <div class="leading-tight">
                                    <p class="font-headline-lg font-bold text-on-surface">CAMA</p>
                                    <p class="text-[10px] uppercase tracking-wide text-on-surface-variant">Aperçu site public</p>
                                </div>
                            </div>
                            <div class="hidden lg:flex gap-5 text-xs font-bold text-on-surface-variant">
                                <span>Accueil</span><span>À propos</span><span>Services</span><span>Ressources</span><span>Actualités</span><span>Contact</span>
                            </div>
                        </div>
                    </div>
                    <div>
                        <div
                            v-for="(section, sectionIndex) in sections"
                            :key="section.uid"
                            class="cv-section relative"
                            :class="[{ 'is-selected': selected?.uid === section.uid, 'opacity-45 outline outline-1 outline-dashed outline-tertiary': section.settings.hidden }, animationClass(section.settings)]"
                            :style="sectionStyle(section)"
                            @click.stop="select('section', section.uid)"
                        >
                            <span class="el-label sec-lb bg-primary text-white">Section</span>
                            <div class="el-toolbar sec-tb top-1 left-1/2 -translate-x-1/2 bg-primary text-white rounded-full text-[11px] overflow-hidden shadow-lg">
                                <button class="px-2 py-1 hover:bg-black/20" type="button" title="Ajouter colonne" @click.stop="addColumn(section)"><span class="material-symbols-outlined text-[14px]">view_column</span></button>
                                <button class="px-2 py-1 hover:bg-black/20" type="button" title="Monter" @click.stop="moveSection(sectionIndex, -1)"><span class="material-symbols-outlined text-[14px]">arrow_upward</span></button>
                                <button class="px-2 py-1 hover:bg-black/20" type="button" title="Descendre" @click.stop="moveSection(sectionIndex, 1)"><span class="material-symbols-outlined text-[14px]">arrow_downward</span></button>
                                <button class="px-2 py-1 hover:bg-black/20" type="button" :title="section.settings.hidden ? 'Afficher' : 'Masquer'" @click.stop="toggleSectionHidden(section)"><span class="material-symbols-outlined text-[14px]">{{ section.settings.hidden ? 'visibility' : 'visibility_off' }}</span></button>
                                <button class="px-2 py-1 hover:bg-black/20" type="button" title="Dupliquer" @click.stop="duplicateSection(sectionIndex)"><span class="material-symbols-outlined text-[14px]">content_copy</span></button>
                                <button class="px-2 py-1 hover:bg-black/20" type="button" title="Supprimer" @click.stop="deleteSection(sectionIndex)"><span class="material-symbols-outlined text-[14px]">delete</span></button>
                            </div>
                            <div class="flex flex-wrap gap-6" :class="section.settings.contentWidth === 'full' ? '' : 'max-w-[1100px] mx-auto'">
                                <div
                                    v-for="(column, columnIndex) in section.columns"
                                    :key="column.uid"
                                    class="cv-col relative"
                                    :class="[{ 'is-selected': selected?.uid === column.uid, 'opacity-45 outline outline-1 outline-dashed outline-tertiary': column.settings.hidden }, animationClass(column.settings)]"
                                    :style="columnStyle(column)"
                                    @click.stop="select('column', column.uid)"
                                    @dragover.prevent
                                    @drop.stop="onDropWidget(column)"
                                >
                                    <span class="el-label col-lb bg-secondary text-white">Colonne {{ column.width }}%</span>
                                    <div class="el-toolbar col-tb top-1 right-1 bg-secondary text-white rounded-md text-[11px] overflow-hidden">
                                        <button class="px-1.5 py-1 hover:bg-black/20" type="button" :title="column.settings.hidden ? 'Afficher' : 'Masquer'" @click.stop="toggleColumnHidden(column)"><span class="material-symbols-outlined text-[14px]">{{ column.settings.hidden ? 'visibility' : 'visibility_off' }}</span></button>
                                        <button class="px-1.5 py-1 hover:bg-black/20" type="button" title="Supprimer" @click.stop="deleteColumn(section, columnIndex)"><span class="material-symbols-outlined text-[14px]">delete</span></button>
                                    </div>
                                    <div v-if="!column.widgets.length" class="drop-empty py-8 text-center text-[11px] text-on-surface-variant">
                                        Glissez un élément ici
                                    </div>
                                    <div
                                        v-for="(widget, widgetIndex) in column.widgets"
                                        :key="widget.uid"
                                        class="cv-widget relative"
                                        :class="[{ 'is-selected': selected?.uid === widget.uid, 'opacity-45 outline outline-1 outline-dashed outline-tertiary': widget.style.hidden }, animationClass(widget.style)]"
                                        :style="styleBox(widget.style)"
                                        @click.stop="select('widget', widget.uid)"
                                    >
                                        <div class="el-toolbar wid-tb -top-3 right-1 bg-primary text-white rounded-md text-[11px] overflow-hidden">
                                            <span class="px-2 py-1 font-semibold inline-flex items-center gap-1">{{ WIDGETS[widget.type].label }}</span>
                                            <button class="px-1.5 py-1 hover:bg-black/20" type="button" :title="widget.style.hidden ? 'Afficher' : 'Masquer'" @click.stop="toggleWidgetHidden(widget)"><span class="material-symbols-outlined text-[14px]">{{ widget.style.hidden ? 'visibility' : 'visibility_off' }}</span></button>
                                            <button class="px-1.5 py-1 hover:bg-black/20" type="button" title="Copier le style" @click.stop="copyWidgetStyle(widget)"><span class="material-symbols-outlined text-[14px]">format_paint</span></button>
                                            <button class="px-1.5 py-1 hover:bg-black/20 disabled:opacity-30" type="button" title="Coller le style" :disabled="!canPasteStyle" @click.stop="pasteWidgetStyle(widget)"><span class="material-symbols-outlined text-[14px]">content_paste</span></button>
                                            <button class="px-1.5 py-1 hover:bg-black/20" type="button" title="Dupliquer" @click.stop="duplicateWidget(column, widgetIndex)"><span class="material-symbols-outlined text-[14px]">content_copy</span></button>
                                            <button class="px-1.5 py-1 hover:bg-black/20" type="button" title="Supprimer" @click.stop="deleteWidget(column, widgetIndex)"><span class="material-symbols-outlined text-[14px]">delete</span></button>
                                        </div>
                                        <div v-html="renderWidgetHtml(widget)" />
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="p-4 flex flex-col sm:flex-row gap-2">
                            <button class="flex-1 border-2 border-dashed border-outline-variant rounded-xl py-3 text-sm font-bold text-on-surface-variant hover:border-primary hover:text-primary flex items-center justify-center gap-2" type="button" @click="structureOpen = true">
                                <span class="material-symbols-outlined">add</span>
                                Section vide
                            </button>
                            <button class="flex-1 border-2 border-primary/30 bg-primary/5 rounded-xl py-3 text-sm font-bold text-primary hover:bg-primary/10 flex items-center justify-center gap-2" type="button" @click="sectionLibraryOpen = true">
                                <span class="material-symbols-outlined">dashboard</span>
                                Bibliothèque
                            </button>
                        </div>
                    </div>
                    <div class="bg-on-background text-white px-6 py-8 text-xs">
                        <div class="flex items-center gap-3">
                            <img alt="Logo CAMA" class="h-9 w-9 object-contain bg-white rounded" src="/images/logo_cama.png" />
                            <span>© CAMA - Pied de page public</span>
                        </div>
                    </div>
                </div>
            </main>

            <aside class="w-80 border-l border-outline-variant bg-white shrink-0 flex flex-col">
                <div v-if="!selectedNode" class="p-6 text-center text-xs text-on-surface-variant mt-10">
                    <span class="material-symbols-outlined text-3xl text-outline-variant block mb-2">tune</span>
                    Sélectionnez une section, une colonne ou un widget dans la page pour modifier ses réglages.
                </div>
                <div v-else class="flex flex-col flex-1 overflow-hidden">
                    <div class="px-3 py-3 flex items-center gap-2 border-b border-outline-variant">
                        <span class="material-symbols-outlined text-[18px] text-primary">{{ selectedNode.kind === 'widget' ? WIDGETS[selectedNode.node.type].icon : selectedNode.kind === 'column' ? 'view_column' : 'crop_landscape' }}</span>
                        <span class="text-xs font-bold flex-1 truncate">{{ selectedNode.kind === 'widget' ? WIDGETS[selectedNode.node.type].label : selectedNode.kind === 'column' ? 'Colonne' : 'Section' }}</span>
                        <button class="text-on-surface-variant hover:text-primary" type="button" @click="selected = null"><span class="material-symbols-outlined text-[16px]">close</span></button>
                    </div>
                    <div class="flex-1 overflow-y-auto no-scrollbar p-4 space-y-3">
                        <template v-if="selectedNode.kind === 'section'">
                            <label class="inline-flex items-center gap-2 text-[11px] font-bold text-on-surface-variant bg-surface-container-low rounded-lg px-3 py-2">
                                <input v-model="selectedNode.node.settings.hidden" type="checkbox" @change="updateSelected" />
                                Masquer cette section sur le site public
                            </label>
                            <label class="ctrl-label">Largeur du contenu</label>
                            <select v-model="selectedNode.node.settings.contentWidth" class="ctrl-select" @change="updateSelected">
                                <option value="boxed">Centré</option>
                                <option value="full">Pleine largeur</option>
                            </select>
                            <label class="ctrl-label">Couleur de fond</label>
                            <select v-model="selectedNode.node.settings.bgColor" class="ctrl-select" @change="updateSelected">
                                <option v-for="(_, token) in PALETTE" :key="token" :value="token">{{ token }}</option>
                            </select>
                            <label class="ctrl-label">Image de fond</label>
                            <input v-model="selectedNode.node.settings.bgImage" class="ctrl-input" placeholder="/images/CAMA_8.jfif" @input="updateSelected" />
                            <button class="w-full border border-dashed border-outline-variant rounded-lg py-1.5 text-[11px] font-semibold text-primary" type="button" @click="openMediaPicker('settings', 'bgImage')">
                                Choisir depuis la médiathèque
                            </button>
                            <label class="ctrl-label">Hauteur min. (px)</label>
                            <input v-model.number="selectedNode.node.settings.minHeight" class="ctrl-input" type="number" min="0" @input="updateSelected" />
                            <label class="ctrl-label">Padding haut / droite / bas / gauche</label>
                            <div class="grid grid-cols-4 gap-2">
                                <input v-model.number="selectedNode.node.settings.padding.t" class="ctrl-input" type="number" placeholder="H" @input="updateSelected" />
                                <input v-model.number="selectedNode.node.settings.padding.r" class="ctrl-input" type="number" placeholder="D" @input="updateSelected" />
                                <input v-model.number="selectedNode.node.settings.padding.b" class="ctrl-input" type="number" placeholder="B" @input="updateSelected" />
                                <input v-model.number="selectedNode.node.settings.padding.l" class="ctrl-input" type="number" placeholder="G" @input="updateSelected" />
                            </div>
                            <label class="ctrl-label">Marge haut / droite / bas / gauche</label>
                            <div class="grid grid-cols-4 gap-2">
                                <input v-model.number="selectedNode.node.settings.margin.t" class="ctrl-input" type="number" placeholder="H" @input="updateSelected" />
                                <input v-model.number="selectedNode.node.settings.margin.r" class="ctrl-input" type="number" placeholder="D" @input="updateSelected" />
                                <input v-model.number="selectedNode.node.settings.margin.b" class="ctrl-input" type="number" placeholder="B" @input="updateSelected" />
                                <input v-model.number="selectedNode.node.settings.margin.l" class="ctrl-input" type="number" placeholder="G" @input="updateSelected" />
                            </div>
                            <label class="ctrl-label">Arrondi</label>
                            <input v-model.number="selectedNode.node.settings.radius" class="ctrl-input" type="number" min="0" @input="updateSelected" />
                            <label class="ctrl-label">Animation</label>
                            <select v-model="selectedNode.node.settings.animation" class="ctrl-select" @change="updateSelected">
                                <option v-for="(label, key) in ANIMATIONS" :key="key" :value="key">{{ label }}</option>
                            </select>
                        </template>

                        <template v-else-if="selectedNode.kind === 'column'">
                            <label class="inline-flex items-center gap-2 text-[11px] font-bold text-on-surface-variant bg-surface-container-low rounded-lg px-3 py-2">
                                <input v-model="selectedNode.node.settings.hidden" type="checkbox" @change="updateSelected" />
                                Masquer cette colonne sur le site public
                            </label>
                            <label class="ctrl-label">Largeur (%)</label>
                            <input v-model.number="selectedNode.node.width" class="ctrl-input" min="10" max="100" type="number" @input="updateSelected" />
                            <label class="ctrl-label">Couleur de fond</label>
                            <select v-model="selectedNode.node.settings.bgColor" class="ctrl-select" @change="updateSelected">
                                <option value="">Aucune</option>
                                <option v-for="(_, token) in PALETTE" :key="token" :value="token">{{ token }}</option>
                            </select>
                            <label class="ctrl-label">Padding haut / droite / bas / gauche</label>
                            <div class="grid grid-cols-4 gap-2">
                                <input v-model.number="selectedNode.node.settings.padding.t" class="ctrl-input" type="number" placeholder="H" @input="updateSelected" />
                                <input v-model.number="selectedNode.node.settings.padding.r" class="ctrl-input" type="number" placeholder="D" @input="updateSelected" />
                                <input v-model.number="selectedNode.node.settings.padding.b" class="ctrl-input" type="number" placeholder="B" @input="updateSelected" />
                                <input v-model.number="selectedNode.node.settings.padding.l" class="ctrl-input" type="number" placeholder="G" @input="updateSelected" />
                            </div>
                            <label class="ctrl-label">Marge haut / droite / bas / gauche</label>
                            <div class="grid grid-cols-4 gap-2">
                                <input v-model.number="selectedNode.node.settings.margin.t" class="ctrl-input" type="number" placeholder="H" @input="updateSelected" />
                                <input v-model.number="selectedNode.node.settings.margin.r" class="ctrl-input" type="number" placeholder="D" @input="updateSelected" />
                                <input v-model.number="selectedNode.node.settings.margin.b" class="ctrl-input" type="number" placeholder="B" @input="updateSelected" />
                                <input v-model.number="selectedNode.node.settings.margin.l" class="ctrl-input" type="number" placeholder="G" @input="updateSelected" />
                            </div>
                            <label class="ctrl-label">Arrondi</label>
                            <input v-model.number="selectedNode.node.settings.radius" class="ctrl-input" type="number" min="0" @input="updateSelected" />
                            <label class="ctrl-label">Animation</label>
                            <select v-model="selectedNode.node.settings.animation" class="ctrl-select" @change="updateSelected">
                                <option v-for="(label, key) in ANIMATIONS" :key="key" :value="key">{{ label }}</option>
                            </select>
                        </template>

                        <template v-else>
                            <label class="inline-flex items-center gap-2 text-[11px] font-bold text-on-surface-variant bg-surface-container-low rounded-lg px-3 py-2">
                                <input v-model="selectedNode.node.style.hidden" type="checkbox" @change="updateSelected" />
                                Masquer ce widget sur le site public
                            </label>
                            <div class="flex gap-2">
                                <button class="flex-1 inline-flex items-center justify-center gap-1.5 border border-outline-variant rounded-lg py-2 text-[11px] font-bold text-on-surface-variant hover:border-primary hover:text-primary transition-colors" type="button" @click="copyWidgetStyle(selectedNode.node)">
                                    <span class="material-symbols-outlined text-[15px]">format_paint</span>
                                    Copier le style
                                </button>
                                <button class="flex-1 inline-flex items-center justify-center gap-1.5 border border-outline-variant rounded-lg py-2 text-[11px] font-bold text-on-surface-variant hover:border-primary hover:text-primary transition-colors disabled:opacity-30 disabled:pointer-events-none" type="button" :disabled="!canPasteStyle" @click="pasteWidgetStyle(selectedNode.node)">
                                    <span class="material-symbols-outlined text-[15px]">content_paste</span>
                                    Coller le style
                                </button>
                            </div>
                            <p v-if="styleCopiedHint" class="text-[10px] text-primary font-semibold leading-snug">{{ styleCopiedHint }}</p>
                            <label class="ctrl-label">Alignement</label>
                            <select v-model="selectedNode.node.style.align" class="ctrl-select" @change="updateSelected">
                                <option value="left">Gauche</option>
                                <option value="center">Centre</option>
                                <option value="right">Droite</option>
                            </select>

                            <label class="ctrl-label">Couleur texte</label>
                            <select v-model="selectedNode.node.style.textColor" class="ctrl-select" @change="updateSelected">
                                <option v-for="(_, token) in PALETTE" :key="token" :value="token">{{ token }}</option>
                            </select>

                            <label class="ctrl-label">Fond du widget</label>
                            <select v-model="selectedNode.node.style.bgColor" class="ctrl-select" @change="updateSelected">
                                <option value="">Aucun</option>
                                <option v-for="(_, token) in PALETTE" :key="token" :value="token">{{ token }}</option>
                            </select>
                            <label class="ctrl-label">Padding haut / droite / bas / gauche</label>
                            <div class="grid grid-cols-4 gap-2">
                                <input v-model.number="selectedNode.node.style.padding.t" class="ctrl-input" type="number" placeholder="H" @input="updateSelected" />
                                <input v-model.number="selectedNode.node.style.padding.r" class="ctrl-input" type="number" placeholder="D" @input="updateSelected" />
                                <input v-model.number="selectedNode.node.style.padding.b" class="ctrl-input" type="number" placeholder="B" @input="updateSelected" />
                                <input v-model.number="selectedNode.node.style.padding.l" class="ctrl-input" type="number" placeholder="G" @input="updateSelected" />
                            </div>
                            <label class="ctrl-label">Marge haut / droite / bas / gauche</label>
                            <div class="grid grid-cols-4 gap-2">
                                <input v-model.number="selectedNode.node.style.margin.t" class="ctrl-input" type="number" placeholder="H" @input="updateSelected" />
                                <input v-model.number="selectedNode.node.style.margin.r" class="ctrl-input" type="number" placeholder="D" @input="updateSelected" />
                                <input v-model.number="selectedNode.node.style.margin.b" class="ctrl-input" type="number" placeholder="B" @input="updateSelected" />
                                <input v-model.number="selectedNode.node.style.margin.l" class="ctrl-input" type="number" placeholder="G" @input="updateSelected" />
                            </div>
                            <label class="ctrl-label">Arrondi</label>
                            <input v-model.number="selectedNode.node.style.radius" class="ctrl-input" type="number" min="0" @input="updateSelected" />
                            <label class="ctrl-label">Animation</label>
                            <select v-model="selectedNode.node.style.animation" class="ctrl-select" @change="updateSelected">
                                <option v-for="(label, key) in ANIMATIONS" :key="key" :value="key">{{ label }}</option>
                            </select>

                            <template v-if="isElementorWidget(selectedNode.node)">
                                <div class="grid grid-cols-3 gap-1 bg-surface-container-low rounded-lg p-1">
                                    <button
                                        v-for="(label, key) in ELEMENTOR_TABS"
                                        :key="key"
                                        class="rounded-md px-2 py-1.5 text-[11px] font-bold"
                                        :class="elementorPanelTab === key ? 'bg-white text-primary shadow-sm' : 'text-on-surface-variant'"
                                        type="button"
                                        @click="elementorPanelTab = key"
                                    >
                                        {{ label }}
                                    </button>
                                </div>

                                <template v-if="elementorPanelTab === 'content'">
                                    <div class="rounded-lg border border-outline-variant p-3 space-y-3">
                                        <p class="text-[11px] font-bold uppercase tracking-wider text-primary">Contenu</p>
                                        <template v-for="control in elementorSchema(selectedNode.node).content" :key="control.key">
                                            <label class="ctrl-label">{{ control.label }}</label>
                                            <textarea v-if="control.type === 'textarea'" :value="controlValue(selectedNode.node, 'content', control)" rows="5" class="ctrl-area" @input="setControlValue(selectedNode.node, 'content', control, $event.target.value)" />
                                            <select v-else-if="control.type === 'select'" :value="controlValue(selectedNode.node, 'content', control)" class="ctrl-select" @change="setControlValue(selectedNode.node, 'content', control, $event.target.value)">
                                                <option v-for="option in control.options" :key="option" :value="option">{{ option || 'Défaut' }}</option>
                                            </select>
                                            <label v-else-if="control.type === 'checkbox'" class="inline-flex items-center gap-2 text-[11px] font-semibold text-on-surface-variant">
                                                <input type="checkbox" :checked="!!controlValue(selectedNode.node, 'content', control)" @change="setControlValue(selectedNode.node, 'content', control, $event.target.checked)" />
                                                Actif
                                            </label>
                                            <div v-else-if="control.type === 'repeater'" class="space-y-2">
                                                <div v-for="(item, index) in controlItems(selectedNode.node, 'content', control)" :key="index" class="border border-outline-variant rounded-lg p-2 space-y-1">
                                                    <input v-for="field in control.fields" :key="field" v-model="item[field]" class="ctrl-input" :placeholder="field" @input="updateSelected" />
                                                    <button class="text-[10px] text-error" type="button" @click="removeControlItem(selectedNode.node, 'content', control, index)">Supprimer</button>
                                                </div>
                                                <button class="w-full border border-dashed border-outline-variant rounded-lg py-1.5 text-[11px] font-semibold text-primary" type="button" @click="addControlItem(selectedNode.node, 'content', control)">Ajouter</button>
                                            </div>
                                            <input v-else :type="control.type === 'number' ? 'number' : 'text'" :value="controlValue(selectedNode.node, 'content', control)" class="ctrl-input" @input="setControlValue(selectedNode.node, 'content', control, control.type === 'number' ? Number($event.target.value) : $event.target.value)" />
                                            <button v-if="['src', 'backgroundImage'].includes(control.key)" class="w-full border border-dashed border-outline-variant rounded-lg py-1.5 text-[11px] font-semibold text-primary" type="button" @click="openMediaPicker('content', control.key)">
                                                Choisir depuis la médiathèque
                                            </button>
                                        </template>
                                    </div>
                                </template>

                                <template v-if="elementorPanelTab === 'style'">
                                    <div class="rounded-lg border border-outline-variant p-3 space-y-3">
                                        <p class="text-[11px] font-bold uppercase tracking-wider text-primary">Style</p>
                                        <template v-for="control in elementorSchema(selectedNode.node).style" :key="control.key">
                                            <label class="ctrl-label">{{ control.label }}</label>
                                            <input v-if="control.type === 'color'" type="color" :value="controlValue(selectedNode.node, 'style', control) || '#000000'" class="ctrl-input" @input="setControlValue(selectedNode.node, 'style', control, $event.target.value)" />
                                            <div v-else-if="control.type === 'responsive-align'" class="grid grid-cols-3 gap-2">
                                                <select :value="selectedNode.node.style.align" class="ctrl-select" @change="setControlValue(selectedNode.node, 'style', { key: 'align' }, $event.target.value)">
                                                    <option value="">Desk</option><option value="left">Gauche</option><option value="center">Centre</option><option value="right">Droite</option>
                                                </select>
                                                <select :value="selectedNode.node.style.alignTablet" class="ctrl-select" @change="setControlValue(selectedNode.node, 'style', { key: 'alignTablet' }, $event.target.value)">
                                                    <option value="">Tab</option><option value="left">Gauche</option><option value="center">Centre</option><option value="right">Droite</option>
                                                </select>
                                                <select :value="selectedNode.node.style.alignMobile" class="ctrl-select" @change="setControlValue(selectedNode.node, 'style', { key: 'alignMobile' }, $event.target.value)">
                                                    <option value="">Mob</option><option value="left">Gauche</option><option value="center">Centre</option><option value="right">Droite</option>
                                                </select>
                                            </div>
                                            <div v-else-if="control.type === 'typography'" class="space-y-2">
                                                <input v-model="selectedNode.node.style.fontFamily" class="ctrl-input" placeholder="Famille" @input="updateSelected" />
                                                <div class="grid grid-cols-3 gap-2">
                                                    <input v-model.number="selectedNode.node.style.fontSize" class="ctrl-input" type="number" placeholder="Desk" @input="updateSelected" />
                                                    <input v-model.number="selectedNode.node.style.fontSizeTablet" class="ctrl-input" type="number" placeholder="Tab" @input="updateSelected" />
                                                    <input v-model.number="selectedNode.node.style.fontSizeMobile" class="ctrl-input" type="number" placeholder="Mob" @input="updateSelected" />
                                                </div>
                                                <div class="grid grid-cols-3 gap-2">
                                                    <input v-model="selectedNode.node.style.fontWeight" class="ctrl-input" placeholder="Poids" @input="updateSelected" />
                                                    <input v-model.number="selectedNode.node.style.lineHeight" class="ctrl-input" type="number" step="0.1" placeholder="Line" @input="updateSelected" />
                                                    <input v-model.number="selectedNode.node.style.letterSpacing" class="ctrl-input" type="number" step="0.1" placeholder="Letter" @input="updateSelected" />
                                                </div>
                                                <div class="grid grid-cols-3 gap-2">
                                                    <input v-model="selectedNode.node.style.textTransform" class="ctrl-input" placeholder="Transform" @input="updateSelected" />
                                                    <input v-model="selectedNode.node.style.fontStyle" class="ctrl-input" placeholder="Style" @input="updateSelected" />
                                                    <input v-model="selectedNode.node.style.textDecoration" class="ctrl-input" placeholder="Decor" @input="updateSelected" />
                                                </div>
                                            </div>
                                            <div v-else-if="control.type === 'text-stroke'" class="grid grid-cols-2 gap-2">
                                                <input v-model.number="selectedNode.node.style.textStrokeWidth" class="ctrl-input" type="number" placeholder="Largeur" @input="updateSelected" />
                                                <input v-model="selectedNode.node.style.textStrokeColor" class="ctrl-input" type="color" @input="updateSelected" />
                                            </div>
                                            <div v-else-if="control.type === 'text-shadow'" class="space-y-2">
                                                <div class="grid grid-cols-3 gap-2">
                                                    <input v-model.number="selectedNode.node.style.textShadow.horizontal" class="ctrl-input" type="number" placeholder="H" @input="updateSelected" />
                                                    <input v-model.number="selectedNode.node.style.textShadow.vertical" class="ctrl-input" type="number" placeholder="V" @input="updateSelected" />
                                                    <input v-model.number="selectedNode.node.style.textShadow.blur" class="ctrl-input" type="number" placeholder="Blur" @input="updateSelected" />
                                                </div>
                                                <input v-model="selectedNode.node.style.textShadow.color" class="ctrl-input" type="text" placeholder="rgba(...)" @input="updateSelected" />
                                            </div>
                                            <select v-else-if="control.type === 'select'" :value="controlValue(selectedNode.node, 'style', control)" class="ctrl-select" @change="setControlValue(selectedNode.node, 'style', control, $event.target.value)">
                                                <option v-for="option in control.options" :key="option" :value="option">{{ option || 'Défaut' }}</option>
                                            </select>
                                            <input v-else :type="control.type === 'number' ? 'number' : 'text'" :value="controlValue(selectedNode.node, 'style', control)" class="ctrl-input" @input="setControlValue(selectedNode.node, 'style', control, control.type === 'number' ? Number($event.target.value) : $event.target.value)" />
                                        </template>
                                    </div>
                                </template>

                                <template v-if="elementorPanelTab === 'advanced'">
                                    <details v-for="section in ELEMENTOR_ADVANCED_SECTIONS" :key="section.key" class="border border-outline-variant rounded-lg" open>
                                        <summary class="px-3 py-2 cursor-pointer text-[11px] font-bold uppercase text-primary">{{ section.label }}</summary>
                                        <div class="p-3 space-y-2">
                                            <template v-for="control in section.controls" :key="control">
                                                <label class="ctrl-label">{{ control }}</label>
                                                <select v-if="control === 'animation'" v-model="selectedNode.node.advanced.animation" class="ctrl-select" @change="updateSelected">
                                                    <option v-for="(label, key) in ANIMATIONS" :key="key" :value="key">{{ label }}</option>
                                                </select>
                                                <div v-else-if="['hideDesktop', 'hideTablet', 'hideMobile'].includes(control)" class="text-[11px]">
                                                    <label class="inline-flex items-center gap-2"><input v-model="selectedNode.node.advanced[control]" type="checkbox" true-value="yes" false-value="" @change="updateSelected" /> Masquer</label>
                                                </div>
                                                <textarea v-else-if="control === 'customCss'" v-model="selectedNode.node.advanced[control]" rows="5" class="ctrl-area font-mono text-[11px]" @input="updateSelected" />
                                                <input v-else v-model="selectedNode.node.advanced[control]" class="ctrl-input" @input="updateSelected" />
                                            </template>
                                        </div>
                                    </details>
                                </template>
                            </template>

                            <template v-if="selectedNode.node.type === 'heading'">
                                <label class="ctrl-label">Texte</label>
                                <input v-model="selectedNode.node.content.text" class="ctrl-input" @input="updateSelected" />
                                <label class="ctrl-label">Balise</label>
                                <select v-model="selectedNode.node.content.tag" class="ctrl-select" @change="updateSelected">
                                    <option value="h1">H1</option>
                                    <option value="h2">H2</option>
                                    <option value="h3">H3</option>
                                    <option value="p">Paragraphe</option>
                                </select>
                                <label class="ctrl-label">Taille</label>
                                <select v-model="selectedNode.node.style.size" class="ctrl-select" @change="updateSelected">
                                    <option value="text-xl">XL</option>
                                    <option value="text-2xl">2XL</option>
                                    <option value="text-3xl">3XL</option>
                                    <option value="text-4xl">4XL</option>
                                    <option value="text-5xl">5XL</option>
                                </select>
                            </template>

                            <template v-if="selectedNode.node.type === 'text'">
                                <label class="ctrl-label">Contenu</label>
                                <textarea v-model="selectedNode.node.content.html" rows="6" class="ctrl-area" @input="updateSelected" />
                            </template>

                            <template v-if="selectedNode.node.type === 'button'">
                                <label class="ctrl-label">Libellé</label>
                                <input v-model="selectedNode.node.content.text" class="ctrl-input" @input="updateSelected" />
                                <label class="ctrl-label">Lien</label>
                                <input v-model="selectedNode.node.content.href" class="ctrl-input" @input="updateSelected" />
                                <label class="ctrl-label">Fond</label>
                                <select v-model="selectedNode.node.style.bgColor" class="ctrl-select" @change="updateSelected">
                                    <option v-for="(_, token) in PALETTE" :key="token" :value="token">{{ token }}</option>
                                </select>
                            </template>

                            <template v-if="selectedNode.node.type === 'image'">
                                <label class="ctrl-label">Source image</label>
                                <input v-model="selectedNode.node.content.src" class="ctrl-input" @input="updateSelected" />
                                <button class="w-full border border-dashed border-outline-variant rounded-lg py-1.5 text-[11px] font-semibold text-primary" type="button" @click="openMediaPicker('content', 'src')">
                                    Choisir depuis la médiathèque
                                </button>
                                <label class="ctrl-label">Alt</label>
                                <input v-model="selectedNode.node.content.alt" class="ctrl-input" @input="updateSelected" />
                            </template>

                            <template v-if="selectedNode.node.type === 'iconbox'">
                                <label class="ctrl-label">Icône</label>
                                <CamaIconPicker v-model="selectedNode.node.content.icon" label="" @update:modelValue="updateSelected" />
                                <label class="ctrl-label">Titre</label>
                                <input v-model="selectedNode.node.content.title" class="ctrl-input" @input="updateSelected" />
                                <label class="ctrl-label">Texte</label>
                                <textarea v-model="selectedNode.node.content.text" rows="3" class="ctrl-area" @input="updateSelected" />
                            </template>

                            <template v-if="selectedNode.node.type === 'cards'">
                                <label class="ctrl-label">Cartes</label>
                                <div v-for="(item, index) in selectedNode.node.content.items" :key="index" class="border border-outline-variant rounded-lg p-2 mb-2">
                                    <CamaIconPicker v-model="item.icon" label="" class="mb-1" @update:modelValue="updateSelected" />
                                    <input v-model="item.title" class="ctrl-input mb-1" placeholder="Titre" @input="updateSelected" />
                                    <textarea v-model="item.text" class="ctrl-area" rows="2" placeholder="Texte" @input="updateSelected" />
                                    <button class="text-[10px] text-error mt-1" type="button" @click="removeRepeaterItem('items', index)">Supprimer</button>
                                </div>
                                <button class="w-full border border-dashed border-outline-variant rounded-lg py-1.5 text-[11px] font-semibold text-primary" type="button" @click="addRepeaterItem('items', { icon: 'shield', title: 'Nouvelle carte', text: 'Texte de la carte.' })">Ajouter une carte</button>
                            </template>

                            <template v-if="selectedNode.node.type === 'stats'">
                                <label class="ctrl-label">Statistiques</label>
                                <div v-for="(item, index) in selectedNode.node.content.items" :key="index" class="border border-outline-variant rounded-lg p-2 mb-2">
                                    <input v-model="item.value" class="ctrl-input mb-1" placeholder="Valeur" @input="updateSelected" />
                                    <input v-model="item.label" class="ctrl-input" placeholder="Libellé" @input="updateSelected" />
                                    <button class="text-[10px] text-error mt-1" type="button" @click="removeRepeaterItem('items', index)">Supprimer</button>
                                </div>
                                <button class="w-full border border-dashed border-outline-variant rounded-lg py-1.5 text-[11px] font-semibold text-primary" type="button" @click="addRepeaterItem('items', { value: '100', label: 'Nouvelle statistique' })">Ajouter</button>
                            </template>

                            <template v-if="selectedNode.node.type === 'accordion'">
                                <label class="ctrl-label">Questions</label>
                                <div v-for="(item, index) in selectedNode.node.content.items" :key="index" class="border border-outline-variant rounded-lg p-2 mb-2">
                                    <input v-model="item.question" class="ctrl-input mb-1" placeholder="Question" @input="updateSelected" />
                                    <textarea v-model="item.answer" class="ctrl-area" rows="2" placeholder="Réponse" @input="updateSelected" />
                                    <button class="text-[10px] text-error mt-1" type="button" @click="removeRepeaterItem('items', index)">Supprimer</button>
                                </div>
                                <button class="w-full border border-dashed border-outline-variant rounded-lg py-1.5 text-[11px] font-semibold text-primary" type="button" @click="addRepeaterItem('items', { question: 'Nouvelle question', answer: 'Réponse.' })">Ajouter</button>
                            </template>

                            <template v-if="selectedNode.node.type === 'cta'">
                                <label class="ctrl-label">Titre</label>
                                <input v-model="selectedNode.node.content.title" class="ctrl-input" @input="updateSelected" />
                                <label class="ctrl-label">Texte</label>
                                <textarea v-model="selectedNode.node.content.text" rows="3" class="ctrl-area" @input="updateSelected" />
                                <label class="ctrl-label">Bouton</label>
                                <input v-model="selectedNode.node.content.button" class="ctrl-input" @input="updateSelected" />
                                <label class="ctrl-label">Lien</label>
                                <input v-model="selectedNode.node.content.href" class="ctrl-input" @input="updateSelected" />
                            </template>

                            <template v-if="selectedNode.node.type === 'home_hero'">
                                <SourceModuleNote route-name="admin.cms.banniere" module-label="Bannière / Slides">
                                    Les images et titres des slides viennent du module <strong>CMS &gt; Bannière / Slides</strong>. Le bouton principal reprend le lien du slide s'il est renseigné, sinon celui défini ci-dessous.
                                </SourceModuleNote>
                                <label class="ctrl-label">Icône badge</label>
                                <CamaIconPicker v-model="selectedNode.node.content.badgeIcon" label="" @update:modelValue="updateSelected" />
                                <label class="ctrl-label">Texte badge</label>
                                <input v-model="selectedNode.node.content.badgeText" class="ctrl-input" @input="updateSelected" />
                                <label class="ctrl-label">Bouton principal (par défaut)</label>
                                <input v-model="selectedNode.node.content.primaryLabel" class="ctrl-input" placeholder="Ex. Commencer l'enrôlement" @input="updateSelected" />
                                <label class="ctrl-label">Lien principal (par défaut)</label>
                                <input v-model="selectedNode.node.content.primaryHref" class="ctrl-input" placeholder="/inscription-assure" @input="updateSelected" />
                                <label class="ctrl-label">Bouton secondaire <span class="text-on-surface-variant/60 normal-case">(vide = masqué)</span></label>
                                <input v-model="selectedNode.node.content.secondaryLabel" class="ctrl-input" placeholder="Espace assuré" @input="updateSelected" />
                                <label class="ctrl-label">Lien secondaire</label>
                                <input v-model="selectedNode.node.content.secondaryHref" class="ctrl-input" placeholder="/espace-assure/connexion" @input="updateSelected" />
                            </template>

                            <template v-if="selectedNode.node.type === 'home_pillars'">
                                <label class="ctrl-label">Titre de section</label>
                                <input v-model="selectedNode.node.content.title" class="ctrl-input" @input="updateSelected" />
                                <label class="ctrl-label">Piliers</label>
                                <div v-for="(item, index) in selectedNode.node.content.items" :key="index" class="border border-outline-variant rounded-lg p-2 mb-2">
                                    <CamaIconPicker v-model="item.icon" label="" class="mb-1" @update:modelValue="updateSelected" />
                                    <input v-model="item.title" class="ctrl-input mb-1" placeholder="Titre" @input="updateSelected" />
                                    <textarea v-model="item.text" class="ctrl-area" rows="3" placeholder="Texte" @input="updateSelected" />
                                    <button class="text-[10px] text-error mt-1" type="button" @click="removeRepeaterItem('items', index)">Supprimer</button>
                                </div>
                                <button class="w-full border border-dashed border-outline-variant rounded-lg py-1.5 text-[11px] font-semibold text-primary" type="button" @click="addRepeaterItem('items', { icon: 'shield', title: 'Nouveau pilier', text: 'Texte du pilier.' })">Ajouter un pilier</button>
                            </template>

                            <template v-if="selectedNode.node.type === 'home_key_figures'">
                                <SourceModuleNote route-name="admin.cms.chiffres_cles" module-label="Chiffres clés">
                                    Les valeurs viennent du module <strong>CMS &gt; Chiffres clés</strong>.
                                </SourceModuleNote>
                                <label class="ctrl-label">Sur-titre</label>
                                <input v-model="selectedNode.node.content.eyebrow" class="ctrl-input" @input="updateSelected" />
                                <label class="ctrl-label">Titre</label>
                                <input v-model="selectedNode.node.content.title" class="ctrl-input" @input="updateSelected" />
                            </template>

                            <template v-if="selectedNode.node.type === 'home_latest_articles'">
                                <SourceModuleNote route-name="admin.cms.actualites" module-label="Actualités">
                                    Les articles viennent du module <strong>CMS &gt; Actualités</strong>.
                                </SourceModuleNote>
                                <label class="ctrl-label">Sur-titre</label>
                                <input v-model="selectedNode.node.content.eyebrow" class="ctrl-input" @input="updateSelected" />
                                <label class="ctrl-label">Titre</label>
                                <input v-model="selectedNode.node.content.title" class="ctrl-input" @input="updateSelected" />
                                <label class="ctrl-label">Libellé du lien</label>
                                <input v-model="selectedNode.node.content.linkLabel" class="ctrl-input" @input="updateSelected" />
                                <label class="ctrl-label">Lien</label>
                                <input v-model="selectedNode.node.content.linkHref" class="ctrl-input" @input="updateSelected" />
                            </template>

                            <template v-if="selectedNode.node.type === 'about_hero'">
                                <label class="ctrl-label">Badge</label>
                                <input v-model="selectedNode.node.content.badge" class="ctrl-input" @input="updateSelected" />
                                <label class="ctrl-label">Titre</label>
                                <textarea v-model="selectedNode.node.content.title" rows="2" class="ctrl-area" @input="updateSelected" />
                                <label class="ctrl-label">Texte</label>
                                <textarea v-model="selectedNode.node.content.text" rows="4" class="ctrl-area" @input="updateSelected" />
                            </template>

                            <template v-if="selectedNode.node.type === 'about_director'">
                                <label class="ctrl-label">Sur-titre</label>
                                <input v-model="selectedNode.node.content.eyebrow" class="ctrl-input" @input="updateSelected" />
                                <label class="ctrl-label">Titre</label>
                                <input v-model="selectedNode.node.content.title" class="ctrl-input" @input="updateSelected" />
                                <label class="ctrl-label">Image</label>
                                <input v-model="selectedNode.node.content.imageSrc" class="ctrl-input" @input="updateSelected" />
                                <label class="ctrl-label">Badge directeur</label>
                                <input v-model="selectedNode.node.content.badge" class="ctrl-input" @input="updateSelected" />
                                <label class="ctrl-label">Nom</label>
                                <input v-model="selectedNode.node.content.name" class="ctrl-input" @input="updateSelected" />
                                <label class="ctrl-label">Fonction</label>
                                <input v-model="selectedNode.node.content.role" class="ctrl-input" @input="updateSelected" />
                                <label class="ctrl-label">Citation</label>
                                <textarea v-model="selectedNode.node.content.quote" rows="3" class="ctrl-area" @input="updateSelected" />
                                <label class="ctrl-label">Texte</label>
                                <textarea v-model="selectedNode.node.content.text" rows="4" class="ctrl-area" @input="updateSelected" />
                                <label class="ctrl-label">Lien</label>
                                <input v-model="selectedNode.node.content.linkLabel" class="ctrl-input mb-1" @input="updateSelected" />
                                <input v-model="selectedNode.node.content.linkHref" class="ctrl-input" @input="updateSelected" />
                                <label class="ctrl-label">Statistiques</label>
                                <div v-for="(item, index) in selectedNode.node.content.stats" :key="index" class="border border-outline-variant rounded-lg p-2 mb-2">
                                    <CamaIconPicker v-model="item.icon" label="" class="mb-1" @update:modelValue="updateSelected" />
                                    <input v-model="item.value" class="ctrl-input mb-1" placeholder="Valeur" @input="updateSelected" />
                                    <input v-model="item.label" class="ctrl-input mb-1" placeholder="Libellé" @input="updateSelected" />
                                </div>
                            </template>

                            <template v-if="selectedNode.node.type === 'about_timeline'">
                                <label class="ctrl-label">Sur-titre</label>
                                <input v-model="selectedNode.node.content.eyebrow" class="ctrl-input" @input="updateSelected" />
                                <label class="ctrl-label">Titre</label>
                                <input v-model="selectedNode.node.content.title" class="ctrl-input" @input="updateSelected" />
                                <label class="ctrl-label">Texte</label>
                                <textarea v-model="selectedNode.node.content.text" rows="2" class="ctrl-area" @input="updateSelected" />
                                <label class="ctrl-label">Étapes historiques</label>
                                <div v-for="(item, index) in selectedNode.node.content.items" :key="index" class="border border-outline-variant rounded-lg p-2 mb-2">
                                    <CamaIconPicker v-model="item.icon" label="" class="mb-1" @update:modelValue="updateSelected" />
                                    <input v-model="item.date" class="ctrl-input mb-1" placeholder="Date" @input="updateSelected" />
                                    <input v-model="item.title" class="ctrl-input mb-1" placeholder="Titre" @input="updateSelected" />
                                    <textarea v-model="item.text" class="ctrl-area mb-1" rows="2" placeholder="Texte" @input="updateSelected" />
                                </div>
                                <label class="ctrl-label">Bloc aujourd’hui</label>
                                <input v-model="selectedNode.node.content.todayBadge" class="ctrl-input mb-1" @input="updateSelected" />
                                <input v-model="selectedNode.node.content.todayTitle" class="ctrl-input mb-1" @input="updateSelected" />
                                <textarea v-model="selectedNode.node.content.todayText" class="ctrl-area" rows="2" @input="updateSelected" />
                            </template>

                            <template v-if="selectedNode.node.type === 'about_missions'">
                                <label class="ctrl-label">Titre</label>
                                <input v-model="selectedNode.node.content.title" class="ctrl-input" @input="updateSelected" />
                                <label class="ctrl-label">Texte</label>
                                <textarea v-model="selectedNode.node.content.text" rows="2" class="ctrl-area" @input="updateSelected" />
                                <label class="ctrl-label">Lien</label>
                                <input v-model="selectedNode.node.content.linkLabel" class="ctrl-input mb-1" @input="updateSelected" />
                                <input v-model="selectedNode.node.content.linkHref" class="ctrl-input" @input="updateSelected" />
                                <label class="ctrl-label">Missions</label>
                                <div v-for="(item, index) in selectedNode.node.content.items" :key="index" class="border border-outline-variant rounded-lg p-2 mb-2">
                                    <CamaIconPicker v-model="item.icon" label="" class="mb-1" @update:modelValue="updateSelected" />
                                    <input v-model="item.title" class="ctrl-input mb-1" placeholder="Titre" @input="updateSelected" />
                                    <textarea :value="(item.items || []).join('\n')" class="ctrl-area" rows="4" placeholder="Une ligne par mission" @input="item.items = $event.target.value.split('\n'); updateSelected()" />
                                </div>
                            </template>

                            <template v-if="selectedNode.node.type === 'about_cta'">
                                <label class="ctrl-label">Titre</label>
                                <input v-model="selectedNode.node.content.title" class="ctrl-input" @input="updateSelected" />
                                <label class="ctrl-label">Texte</label>
                                <textarea v-model="selectedNode.node.content.text" rows="2" class="ctrl-area" @input="updateSelected" />
                                <label class="ctrl-label">Bouton principal</label>
                                <input v-model="selectedNode.node.content.primaryLabel" class="ctrl-input mb-1" @input="updateSelected" />
                                <input v-model="selectedNode.node.content.primaryHref" class="ctrl-input" @input="updateSelected" />
                                <label class="ctrl-label">Bouton secondaire</label>
                                <input v-model="selectedNode.node.content.secondaryLabel" class="ctrl-input mb-1" @input="updateSelected" />
                                <input v-model="selectedNode.node.content.secondaryHref" class="ctrl-input" @input="updateSelected" />
                            </template>

                            <template v-if="['resources_hero', 'news_hero', 'contact_hero'].includes(selectedNode.node.type)">
                                <label v-if="selectedNode.node.content.eyebrow !== undefined" class="ctrl-label">Sur-titre</label>
                                <input v-if="selectedNode.node.content.eyebrow !== undefined" v-model="selectedNode.node.content.eyebrow" class="ctrl-input" @input="updateSelected" />
                                <label v-if="selectedNode.node.content.badge !== undefined" class="ctrl-label">Badge</label>
                                <input v-if="selectedNode.node.content.badge !== undefined" v-model="selectedNode.node.content.badge" class="ctrl-input" @input="updateSelected" />
                                <label class="ctrl-label">Titre</label>
                                <input v-model="selectedNode.node.content.title" class="ctrl-input" @input="updateSelected" />
                                <label class="ctrl-label">Texte</label>
                                <textarea v-model="selectedNode.node.content.text" rows="3" class="ctrl-area" @input="updateSelected" />
                            </template>

                            <template v-if="selectedNode.node.type === 'resources_listing'">
                                <SourceModuleNote route-name="admin.cms.ressources" module-label="Ressources">
                                    Les documents viennent du module <strong>CMS &gt; Ressources</strong>.
                                </SourceModuleNote>
                                <label class="ctrl-label">Libellé téléchargement</label>
                                <input v-model="selectedNode.node.content.downloadLabel" class="ctrl-input" @input="updateSelected" />
                                <label class="ctrl-label">Texte vide</label>
                                <textarea v-model="selectedNode.node.content.emptyText" rows="2" class="ctrl-area" @input="updateSelected" />
                            </template>

                            <template v-if="selectedNode.node.type === 'news_filters'">
                                <SourceModuleNote route-name="admin.cms.actualites" module-label="Actualités">
                                    Les catégories viennent du module <strong>CMS &gt; Actualités</strong>.
                                </SourceModuleNote>
                                <label class="ctrl-label">Placeholder recherche</label>
                                <input v-model="selectedNode.node.content.searchPlaceholder" class="ctrl-input" @input="updateSelected" />
                            </template>

                            <template v-if="selectedNode.node.type === 'news_listing'">
                                <SourceModuleNote route-name="admin.cms.actualites" module-label="Actualités">
                                    Les articles viennent du module <strong>CMS &gt; Actualités</strong>.
                                </SourceModuleNote>
                                <label class="ctrl-label">Libellé article principal</label>
                                <input v-model="selectedNode.node.content.readLabel" class="ctrl-input" @input="updateSelected" />
                                <label class="ctrl-label">Libellé cartes</label>
                                <input v-model="selectedNode.node.content.cardReadLabel" class="ctrl-input" @input="updateSelected" />
                                <label class="ctrl-label">Texte vide</label>
                                <textarea v-model="selectedNode.node.content.emptyText" rows="2" class="ctrl-area" @input="updateSelected" />
                            </template>

                            <template v-if="selectedNode.node.type === 'news_newsletter'">
                                <label class="ctrl-label">Titre</label>
                                <input v-model="selectedNode.node.content.title" class="ctrl-input" @input="updateSelected" />
                                <label class="ctrl-label">Texte</label>
                                <textarea v-model="selectedNode.node.content.text" rows="2" class="ctrl-area" @input="updateSelected" />
                                <label class="ctrl-label">Placeholder</label>
                                <input v-model="selectedNode.node.content.placeholder" class="ctrl-input" @input="updateSelected" />
                                <label class="ctrl-label">Bouton</label>
                                <input v-model="selectedNode.node.content.buttonLabel" class="ctrl-input" @input="updateSelected" />
                            </template>

                            <template v-if="selectedNode.node.type === 'contact_form'">
                                <label class="ctrl-label">Titre</label>
                                <input v-model="selectedNode.node.content.title" class="ctrl-input" @input="updateSelected" />
                                <label class="ctrl-label">Texte</label>
                                <textarea v-model="selectedNode.node.content.text" rows="2" class="ctrl-area" @input="updateSelected" />
                                <label class="ctrl-label">Bouton</label>
                                <input v-model="selectedNode.node.content.buttonLabel" class="ctrl-input" @input="updateSelected" />
                                <label class="ctrl-label">Succès</label>
                                <input v-model="selectedNode.node.content.successLabel" class="ctrl-input" @input="updateSelected" />
                            </template>

                            <template v-if="selectedNode.node.type === 'contact_map'">
                                <SourceModuleNote route-name="admin.cms.partenaires" module-label="Partenaires & centres">
                                    Les points viennent des antennes et du module <strong>CMS &gt; Partenaires & centres</strong>.
                                </SourceModuleNote>
                                <label class="ctrl-label">Titre</label>
                                <input v-model="selectedNode.node.content.title" class="ctrl-input" @input="updateSelected" />
                                <label class="ctrl-label">Texte</label>
                                <textarea v-model="selectedNode.node.content.text" rows="2" class="ctrl-area" @input="updateSelected" />
                                <label class="ctrl-label">Recherche</label>
                                <input v-model="selectedNode.node.content.searchPlaceholder" class="ctrl-input" @input="updateSelected" />
                                <label class="ctrl-label">Libellé itinéraire</label>
                                <input v-model="selectedNode.node.content.directionsLabel" class="ctrl-input" @input="updateSelected" />
                            </template>

                            <template v-if="selectedNode.node.type === 'services_hero'">
                                <label class="ctrl-label">Badge</label>
                                <input v-model="selectedNode.node.content.badge" class="ctrl-input" @input="updateSelected" />
                                <label class="ctrl-label">Titre</label>
                                <textarea v-model="selectedNode.node.content.title" rows="2" class="ctrl-area" @input="updateSelected" />
                                <label class="ctrl-label">Texte</label>
                                <textarea v-model="selectedNode.node.content.text" rows="3" class="ctrl-area" @input="updateSelected" />
                                <label class="ctrl-label">Bouton principal</label>
                                <input v-model="selectedNode.node.content.primaryLabel" class="ctrl-input mb-1" @input="updateSelected" />
                                <input v-model="selectedNode.node.content.primaryHref" class="ctrl-input" @input="updateSelected" />
                                <label class="ctrl-label">Bouton secondaire</label>
                                <input v-model="selectedNode.node.content.secondaryLabel" class="ctrl-input mb-1" @input="updateSelected" />
                                <input v-model="selectedNode.node.content.secondaryHref" class="ctrl-input" @input="updateSelected" />
                            </template>

                            <template v-if="selectedNode.node.type === 'services_stats'">
                                <label class="ctrl-label">Statistiques services</label>
                                <div v-for="(item, index) in selectedNode.node.content.items" :key="index" class="border border-outline-variant rounded-lg p-2 mb-2">
                                    <CamaIconPicker v-model="item.icon" label="" class="mb-1" @update:modelValue="updateSelected" />
                                    <input v-model="item.value" class="ctrl-input mb-1" placeholder="Valeur" @input="updateSelected" />
                                    <input v-model="item.label" class="ctrl-input mb-1" placeholder="Libellé" @input="updateSelected" />
                                    <select v-model="item.tone" class="ctrl-select" @change="updateSelected">
                                        <option value="primary">Rouge</option>
                                        <option value="secondary">Vert</option>
                                        <option value="tertiary">Or</option>
                                        <option value="dark">Noir</option>
                                    </select>
                                    <button class="text-[10px] text-error mt-1" type="button" @click="removeRepeaterItem('items', index)">Supprimer</button>
                                </div>
                                <button class="w-full border border-dashed border-outline-variant rounded-lg py-1.5 text-[11px] font-semibold text-primary" type="button" @click="addRepeaterItem('items', { icon: 'monitoring', value: '100', label: 'Nouvelle statistique', tone: 'primary' })">Ajouter une statistique</button>
                            </template>

                            <template v-if="selectedNode.node.type === 'services_coverage' || selectedNode.node.type === 'services_steps'">
                                <label class="ctrl-label">Sur-titre</label>
                                <input v-model="selectedNode.node.content.eyebrow" class="ctrl-input" @input="updateSelected" />
                                <label class="ctrl-label">Titre</label>
                                <input v-model="selectedNode.node.content.title" class="ctrl-input" @input="updateSelected" />
                                <label class="ctrl-label">{{ selectedNode.node.type === 'services_coverage' ? 'Garanties' : 'Étapes' }}</label>
                                <div v-for="(item, index) in selectedNode.node.content.items" :key="index" class="border border-outline-variant rounded-lg p-2 mb-2">
                                    <CamaIconPicker v-model="item.icon" label="" class="mb-1" @update:modelValue="updateSelected" />
                                    <input v-model="item.title" class="ctrl-input mb-1" placeholder="Titre" @input="updateSelected" />
                                    <textarea v-model="item.text" class="ctrl-area mb-1" rows="2" placeholder="Texte" @input="updateSelected" />
                                    <input v-if="selectedNode.node.type === 'services_coverage'" v-model="item.rate" class="ctrl-input mb-1" placeholder="Taux" @input="updateSelected" />
                                    <select v-model="item.tone" class="ctrl-select" @change="updateSelected">
                                        <option value="primary">Rouge</option>
                                        <option value="secondary">Vert</option>
                                        <option value="tertiary">Or</option>
                                        <option value="dark">Noir</option>
                                    </select>
                                    <button class="text-[10px] text-error mt-1" type="button" @click="removeRepeaterItem('items', index)">Supprimer</button>
                                </div>
                                <button v-if="selectedNode.node.type === 'services_coverage'" class="w-full border border-dashed border-outline-variant rounded-lg py-1.5 text-[11px] font-semibold text-primary" type="button" @click="addRepeaterItem('items', { icon: 'medical_services', title: 'Nouvelle garantie', text: 'Description de la garantie.', rate: '80%', tone: 'primary' })">Ajouter une garantie</button>
                                <button v-else class="w-full border border-dashed border-outline-variant rounded-lg py-1.5 text-[11px] font-semibold text-primary" type="button" @click="addRepeaterItem('items', { icon: 'check_circle', title: 'Nouvelle étape', text: 'Description de l’étape.', tone: 'primary' })">Ajouter une étape</button>
                            </template>

                            <template v-if="selectedNode.node.type === 'services_partners'">
                                <SourceModuleNote route-name="admin.cms.partenaires" module-label="Partenaires & centres">
                                    Les cartes viennent du module <strong>CMS &gt; Partenaires & centres</strong>.
                                </SourceModuleNote>
                                <label class="ctrl-label">Sur-titre</label>
                                <input v-model="selectedNode.node.content.eyebrow" class="ctrl-input" @input="updateSelected" />
                                <label class="ctrl-label">Titre</label>
                                <input v-model="selectedNode.node.content.title" class="ctrl-input" @input="updateSelected" />
                                <label class="ctrl-label">Texte</label>
                                <textarea v-model="selectedNode.node.content.text" rows="3" class="ctrl-area" @input="updateSelected" />
                            </template>

                            <template v-if="selectedNode.node.type === 'services_faq'">
                                <SourceModuleNote route-name="admin.cms.faq" module-label="FAQ">
                                    Les questions viennent du module <strong>CMS &gt; FAQ</strong>.
                                </SourceModuleNote>
                                <label class="ctrl-label">Sur-titre</label>
                                <input v-model="selectedNode.node.content.eyebrow" class="ctrl-input" @input="updateSelected" />
                                <label class="ctrl-label">Titre</label>
                                <input v-model="selectedNode.node.content.title" class="ctrl-input" @input="updateSelected" />
                                <label class="ctrl-label">Texte</label>
                                <textarea v-model="selectedNode.node.content.text" rows="3" class="ctrl-area" @input="updateSelected" />
                                <label class="ctrl-label">Carte contact</label>
                                <input v-model="selectedNode.node.content.cardTitle" class="ctrl-input mb-1" @input="updateSelected" />
                                <textarea v-model="selectedNode.node.content.cardText" rows="2" class="ctrl-area mb-1" @input="updateSelected" />
                                <input v-model="selectedNode.node.content.linkLabel" class="ctrl-input mb-1" @input="updateSelected" />
                                <input v-model="selectedNode.node.content.linkHref" class="ctrl-input" @input="updateSelected" />
                            </template>

                            <template v-if="selectedNode.node.type === 'services_cta'">
                                <label class="ctrl-label">Titre</label>
                                <textarea v-model="selectedNode.node.content.title" rows="2" class="ctrl-area" @input="updateSelected" />
                                <label class="ctrl-label">Texte</label>
                                <textarea v-model="selectedNode.node.content.text" rows="3" class="ctrl-area" @input="updateSelected" />
                                <label class="ctrl-label">Bouton principal</label>
                                <input v-model="selectedNode.node.content.primaryLabel" class="ctrl-input mb-1" @input="updateSelected" />
                                <input v-model="selectedNode.node.content.primaryHref" class="ctrl-input" @input="updateSelected" />
                                <label class="ctrl-label">Bouton secondaire</label>
                                <input v-model="selectedNode.node.content.secondaryLabel" class="ctrl-input mb-1" @input="updateSelected" />
                                <input v-model="selectedNode.node.content.secondaryHref" class="ctrl-input" @input="updateSelected" />
                            </template>

                            <template v-if="selectedNode.node.type === 'html'">
                                <label class="ctrl-label">Code HTML</label>
                                <textarea v-model="selectedNode.node.content.html" rows="12" class="ctrl-area font-mono text-[11px]" @input="updateSelected" />
                            </template>
                        </template>
                    </div>
                </div>
            </aside>
        </div>
    </div>

    <div v-if="sectionLibraryOpen" class="fixed inset-0 bg-black/60 z-[80] flex items-center justify-center p-4 md:p-6">
        <div class="bg-white rounded-xl w-full max-w-4xl max-h-[90vh] flex flex-col overflow-hidden shadow-2xl">
            <div class="px-4 py-3 border-b border-outline-variant flex items-center justify-between shrink-0">
                <span class="text-sm font-bold flex items-center gap-2">
                    <span class="material-symbols-outlined text-[20px] text-primary">dashboard</span>
                    Bibliothèque de sections
                </span>
                <button class="text-on-surface-variant hover:text-primary" type="button" @click="sectionLibraryOpen = false"><span class="material-symbols-outlined">close</span></button>
            </div>
            <div class="px-4 py-2 border-b border-outline-variant flex flex-wrap gap-1.5 shrink-0">
                <button
                    v-for="cat in ['Tous', ...SECTION_LIBRARY_CATEGORIES]"
                    :key="cat"
                    type="button"
                    class="px-2.5 py-1 rounded-full text-[11px] font-bold border transition-colors"
                    :class="sectionLibraryCategory === cat ? 'border-primary bg-primary/10 text-primary' : 'border-outline-variant text-on-surface-variant'"
                    @click="sectionLibraryCategory = cat"
                >
                    {{ cat }}
                </button>
            </div>
            <div class="flex-1 overflow-y-auto p-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                <button
                    v-for="template in filteredSectionLibrary"
                    :key="template.id"
                    type="button"
                    class="text-left border border-outline-variant rounded-xl p-4 hover:border-primary hover:shadow-md hover:bg-primary/5 transition-all"
                    @click="insertSectionTemplate(template)"
                >
                    <div class="w-10 h-10 rounded-lg bg-primary/10 text-primary flex items-center justify-center mb-2">
                        <span class="material-symbols-outlined">{{ template.icon }}</span>
                    </div>
                    <p class="text-sm font-bold text-on-surface">{{ template.name }}</p>
                    <p class="text-[11px] text-on-surface-variant mt-1">{{ template.description }}</p>
                    <p class="text-[10px] text-primary font-bold mt-2">Insérer en un clic</p>
                </button>
            </div>
        </div>
    </div>

    <div v-if="structureOpen" class="fixed inset-0 z-[75] flex items-center justify-center bg-black/30">
        <div class="bg-white rounded-xl p-5 shadow-2xl w-full max-w-md">
            <p class="text-sm font-bold mb-3">Choisissez votre structure</p>
            <div class="grid grid-cols-3 gap-3">
                <button v-for="widths in [[100], [50, 50], [33, 33, 34], [33, 67], [67, 33], [25, 25, 25, 25]]" :key="widths.join('-')" class="border border-outline-variant rounded-lg p-2 hover:border-primary" type="button" @click="addSection(widths)">
                    <div class="flex gap-1 h-10">
                        <div v-for="width in widths" :key="width" class="bg-surface-container-highest rounded" :style="{ flex: `0 0 ${width}%` }" />
                    </div>
                </button>
            </div>
            <button class="mt-4 w-full text-xs text-on-surface-variant hover:text-primary" type="button" @click="structureOpen = false">Annuler</button>
        </div>
    </div>

    <div v-if="previewOpen" class="fixed inset-0 bg-black/60 z-[80] flex flex-col">
        <div class="h-12 bg-white flex items-center justify-between px-4 shrink-0 border-b border-outline-variant">
            <span class="text-xs font-bold truncate">Aperçu — {{ currentPageOption.title }}</span>
            <div class="flex items-center gap-3">
                <div class="flex items-center gap-1 bg-surface-container-low rounded-lg p-1">
                    <button
                        v-for="(preset, key) in DEVICE_PRESETS"
                        :key="key"
                        class="device-btn px-2 py-1 rounded-md"
                        :class="{ 'is-active': previewDevice === key }"
                        type="button"
                        :title="preset.label"
                        @click="previewDevice = key"
                    >
                        <span class="material-symbols-outlined text-[18px]">{{ preset.icon }}</span>
                    </button>
                </div>
                <span class="text-[10px] text-on-surface-variant hidden sm:inline tabular-nums">{{ previewFrame.dimensions }}</span>
                <button class="px-3 py-1.5 rounded-lg text-xs font-bold border border-outline-variant" type="button" @click="previewOpen = false">Fermer</button>
            </div>
        </div>
        <div class="flex-1 overflow-auto flex justify-center items-start p-4">
            <iframe
                class="bg-white shadow-2xl transition-all duration-300 rounded-lg"
                :class="previewFrame.framed ? 'border-[10px] border-on-background/80' : ''"
                :style="previewFrame.style"
                :srcdoc="previewHtml"
                title="Aperçu responsive"
            />
        </div>
    </div>

    <div v-if="publicPreviewOpen" class="fixed inset-0 bg-black/70 z-[85] flex flex-col">
        <div class="h-14 bg-white flex items-center justify-between px-4 shrink-0 border-b border-outline-variant">
            <div class="min-w-0">
                <p class="text-xs font-bold truncate">Site public — {{ currentPageOption.title }}</p>
                <p class="text-[10px] text-on-surface-variant truncate">Aperçu réel : {{ publicPreviewUrl }}</p>
            </div>
            <div class="flex items-center gap-2">
                <a class="px-3 py-1.5 rounded-lg text-xs font-bold border border-outline-variant flex items-center gap-1" :href="publicPreviewUrl" target="_blank" rel="noopener">
                    <span class="material-symbols-outlined text-[16px]">open_in_new</span>
                    Ouvrir
                </a>
                <button class="px-3 py-1.5 rounded-lg text-xs font-bold border border-outline-variant" type="button" @click="publicPreviewOpen = false">Fermer</button>
            </div>
        </div>
        <iframe
            :key="publicPreviewKey"
            class="flex-1 w-full bg-white"
            :src="publicPreviewUrl"
            title="Aperçu du site public"
        />
    </div>

    <div v-if="mediaOpen" class="fixed inset-0 bg-black/60 z-[86] flex items-center justify-center p-6">
        <div class="bg-white rounded-xl w-full max-w-5xl max-h-[85vh] flex flex-col overflow-hidden shadow-2xl">
            <div class="px-4 py-3 border-b border-outline-variant flex items-center justify-between">
                <div>
                    <span class="text-sm font-bold flex items-center gap-2"><span class="material-symbols-outlined text-[18px] text-primary">perm_media</span>Médiathèque</span>
                    <p class="text-[10px] text-on-surface-variant">Choisissez une image pour le champ sélectionné, ou ouvrez le module pour importer de nouveaux fichiers.</p>
                </div>
                <div class="flex items-center gap-2">
                    <a class="px-3 py-1.5 rounded-lg text-xs font-bold border border-outline-variant" :href="route('admin.cms.media')" target="_blank" rel="noopener">Gérer</a>
                    <button class="text-on-surface-variant hover:text-primary" type="button" @click="mediaOpen = false"><span class="material-symbols-outlined">close</span></button>
                </div>
            </div>
            <div class="p-4 border-b border-outline-variant bg-surface-container-low">
                <div class="grid lg:grid-cols-[1.3fr_1fr_1fr_auto] gap-2 items-end">
                    <div>
                        <label class="text-[10px] uppercase font-bold text-on-surface-variant">Importer depuis l’éditeur</label>
                        <input class="mt-1 w-full text-xs border border-outline-variant rounded-lg px-3 py-2 bg-white" type="file" accept="image/*" @change="onBuilderMediaFileChange" />
                        <p v-if="builderMediaFileName" class="text-[10px] text-on-surface-variant mt-1">{{ builderMediaFileName }}</p>
                    </div>
                    <div>
                        <label class="text-[10px] uppercase font-bold text-on-surface-variant">Titre</label>
                        <input v-model="builderMediaForm.title" class="mt-1 w-full text-xs border border-outline-variant rounded-lg px-3 py-2 bg-white" placeholder="Titre" />
                    </div>
                    <div>
                        <label class="text-[10px] uppercase font-bold text-on-surface-variant">Alt</label>
                        <input v-model="builderMediaForm.alt_text" class="mt-1 w-full text-xs border border-outline-variant rounded-lg px-3 py-2 bg-white" placeholder="Texte alternatif" />
                    </div>
                    <button class="px-3 py-2 rounded-lg text-xs font-bold bg-primary text-on-primary disabled:opacity-50" type="button" :disabled="!builderMediaForm.file || builderMediaForm.processing" @click="uploadBuilderMedia">
                        {{ builderMediaForm.processing ? 'Import…' : 'Importer' }}
                    </button>
                </div>
                <div class="grid md:grid-cols-3 gap-2 mt-2">
                    <input v-model="builderMediaForm.folder" class="text-xs border border-outline-variant rounded-lg px-3 py-2 bg-white" placeholder="Dossier" />
                    <input v-model="builderMediaForm.category" class="text-xs border border-outline-variant rounded-lg px-3 py-2 bg-white" placeholder="Catégorie" />
                    <input v-model="builderMediaForm.tags" class="text-xs border border-outline-variant rounded-lg px-3 py-2 bg-white" placeholder="Tags séparés par virgule" />
                </div>
                <p v-if="builderMediaForm.errors.file" class="text-[10px] text-error font-semibold mt-1">{{ builderMediaForm.errors.file }}</p>
            </div>
            <div v-if="mediaItems.length" class="flex-1 overflow-auto p-4 grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <button
                    v-for="item in mediaItems"
                    :key="item.id"
                    class="text-left border border-outline-variant rounded-xl overflow-hidden hover:border-primary hover:shadow-md transition"
                    type="button"
                    @click="chooseMedia(item)"
                >
                    <img v-if="item.isImage" :src="item.url" :alt="item.originalName" class="h-36 w-full object-cover bg-surface-container-low" />
                    <div class="p-3">
                        <p class="text-xs font-bold text-on-surface truncate">{{ item.originalName }}</p>
                        <p class="text-[10px] text-on-surface-variant">{{ item.size }} · {{ item.width || '-' }}×{{ item.height || '-' }}</p>
                    </div>
                </button>
            </div>
            <div v-else class="p-10 text-center text-sm text-on-surface-variant">
                Aucun média image disponible. Ouvrez la médiathèque pour importer vos premiers visuels.
            </div>
        </div>
    </div>

    <VersionHistoryModal
        :open="versionOpen"
        :versions="versions"
        :permissions="cmsPermissions"
        @close="versionOpen = false"
        @preview="openVersionPreview"
        @compare="openVersionCompare"
        @duplicate="duplicateVersion"
        @restore="restoreVersion"
    />

    <div v-if="versionPreview" class="fixed inset-0 bg-black/70 z-[88] flex flex-col">
        <div class="h-14 bg-white flex items-center justify-between px-4 shrink-0 border-b border-outline-variant">
            <div>
                <p class="text-xs font-bold">Aperçu version {{ versionPreview.versionNumber }} — {{ versionPreview.title }}</p>
                <p class="text-[10px] text-on-surface-variant">{{ versionPreview.createdAt }} · {{ versionPreview.eventLabel }}</p>
            </div>
            <button class="px-3 py-1.5 rounded-lg text-xs font-bold border border-outline-variant" type="button" @click="versionPreview = null">Fermer</button>
        </div>
        <iframe class="flex-1 w-full bg-white" :srcdoc="fullHtml(versionPreview.sections ?? [])" title="Aperçu version" />
    </div>

    <div v-if="versionCompare" class="fixed inset-0 bg-black/60 z-[88] flex items-center justify-center p-6">
        <div class="bg-white rounded-xl w-full max-w-2xl overflow-hidden shadow-2xl">
            <div class="px-4 py-3 border-b border-outline-variant flex items-center justify-between">
                <div>
                    <p class="text-sm font-bold">Comparaison avec la version {{ versionCompare.versionNumber }}</p>
                    <p class="text-[10px] text-on-surface-variant">{{ versionCompare.createdAt }} · {{ versionCompare.eventLabel }}</p>
                </div>
                <button class="text-on-surface-variant hover:text-primary" type="button" @click="versionCompare = null"><span class="material-symbols-outlined">close</span></button>
            </div>
            <div class="p-5 space-y-4">
                <div class="grid grid-cols-2 gap-3">
                    <div class="rounded-xl border border-outline-variant p-4">
                        <p class="text-[10px] uppercase font-bold text-on-surface-variant">État actuel</p>
                        <p class="text-2xl font-bold text-on-surface">{{ versionDiff(versionCompare).currentSections }}</p>
                        <p class="text-xs text-on-surface-variant">sections · {{ versionDiff(versionCompare).currentWidgets }} widgets</p>
                    </div>
                    <div class="rounded-xl border border-outline-variant p-4">
                        <p class="text-[10px] uppercase font-bold text-on-surface-variant">Version {{ versionCompare.versionNumber }}</p>
                        <p class="text-2xl font-bold text-on-surface">{{ versionDiff(versionCompare).versionSections }}</p>
                        <p class="text-xs text-on-surface-variant">sections · {{ versionDiff(versionCompare).versionWidgets }} widgets</p>
                    </div>
                </div>
                <div class="grid md:grid-cols-2 gap-3">
                    <div class="rounded-xl bg-surface-container-low p-3">
                        <p class="text-xs font-bold text-on-surface mb-2">Types ajoutés depuis cette version</p>
                        <p class="text-xs text-on-surface-variant">{{ versionDiff(versionCompare).addedTypes.join(', ') || 'Aucun type nouveau' }}</p>
                    </div>
                    <div class="rounded-xl bg-surface-container-low p-3">
                        <p class="text-xs font-bold text-on-surface mb-2">Types présents dans la version mais absents maintenant</p>
                        <p class="text-xs text-on-surface-variant">{{ versionDiff(versionCompare).removedTypes.join(', ') || 'Aucun type retiré' }}</p>
                    </div>
                </div>
                <div class="flex justify-end gap-2">
                    <button class="px-3 py-1.5 rounded-lg text-xs font-bold border border-outline-variant" type="button" @click="openVersionPreview(versionCompare)">Voir l’aperçu</button>
                    <button v-if="cmsPermissions.canRestoreVersions" class="px-3 py-1.5 rounded-lg text-xs font-bold border border-primary text-primary" type="button" @click="restoreVersion(versionCompare)">Restaurer cette version</button>
                </div>
            </div>
        </div>
    </div>

    <div v-if="exportOpen" class="fixed inset-0 bg-black/60 z-[80] flex items-center justify-center p-6">
        <div class="bg-white rounded-xl w-full max-w-3xl max-h-[85vh] flex flex-col overflow-hidden shadow-2xl">
            <div class="px-4 py-3 border-b border-outline-variant flex items-center justify-between">
                <span class="text-sm font-bold flex items-center gap-2"><span class="material-symbols-outlined text-[18px] text-primary">code</span>Export HTML</span>
                <div class="flex items-center gap-2">
                    <button class="px-3 py-1.5 rounded-lg text-xs font-bold border border-outline-variant" type="button" @click="copyExport">Copier</button>
                    <button class="text-on-surface-variant hover:text-primary" type="button" @click="exportOpen = false"><span class="material-symbols-outlined">close</span></button>
                </div>
            </div>
            <pre class="flex-1 overflow-auto p-4 text-[11px] leading-relaxed bg-surface-container-low font-mono whitespace-pre-wrap">{{ previewHtml }}</pre>
        </div>
    </div>

    <div v-if="toast" class="fixed bottom-6 right-6 z-[90]">
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
.material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
.widget-card { cursor: grab; user-select: none; }
.widget-card:active { cursor: grabbing; }
.cv-section { outline: 1px dashed transparent; transition: outline-color .12s; }
.cv-section:hover { outline-color: #e5bdbb; }
.cv-section.is-selected { outline: 2px solid #9e001f; }
.cv-col { outline: 1px dashed transparent; min-height: 80px; transition: outline-color .12s; }
.cv-col:hover { outline-color: #ffb3b1; }
.cv-col.is-selected { outline: 2px solid #006e27; }
.cv-widget { outline: 2px solid transparent; transition: outline-color .12s; }
.cv-widget:hover { outline-color: #ffb3b1; }
.cv-widget.is-selected { outline: 2px solid #9e001f; }
.el-toolbar { display: none; position: absolute; z-index: 20; }
.cv-section.is-selected > .el-toolbar.sec-tb,
.cv-section:hover > .el-toolbar.sec-tb,
.cv-col.is-selected > .el-toolbar.col-tb,
.cv-col:hover > .el-toolbar.col-tb,
.cv-widget.is-selected > .el-toolbar.wid-tb,
.cv-widget:hover > .el-toolbar.wid-tb { display: flex; }
.el-label { display:none; position:absolute; top:0; left:0; z-index:19; font-size:9px; font-weight:700; letter-spacing:.04em; padding:1px 6px; border-bottom-right-radius:6px; text-transform:uppercase; }
.cv-section:hover > .el-label.sec-lb,
.cv-section.is-selected > .el-label.sec-lb,
.cv-col:hover > .el-label.col-lb,
.cv-col.is-selected > .el-label.col-lb { display:block; }
.drop-empty { border: 2px dashed #e5bdbb; border-radius: 8px; }
.ctrl-label { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:#5c403f; margin-bottom:4px; display:flex; align-items:center; justify-content:space-between; }
.ctrl-input, .ctrl-select, .ctrl-area { width:100%; padding:6px 8px; font-size:12px; border:1px solid #e5bdbb; border-radius:6px; background:#fff; }
.ctrl-input:focus, .ctrl-select:focus, .ctrl-area:focus { outline:none; border-color:#9e001f; }
.device-btn.is-active { background:#fff; box-shadow:0 1px 2px rgba(0,0,0,.12); }
.pb-tool-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 8px;
    color: #5c403f;
    transition: background-color 0.15s;
}
.pb-tool-btn:hover:not(:disabled) { background: #f0eded; color: #9e001f; }
.pb-tool-btn:disabled { opacity: 0.35; cursor: not-allowed; }
.pb-action-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 12px;
    border-radius: 8px;
    border: 1px solid #e5bdbb;
    font-size: 12px;
    font-weight: 700;
    color: #5c403f;
    background: #fff;
    white-space: nowrap;
}
.pb-action-btn:hover { border-color: #9e001f; color: #9e001f; }
.pb-menu-item {
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
    padding: 10px 14px;
    text-align: left;
    font-size: 13px;
    font-weight: 600;
    color: #1b1c1c;
}
.pb-menu-item:hover { background: #f6f3f2; color: #9e001f; }
.no-scrollbar::-webkit-scrollbar { display: none; }
.no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
</style>
