<script setup>
import { computed, ref, watch } from 'vue';
import { EMOJI_CATEGORIES, MATERIAL_ICON_CATEGORIES, isEmojiIcon } from '@/Utils/camaIconCatalog';

const props = defineProps({
    modelValue: { type: String, default: '' },
    label: { type: String, default: 'Icône' },
});

const emit = defineEmits(['update:modelValue']);

const pickerOpen = ref(false);
const activeTab = ref('material');
const activeCategory = ref('sante');
const search = ref('');

const isEmoji = computed(() => isEmojiIcon(props.modelValue));

const filteredMaterialIcons = computed(() => {
    const query = search.value.trim().toLowerCase();

    if (query) {
        return allMaterialFlat().filter((icon) => icon.includes(query));
    }

    const category = MATERIAL_ICON_CATEGORIES.find((item) => item.id === activeCategory.value);

    return category?.icons ?? [];
});

const filteredEmojiIcons = computed(() => {
    const query = search.value.trim();

    if (query) {
        return allEmojiFlat().filter((emoji) => emoji.includes(query));
    }

    const category = EMOJI_CATEGORIES.find((item) => item.id === activeCategory.value);

    return category?.icons ?? [];
});

const activeCategories = computed(() => (
    activeTab.value === 'material' ? MATERIAL_ICON_CATEGORIES : EMOJI_CATEGORIES
));

function allMaterialFlat() {
    return [...new Set(MATERIAL_ICON_CATEGORIES.flatMap((category) => category.icons))];
}

function allEmojiFlat() {
    return [...new Set(EMOJI_CATEGORIES.flatMap((category) => category.icons))];
}

function openPicker() {
    pickerOpen.value = true;

    if (isEmojiIcon(props.modelValue)) {
        activeTab.value = 'emoji';
    } else {
        activeTab.value = 'material';
    }
}

function closePicker() {
    pickerOpen.value = false;
    search.value = '';
}

function selectIcon(value) {
    emit('update:modelValue', value);
    closePicker();
}

function clearIcon() {
    emit('update:modelValue', '');
}

function switchTab(tab) {
    activeTab.value = tab;
    activeCategory.value = tab === 'material' ? 'sante' : 'sante-emoji';
    search.value = '';
}

watch(activeTab, (tab) => {
    activeCategory.value = tab === 'material' ? 'sante' : 'sante-emoji';
});
</script>

