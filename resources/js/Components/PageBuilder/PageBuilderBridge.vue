<script setup>
// Monté uniquement dans l'iframe du page builder (prop Inertia « builderPreview »).
// Reçoit les sections de l'éditeur, et en mode édition gère la sélection, la barre
// d'outils et le dépôt de widgets directement sur le rendu réel de la page.
import { WIDGETS } from '@/Pages/Admin/Cms/PageBuilder/builderConstants';
import { usePage } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { PB_WIDGET_MIME, isBuilderMessage, pbPreview, postToBuilder } from './pageBuilderPreview';

const page = usePage();
const editing = computed(() => page.props.builderPreview?.mode === 'edit');
const toolbar = ref(null);
let dropTarget = null;
let resizeObserver = null;

const selectedNode = computed(() => findNode(pbPreview.selected));

const toolbarActions = computed(() => {
    const found = selectedNode.value;
    if (!found) return [];
    const hidden = found.kind === 'widget' ? found.node.style?.hidden : found.node.settings?.hidden;
    const visibility = { action: 'toggleHidden', icon: hidden ? 'visibility' : 'visibility_off', title: hidden ? 'Afficher' : 'Masquer' };
    const parent = { action: 'selectParent', icon: 'north_west', title: 'Sélectionner le parent' };

    if (found.kind === 'section') {
        return [
            { action: 'addColumn', icon: 'view_column', title: 'Ajouter une colonne' },
            { action: 'moveUp', icon: 'arrow_upward', title: 'Monter' },
            { action: 'moveDown', icon: 'arrow_downward', title: 'Descendre' },
            visibility,
            { action: 'duplicate', icon: 'content_copy', title: 'Dupliquer' },
            { action: 'delete', icon: 'delete', title: 'Supprimer' },
        ];
    }
    if (found.kind === 'column') {
        return [parent, visibility, { action: 'delete', icon: 'delete', title: 'Supprimer' }];
    }
    return [
        parent,
        visibility,
        { action: 'copyStyle', icon: 'format_paint', title: 'Copier le style' },
        { action: 'pasteStyle', icon: 'content_paste', title: 'Coller le style', disabled: !pbPreview.canPaste },
        { action: 'duplicate', icon: 'content_copy', title: 'Dupliquer' },
        { action: 'delete', icon: 'delete', title: 'Supprimer' },
    ];
});

const toolbarLabel = computed(() => {
    const found = selectedNode.value;
    if (!found) return '';
    if (found.kind === 'section') return 'Section';
    if (found.kind === 'column') return `Colonne ${found.node.width}%`;
    return WIDGETS[found.node.type]?.label ?? found.node.type;
});

function findNode(sel) {
    if (!sel) return null;
    for (const section of pbPreview.sections ?? []) {
        if (sel.kind === 'section' && section.uid === sel.uid) return { kind: 'section', node: section };
        for (const column of section.columns ?? []) {
            if (sel.kind === 'column' && column.uid === sel.uid) return { kind: 'column', node: column };
            for (const widget of column.widgets ?? []) {
                if (sel.kind === 'widget' && widget.uid === sel.uid) return { kind: 'widget', node: widget };
            }
        }
    }
    return null;
}

function selectedElement() {
    const sel = pbPreview.selected;
    if (!sel) return null;
    return document.querySelector(`[data-pb-${sel.kind}="${CSS.escape(sel.uid)}"]`);
}

function refreshSelection() {
    document.querySelectorAll('[data-pb-selected]').forEach((el) => el.removeAttribute('data-pb-selected'));
    const el = editing.value ? selectedElement() : null;
    if (!el) {
        toolbar.value = null;
        return;
    }
    el.setAttribute('data-pb-selected', '');
    const rect = el.getBoundingClientRect();
    toolbar.value = {
        top: Math.max(4, rect.top + 4),
        right: Math.max(4, window.innerWidth - rect.right + 4),
    };
}

