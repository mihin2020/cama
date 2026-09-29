<script setup>
const props = defineProps({
    node: { type: Object, required: true },
    depth: { type: Number, required: true },
    path: { type: Array, required: true },
});

const emit = defineEmits(['update-libelle', 'add-child', 'remove']);

const LEVEL_LABELS = ['Région', 'Corps', 'Service', 'Section', 'Sous-section'];
const LEVEL_ICONS = ['map', 'shield', 'apartment', 'groups', 'folder_open'];
const LEVEL_PLACEHOLDERS = [
    'Ex. 1re Région Militaire — Centre (Ouagadougou)',
    'Ex. 11e Régiment d\'Infanterie Commando',
    'Ex. Service Administratif',
    'Ex. Section Personnel',
    'Ex. Bureau Solde',
];
const ADD_CHILD_LABELS = [
    'Ajouter un corps',
    'Ajouter un service',
    'Ajouter une section',
    'Ajouter une sous-section',
];
const CHILD_KEYS = ['corps', 'services', 'sections', 'sous_sections'];
const BORDER_CLASS = ['border-primary/40', 'border-secondary/40', 'border-tertiary/40', 'border-outline-variant', 'border-outline-variant'];

const childKey = props.depth <= 4 ? CHILD_KEYS[props.depth - 1] : null;
const children = childKey ? (props.node[childKey] || []) : [];
const border = BORDER_CLASS[Math.min(props.depth - 1, 4)];
const levelLabel = LEVEL_LABELS[props.depth - 1];
const levelIcon = LEVEL_ICONS[props.depth - 1];
const placeholder = LEVEL_PLACEHOLDERS[props.depth - 1];
const addChildLabel = ADD_CHILD_LABELS[props.depth - 1];

function onLibelleInput(event) {
    emit('update-libelle', { path: props.path, libelle: event.target.value });
}

function addChild() {
    emit('add-child', props.path);
}

function removeNode() {
    emit('remove', props.path);
}
</script>

<template>
    <div class="org-node">
        <div class="org-node-row flex flex-wrap items-center gap-2 p-2.5 rounded-lg bg-white border border-outline-variant/80">
            <span class="org-level-pill" :class="`org-level-pill--${Math.min(depth, 5)}`">
                <span class="material-symbols-outlined">{{ levelIcon }}</span>
                Niv. {{ depth }} · {{ levelLabel }}
            </span>
            <input
                class="flex-1 min-w-[140px] px-2.5 py-2 text-xs border border-outline-variant rounded-lg bg-surface-container-lowest"
                :value="node.libelle"
                :placeholder="placeholder"
                :aria-label="levelLabel"
                @input="onLibelleInput"
            />
            <button
                type="button"
                class="text-error shrink-0 p-1 rounded hover:bg-error/10"
                :title="`Supprimer ce ${levelLabel.toLowerCase()}`"
                @click="removeNode"
            >
                <span class="material-symbols-outlined text-[18px]">delete</span>
            </button>
        </div>

        <div v-if="childKey" class="org-node-children ml-3 sm:ml-5 pl-3 border-l-2 space-y-2" :class="border">
            <OrgTreeNode
                v-for="(child, index) in children"
                :key="child.id || index"
                :node="child"
                :depth="depth + 1"
                :path="[...path, index]"
                @update-libelle="emit('update-libelle', $event)"
                @add-child="emit('add-child', $event)"
                @remove="emit('remove', $event)"
            />
            <button type="button" class="org-add-btn mt-1" @click="addChild">
                <span class="material-symbols-outlined text-[16px]">add</span>
                {{ addChildLabel }}
            </button>
        </div>
    </div>
</template>

<style scoped>
.org-level-pill {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 8px;
    border-radius: 9999px;
    font-size: 10px;
    font-weight: 700;
    white-space: nowrap;
}
.org-level-pill .material-symbols-outlined { font-size: 14px; }
.org-level-pill--1 { background: rgba(158, 0, 31, 0.1); color: #9e001f; }
.org-level-pill--2 { background: rgba(0, 110, 39, 0.1); color: #006e27; }
.org-level-pill--3 { background: rgba(116, 91, 0, 0.1); color: #745b00; }
.org-level-pill--4 { background: rgba(144, 111, 110, 0.12); color: #5c403f; }
.org-level-pill--5 { background: #f0eded; color: #5c403f; }
.org-add-btn {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 6px 10px;
    border-radius: 0.5rem;
    border: 1px dashed #e5bdbb;
    color: #9e001f;
    font-size: 11px;
    font-weight: 700;
    background: transparent;
    cursor: pointer;
}
.org-add-btn:hover { background: rgba(158, 0, 31, 0.06); border-color: #9e001f; }
.org-node-row input:focus { outline: 2px solid rgba(158, 0, 31, 0.25); border-color: #9e001f; }
</style>
