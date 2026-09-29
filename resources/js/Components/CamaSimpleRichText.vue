<script setup>
import { nextTick, onMounted, ref, watch } from 'vue';

const props = defineProps({
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: 'Saisissez votre message…' },
    minHeight: { type: String, default: '88px' },
    allowImage: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const editorRef = ref(null);
const linkOpen = ref(false);
const linkUrl = ref('');
const imageOpen = ref(false);
const imageUrl = ref('');

function focusEditor() {
    editorRef.value?.focus();
}

function sync() {
    if (!editorRef.value) return;
    emit('update:modelValue', editorRef.value.innerHTML);
}

function exec(command, value = null) {
    focusEditor();
    document.execCommand(command, false, value);
    sync();
}

function changeSize(delta) {
    focusEditor();
    const selection = window.getSelection();

    if (!selection?.rangeCount || selection.isCollapsed) {
        return;
    }

    const range = selection.getRangeAt(0);
    const span = document.createElement('span');
    span.appendChild(range.extractContents());

    const current = parseInt(span.style.fontSize, 10) || 14;
    const next = Math.min(20, Math.max(11, current + delta * 2));
    span.style.fontSize = `${next}px`;

    range.insertNode(span);
    selection.removeAllRanges();
    const newRange = document.createRange();
    newRange.selectNodeContents(span);
    selection.addRange(newRange);
    sync();
}

function toggleLinkPanel() {
    focusEditor();
    linkOpen.value = !linkOpen.value;

    if (!linkOpen.value) {
        return;
    }

    const selection = window.getSelection();
    const anchor = selection?.anchorNode?.parentElement?.closest?.('a');
    linkUrl.value = anchor?.getAttribute('href') ?? '';
}

function applyLink() {
    const url = linkUrl.value.trim();

    if (!url) {
        exec('unlink');
    } else {
        focusEditor();
        document.execCommand('createLink', false, url);
        sync();
    }

    linkOpen.value = false;
    linkUrl.value = '';
}

function removeLink() {
    exec('unlink');
    linkOpen.value = false;
    linkUrl.value = '';
}

function toggleImagePanel() {
    focusEditor();
    imageOpen.value = !imageOpen.value;
    linkOpen.value = false;
    imageUrl.value = '';
}

function applyImage() {
    const url = imageUrl.value.trim();
    if (url) {
        focusEditor();
        document.execCommand('insertImage', false, url);
        sync();
    }
    imageOpen.value = false;
    imageUrl.value = '';
}

function onPaste(event) {
    event.preventDefault();
    const text = event.clipboardData?.getData('text/plain') ?? '';
    document.execCommand('insertText', false, text);
    sync();
}

onMounted(async () => {
    await nextTick();

    if (editorRef.value) {
        editorRef.value.innerHTML = props.modelValue || '';
    }
});

watch(
    () => props.modelValue,
    async (value) => {
        await nextTick();

        if (editorRef.value && editorRef.value.innerHTML !== (value || '')) {
            editorRef.value.innerHTML = value || '';
        }
    },
);
</script>

<template>
    <div class="rounded-lg border border-outline-variant overflow-hidden bg-white">
        <div class="flex flex-wrap items-center gap-0.5 px-2 py-1.5 border-b border-outline-variant bg-surface-container-low/60">
            <button
                class="rich-btn"
                title="Gras"
                type="button"
                @mousedown.prevent
                @click="exec('bold')"
            >
                <span class="font-bold text-xs">G</span>
            </button>
            <button
                class="rich-btn"
                title="Italique"
                type="button"
                @mousedown.prevent
                @click="exec('italic')"
            >
                <span class="italic text-xs">I</span>
            </button>
            <button
                class="rich-btn"
                title="Augmenter la taille"
                type="button"
                @mousedown.prevent
                @click="changeSize(1)"
            >
                <span class="text-xs font-bold">A+</span>
            </button>
            <button
                class="rich-btn"
                title="Diminuer la taille"
                type="button"
                @mousedown.prevent
                @click="changeSize(-1)"
            >
                <span class="text-[10px] font-bold">A-</span>
            </button>
            <span class="w-px h-5 bg-outline-variant mx-0.5" />
            <button
                class="rich-btn"
                title="Insérer un lien"
                type="button"
                :class="{ 'is-active': linkOpen }"
                @mousedown.prevent
                @click="toggleLinkPanel"
            >
                <span class="material-symbols-outlined text-[16px]">link</span>
            </button>
            <button
                class="rich-btn"
                title="Retirer le lien"
                type="button"
                @mousedown.prevent
                @click="removeLink"
            >
                <span class="material-symbols-outlined text-[16px]">link_off</span>
            </button>
            <template v-if="allowImage">
                <span class="w-px h-5 bg-outline-variant mx-0.5" />
                <button
                    class="rich-btn"
                    title="Insérer une image"
                    type="button"
                    :class="{ 'is-active': imageOpen }"
                    @mousedown.prevent
                    @click="toggleImagePanel"
                >
                    <span class="material-symbols-outlined text-[16px]">image</span>
                </button>
            </template>
        </div>

        <div v-if="imageOpen" class="flex items-center gap-2 px-2 py-2 border-b border-outline-variant bg-surface-container-low/40">
            <input
                v-model="imageUrl"
                class="flex-1 px-2 py-1.5 text-xs border border-outline-variant rounded-lg"
                placeholder="URL de l'image (https://…)"
                type="text"
                @keydown.enter.prevent="applyImage"
            />
            <button class="px-2.5 py-1.5 text-xs font-bold bg-primary text-on-primary rounded-lg" type="button" @click="applyImage">
                Insérer
            </button>
        </div>

        <div v-if="linkOpen" class="flex items-center gap-2 px-2 py-2 border-b border-outline-variant bg-surface-container-low/40">
            <input
                v-model="linkUrl"
                class="flex-1 px-2 py-1.5 text-xs border border-outline-variant rounded-lg"
                placeholder="URL du lien (ex. inscription-assure.html)"
                type="text"
                @keydown.enter.prevent="applyLink"
            />
            <button class="px-2.5 py-1.5 text-xs font-bold bg-primary text-on-primary rounded-lg" type="button" @click="applyLink">
                OK
            </button>
        </div>

        <div
            ref="editorRef"
            class="rich-editor px-3 py-2.5 text-xs text-on-surface outline-none"
            contenteditable="true"
            :data-placeholder="placeholder"
            :style="{ minHeight }"
            @input="sync"
            @paste="onPaste"
        />
    </div>
</template>

<style scoped>
.rich-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 6px;
    color: #5c403f;
    transition: background-color 0.15s ease;
}

.rich-btn:hover,
.rich-btn.is-active {
    background-color: #f6f3f2;
    color: #9e001f;
}

.rich-editor:empty::before {
    content: attr(data-placeholder);
    color: #906f6e;
    pointer-events: none;
}

.rich-editor :deep(a) {
    color: inherit;
    text-decoration: underline;
    font-weight: 600;
}

.rich-editor :deep(strong),
.rich-editor :deep(b) {
    font-weight: 700;
}

.rich-editor :deep(em),
.rich-editor :deep(i) {
    font-style: italic;
}

.rich-editor :deep(img) {
    max-width: 100%;
    height: auto;
    border-radius: 8px;
    margin: 8px 0;
}
</style>