function onMessage(event) {
    if (!isBuilderMessage(event) || event.source !== window.parent || event.data.type !== 'state') return;

    const first = pbPreview.sections === null;
    pbPreview.sections = event.data.sections ?? [];
    pbPreview.selected = event.data.selected ?? null;
    pbPreview.canPaste = !!event.data.canPaste;
    pbPreview.revision += 1;

    nextTick(() => {
        refreshSelection();
        if (first && event.data.scrollToSelected) selectedElement()?.scrollIntoView({ block: 'center' });
    });
}

function elementSelection(el) {
    if (el.hasAttribute('data-pb-widget')) return { kind: 'widget', uid: el.getAttribute('data-pb-widget') };
    if (el.hasAttribute('data-pb-column')) return { kind: 'column', uid: el.getAttribute('data-pb-column') };
    return { kind: 'section', uid: el.getAttribute('data-pb-section') };
}

function onClick(event) {
    if (event.target.closest('[data-pb-ui]')) return;

    const link = event.target.closest('a[href]');

    if (!editing.value) {
        // Aperçu : on reste sur la page (les ancres internes restent utilisables).
        if (link && !link.getAttribute('href').startsWith('#')) {
            event.preventDefault();
            event.stopPropagation();
        }
        return;
    }

    if (link) {
        event.preventDefault();
        event.stopPropagation();
    }

    const el = event.target.closest('[data-pb-widget], [data-pb-column], [data-pb-section]');
    if (el) {
        postToBuilder('select', elementSelection(el));
    } else {
        postToBuilder('deselect');
    }
}

function runAction(action) {
    const sel = pbPreview.selected;
    if (!sel) return;

    if (action === 'selectParent') {
        const el = selectedElement();
        if (!el) return;
        // Bloc système : le widget et sa section partagent le même élément.
        if (sel.kind === 'widget' && el.hasAttribute('data-pb-section')) {
            postToBuilder('select', { kind: 'section', uid: el.getAttribute('data-pb-section') });
            return;
        }
        const parent = el.parentElement?.closest('[data-pb-column], [data-pb-section]');
        if (parent) postToBuilder('select', elementSelection(parent));
        return;
    }

    postToBuilder('action', { action, kind: sel.kind, uid: sel.uid });
}

function onSubmit(event) {
    // Ni l'aperçu ni l'éditeur ne doivent envoyer de vrais formulaires (contact, newsletter…).
    event.preventDefault();
    event.stopPropagation();
}

function setDropTarget(el) {
    if (dropTarget === el) return;
    dropTarget?.removeAttribute('data-pb-dropping');
    dropTarget = el;
    dropTarget?.setAttribute('data-pb-dropping', '');
}

function onDragOver(event) {
    if (!editing.value || !event.dataTransfer?.types?.includes(PB_WIDGET_MIME)) return;
    const column = event.target.closest('[data-pb-column]');
    setDropTarget(column);
    if (column) {
        event.preventDefault();
        event.dataTransfer.dropEffect = 'copy';
    }
}

function onDrop(event) {
    const column = dropTarget;
    setDropTarget(null);
    if (!editing.value || !column) return;
    event.preventDefault();
    const widgetType = event.dataTransfer.getData(PB_WIDGET_MIME);
    if (widgetType) postToBuilder('drop', { columnUid: column.getAttribute('data-pb-column'), widgetType });
}

function onDragLeave(event) {
    if (!event.relatedTarget) setDropTarget(null);
}

watch(() => pbPreview.selected, () => nextTick(refreshSelection));

onMounted(() => {
    document.documentElement.classList.add(editing.value ? 'pb-editing' : 'pb-previewing');
    window.addEventListener('message', onMessage);
    document.addEventListener('click', onClick, true);
    document.addEventListener('submit', onSubmit, true);
    document.addEventListener('dragover', onDragOver);
    document.addEventListener('drop', onDrop);
    document.addEventListener('dragleave', onDragLeave);
    window.addEventListener('scroll', refreshSelection, { passive: true });
    window.addEventListener('resize', refreshSelection);
    resizeObserver = new ResizeObserver(() => refreshSelection());
    resizeObserver.observe(document.body);
    postToBuilder('ready');
});

