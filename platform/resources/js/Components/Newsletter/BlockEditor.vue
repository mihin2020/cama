<script setup>
import CamaSimpleRichText from '@/Components/CamaSimpleRichText.vue';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    modelValue: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:modelValue']);

function clone(value) {
    try {
        return JSON.parse(JSON.stringify(value ?? []));
    } catch (e) {
        return [];
    }
}

const blocks = ref(clone(props.modelValue));
const activeId = ref(blocks.value[0]?.id ?? null);

watch(
    () => props.modelValue,
    (value) => {
        const incoming = JSON.stringify(value ?? []);
        const current = JSON.stringify(blocks.value);
        if (incoming === current) return;
        blocks.value = clone(value);
        if (!blocks.value.find(b => b.id === activeId.value)) {
            activeId.value = blocks.value[0]?.id ?? null;
        }
    },
    { deep: true },
);

const activeBlock = computed(() => blocks.value.find(b => b.id === activeId.value) ?? null);

function uid() {
    return `b${Date.now()}${Math.random().toString(36).slice(2, 6)}`;
}

function commit() {
    emit('update:modelValue', clone(blocks.value));
}

function defaultContent(type) {
    return {
        heading: { text: 'Titre', level: 2 },
        text: { html: '<p>Bonjour {{prenom}},</p><p>Votre message ici.</p>' },
        image: { url: '/images/logo_cama.png', alt: 'CAMA', width: 200 },
        button: { label: 'En savoir plus', url: 'https://cama.bf' },
        divider: {},
        spacer: { height: 24 },
    }[type] ?? {};
}

function addBlock(type) {
    const block = { id: uid(), type, content: defaultContent(type) };
    blocks.value = [...blocks.value, block];
    activeId.value = block.id;
    commit();
}

function removeBlock(id) {
    blocks.value = blocks.value.filter(b => b.id !== id);
    if (activeId.value === id) activeId.value = blocks.value[0]?.id ?? null;
    commit();
}

function moveBlock(id, dir) {
    const index = blocks.value.findIndex(b => b.id === id);
    const target = index + dir;
    if (index < 0 || target < 0 || target >= blocks.value.length) return;
    const copy = [...blocks.value];
    [copy[index], copy[target]] = [copy[target], copy[index]];
    blocks.value = copy;
    commit();
}

function duplicateBlock(id) {
    const source = blocks.value.find(b => b.id === id);
    if (!source) return;
    const cloneBlock = { ...clone([source])[0], id: uid() };
    const index = blocks.value.findIndex(b => b.id === id);
    const copy = [...blocks.value];
    copy.splice(index + 1, 0, cloneBlock);
    blocks.value = copy;
    activeId.value = cloneBlock.id;
    commit();
}

function blockLabel(type) {
    return {
        heading: 'Titre',
        text: 'Texte',
        image: 'Image',
        button: 'Bouton',
        divider: 'Séparateur',
        spacer: 'Espace',
    }[type] ?? type;
}

function blockIcon(type) {
    return {
        heading: 'title',
        text: 'notes',
        image: 'image',
        button: 'smart_button',
        divider: 'horizontal_rule',
        spacer: 'height',
    }[type] ?? 'widgets';
}
</script>