<template>
    <div class="space-y-2">
        <label v-if="label" class="text-[11px] font-bold uppercase text-on-surface-variant block">
            {{ label }}
        </label>

        <div class="flex items-center gap-2">
            <button
                type="button"
                class="flex-1 flex items-center gap-2 px-3 py-2 text-xs border border-outline-variant rounded-lg bg-white hover:border-primary/40 transition-colors text-left min-h-[38px]"
                @click="openPicker"
            >
                <span v-if="modelValue && isEmoji" class="text-lg leading-none">{{ modelValue }}</span>
                <span v-else-if="modelValue" class="material-symbols-outlined text-[20px] text-on-surface-variant">{{ modelValue }}</span>
                <span v-else class="material-symbols-outlined text-[20px] text-on-surface-variant/40">image</span>
                <span class="truncate text-on-surface-variant">
                    {{ modelValue ? (isEmoji ? 'Emoji sélectionné' : modelValue) : 'Choisir une icône…' }}
                </span>
            </button>

            <button
                v-if="modelValue"
                type="button"
                class="w-9 h-9 flex items-center justify-center rounded-lg border border-outline-variant text-on-surface-variant hover:text-error"
                title="Retirer l'icône"
                @click="clearIcon"
            >
                <span class="material-symbols-outlined text-[18px]">close</span>
            </button>
        </div>

        <Teleport to="body">
            <div
                v-if="pickerOpen"
                class="fixed inset-0 bg-black/50 z-[90] flex items-center justify-center p-4"
                @click.self="closePicker"
            >
                <div class="bg-white rounded-2xl w-full max-w-2xl max-h-[88vh] flex flex-col shadow-2xl overflow-hidden">
                    <div class="flex items-center justify-between px-5 py-4 border-b border-outline-variant shrink-0">
                        <div>
                            <h3 class="font-semibold text-sm text-on-surface">Choisir une icône</h3>
                            <p class="text-[10px] text-on-surface-variant mt-0.5">Material Symbols ou emojis</p>
                        </div>
                        <button type="button" @click="closePicker">
                            <span class="material-symbols-outlined">close</span>
                        </button>
                    </div>

                    <div class="px-5 pt-4 pb-2 space-y-3 shrink-0">
                        <div class="flex gap-1 p-1 bg-surface-container-low rounded-lg w-fit">
                            <button
                                type="button"
                                class="px-3 py-1.5 text-xs font-bold rounded-md transition-colors"
                                :class="activeTab === 'material' ? 'bg-white text-primary shadow-sm' : 'text-on-surface-variant'"
                                @click="switchTab('material')"
                            >
                                Material Symbols
                            </button>
                            <button
                                type="button"
                                class="px-3 py-1.5 text-xs font-bold rounded-md transition-colors"
                                :class="activeTab === 'emoji' ? 'bg-white text-primary shadow-sm' : 'text-on-surface-variant'"
                                @click="switchTab('emoji')"
                            >
                                Emojis
                            </button>
                        </div>

                        <input
                            v-model="search"
                            class="w-full px-3 py-2 text-xs border border-outline-variant rounded-lg"
                            :placeholder="activeTab === 'material' ? 'Rechercher une icône (ex. hospital, groups)…' : 'Rechercher un emoji…'"
                            type="search"
                        />
                    </div>

                    <div class="flex flex-1 min-h-0 border-t border-outline-variant">
                        <div v-if="!search" class="w-40 shrink-0 border-r border-outline-variant overflow-y-auto p-2 space-y-0.5">
                            <button
                                v-for="category in activeCategories"
                                :key="category.id"
                                type="button"
                                class="w-full text-left px-2.5 py-2 text-[11px] font-semibold rounded-lg transition-colors"
                                :class="activeCategory === category.id ? 'bg-primary/10 text-primary' : 'text-on-surface-variant hover:bg-surface-container-low'"
                                @click="activeCategory = category.id"
                            >
                                {{ category.label }}
                            </button>
                        </div>

                        <div class="flex-1 overflow-y-auto p-4">
                            <div v-if="activeTab === 'material'" class="grid grid-cols-6 sm:grid-cols-8 gap-2">
                                <button
                                    v-for="icon in filteredMaterialIcons"
                                    :key="icon"
                                    type="button"
                                    class="aspect-square flex flex-col items-center justify-center gap-1 rounded-lg border transition-all hover:border-primary hover:bg-primary/5"
                                    :class="modelValue === icon ? 'border-primary bg-primary/10 ring-1 ring-primary' : 'border-outline-variant/60'"
                                    :title="icon"
                                    @click="selectIcon(icon)"
                                >
                                    <span class="material-symbols-outlined text-[22px]">{{ icon }}</span>
                                </button>
                            </div>

                            <div v-else class="grid grid-cols-6 sm:grid-cols-8 gap-2">
                                <button
                                    v-for="emoji in filteredEmojiIcons"
                                    :key="emoji"
                                    type="button"
                                    class="aspect-square flex items-center justify-center text-2xl rounded-lg border transition-all hover:border-primary hover:bg-primary/5"
                                    :class="modelValue === emoji ? 'border-primary bg-primary/10 ring-1 ring-primary' : 'border-outline-variant/60'"
                                    @click="selectIcon(emoji)"
                                >
                                    {{ emoji }}
                                </button>
                            </div>

                            <p
                                v-if="(activeTab === 'material' && !filteredMaterialIcons.length) || (activeTab === 'emoji' && !filteredEmojiIcons.length)"
                                class="text-center text-xs text-on-surface-variant py-8"
                            >
                                Aucun résultat pour « {{ search }} »
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