onBeforeUnmount(() => {
    window.removeEventListener('message', onMessage);
    document.removeEventListener('click', onClick, true);
    document.removeEventListener('submit', onSubmit, true);
    document.removeEventListener('dragover', onDragOver);
    document.removeEventListener('drop', onDrop);
    document.removeEventListener('dragleave', onDragLeave);
    window.removeEventListener('scroll', refreshSelection);
    window.removeEventListener('resize', refreshSelection);
    resizeObserver?.disconnect();
});
</script>

<template>
    <Teleport to="body">
        <div
            v-if="editing && toolbar && toolbarActions.length"
            data-pb-ui
            class="pb-toolbar"
            :style="{ top: `${toolbar.top}px`, right: `${toolbar.right}px` }"
        >
            <span class="pb-toolbar-label">{{ toolbarLabel }}</span>
            <button
                v-for="item in toolbarActions"
                :key="item.action"
                type="button"
                :title="item.title"
                :disabled="item.disabled"
                @click.stop="runAction(item.action)"
            >
                <span class="material-symbols-outlined">{{ item.icon }}</span>
            </button>
        </div>
    </Teleport>
</template>

<style>
html.pb-editing [data-pb-section],
html.pb-editing [data-pb-column],
html.pb-editing [data-pb-widget] { cursor: pointer; }
html.pb-editing [data-pb-section]:hover { outline: 1px dashed rgb(var(--c-outline-variant)); outline-offset: -1px; }
html.pb-editing [data-pb-column]:hover { outline: 1px dashed rgb(var(--c-primary-fixed-dim)); outline-offset: -1px; }
html.pb-editing [data-pb-widget]:hover { outline: 2px solid rgb(var(--c-primary-fixed-dim)); outline-offset: -2px; }
html.pb-editing [data-pb-selected] { outline: 2px solid rgb(var(--c-primary)) !important; outline-offset: -2px; }
html.pb-editing [data-pb-column][data-pb-selected] { outline-color: rgb(var(--c-secondary)) !important; }
html.pb-editing [data-pb-dropping] { outline: 2px dashed rgb(var(--c-secondary)) !important; outline-offset: -2px; background-color: rgb(var(--c-secondary) / 0.06); }
html.pb-editing .cms-page-column { min-height: 64px; }

/* Éléments masqués pour un type d'écran : visibles mais estompés dans l'éditeur. */
@media (min-width: 1025px) { html.pb-editing .cms-hide-desktop { display: block !important; opacity: 0.4; } }
@media (min-width: 768px) and (max-width: 1024px) { html.pb-editing .cms-hide-tablet { display: block !important; opacity: 0.4; } }
@media (max-width: 767px) { html.pb-editing .cms-hide-mobile { display: block !important; opacity: 0.4; } }

/* La barre de cookies et le bouton « haut de page » gênent l'édition. */
html.pb-editing #site-cookie-bar,
html.pb-previewing #site-cookie-bar { display: none !important; }

.pb-toolbar {
    position: fixed;
    z-index: 2147483000;
    display: flex;
    align-items: center;
    overflow: hidden;
    border-radius: 8px;
    background: rgb(var(--c-primary));
    color: #fff;
    font: 600 11px/1 Inter, sans-serif;
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.25);
}
.pb-toolbar-label { padding: 0 10px; white-space: nowrap; }
.pb-toolbar button { display: inline-flex; padding: 6px; color: inherit; }
.pb-toolbar button:hover:not(:disabled) { background: rgba(0, 0, 0, 0.2); }
.pb-toolbar button:disabled { opacity: 0.35; cursor: not-allowed; }
.pb-toolbar .material-symbols-outlined { font-size: 16px; }
</style>