<template>
    <div class="grid lg:grid-cols-12 gap-4">
        <div class="lg:col-span-4 space-y-3">
            <div class="flex flex-wrap gap-1.5">
                <button v-for="type in ['heading', 'text', 'image', 'button', 'divider', 'spacer']" :key="type" type="button" class="px-2.5 py-1.5 rounded-lg border border-outline-variant text-[11px] font-bold hover:border-primary" @click="addBlock(type)">
                    + {{ blockLabel(type) }}
                </button>
            </div>
            <p class="text-[10px] text-on-surface-variant">Variables : <code v-pre>{{prenom}}</code> <code v-pre>{{nom}}</code> <code v-pre>{{email}}</code></p>

            <div v-if="!blocks.length" class="rounded-lg border border-dashed border-outline-variant p-4 text-center text-sm text-on-surface-variant">
                Aucun bloc — cliquez « + Texte » ou choisissez un modèle CAMA.
            </div>
            <div v-else class="space-y-1 max-h-[420px] overflow-y-auto">
                <button
                    v-for="(block, index) in blocks"
                    :key="block.id"
                    type="button"
                    class="w-full flex items-center gap-2 px-3 py-2 rounded-lg border text-left text-xs"
                    :class="activeId === block.id ? 'border-primary bg-primary/5' : 'border-outline-variant'"
                    @click="activeId = block.id"
                >
                    <span class="material-symbols-outlined text-[16px] text-primary">{{ blockIcon(block.type) }}</span>
                    <span class="flex-1 truncate font-semibold">{{ blockLabel(block.type) }} {{ index + 1 }}</span>
                    <span class="flex gap-0.5" @click.stop>
                        <span class="material-symbols-outlined text-[16px] cursor-pointer" @click="moveBlock(block.id, -1)">arrow_upward</span>
                        <span class="material-symbols-outlined text-[16px] cursor-pointer" @click="moveBlock(block.id, 1)">arrow_downward</span>
                        <span class="material-symbols-outlined text-[16px] cursor-pointer" @click="duplicateBlock(block.id)">content_copy</span>
                        <span class="material-symbols-outlined text-[16px] text-error cursor-pointer" @click="removeBlock(block.id)">delete</span>
                    </span>
                </button>
            </div>
        </div>

        <div class="lg:col-span-8 assure-card p-4 min-h-[300px]">
            <template v-if="activeBlock">
                <p class="text-xs uppercase tracking-[0.18em] text-primary font-bold mb-3">Éditer — {{ blockLabel(activeBlock.type) }}</p>

                <div v-if="activeBlock.type === 'heading'" class="space-y-2">
                    <input v-model="activeBlock.content.text" class="w-full px-3 py-2 border border-outline-variant rounded-lg text-sm" placeholder="Titre" @input="commit" />
                    <select v-model.number="activeBlock.content.level" class="px-3 py-2 border border-outline-variant rounded-lg text-sm" @change="commit">
                        <option :value="1">Niveau 1</option>
                        <option :value="2">Niveau 2</option>
                        <option :value="3">Niveau 3</option>
                    </select>
                </div>

                <div v-else-if="activeBlock.type === 'text'">
                    <CamaSimpleRichText v-model="activeBlock.content.html" min-height="160px" @update:model-value="commit" />
                </div>

                <div v-else-if="activeBlock.type === 'image'" class="space-y-2">
                    <input v-model="activeBlock.content.url" class="w-full px-3 py-2 border border-outline-variant rounded-lg text-sm" placeholder="URL image" @input="commit" />
                    <input v-model="activeBlock.content.alt" class="w-full px-3 py-2 border border-outline-variant rounded-lg text-sm" placeholder="Texte alternatif" @input="commit" />
                    <input v-model.number="activeBlock.content.width" type="number" class="w-32 px-3 py-2 border border-outline-variant rounded-lg text-sm" placeholder="Largeur px" @input="commit" />
                </div>

                <div v-else-if="activeBlock.type === 'button'" class="space-y-2">
                    <input v-model="activeBlock.content.label" class="w-full px-3 py-2 border border-outline-variant rounded-lg text-sm" placeholder="Libellé bouton" @input="commit" />
                    <input v-model="activeBlock.content.url" class="w-full px-3 py-2 border border-outline-variant rounded-lg text-sm" placeholder="URL" @input="commit" />
                </div>

                <div v-else-if="activeBlock.type === 'spacer'">
                    <label class="text-xs text-on-surface-variant">Hauteur (px)</label>
                    <input v-model.number="activeBlock.content.height" type="number" min="8" max="120" class="w-32 px-3 py-2 border border-outline-variant rounded-lg text-sm" @input="commit" />
                </div>

                <p v-else class="text-sm text-on-surface-variant">Bloc sans paramètres.</p>
            </template>
            <p v-else class="text-sm text-on-surface-variant italic">Sélectionnez un bloc à gauche pour le modifier.</p>
        </div>
    </div>
</template>
