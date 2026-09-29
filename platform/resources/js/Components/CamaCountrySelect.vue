<script setup>
import { CAMA_COUNTRIES, camaFlagUrl } from '@/Data/camaCountries';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: '— Nationalité —' },
    buttonClass: { type: String, default: 'w-full px-3 py-2 text-sm border border-outline-variant rounded-lg bg-white flex items-center gap-2' },
});

const emit = defineEmits(['update:modelValue']);

const open = ref(false);
const searchQuery = ref('');

const countries = CAMA_COUNTRIES.map(([code, name]) => ({ code, name }));

const selected = computed(() => countries.find((c) => c.name === props.modelValue) || null);

const filtered = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    if (!q) return countries;
    return countries.filter((c) => c.name.toLowerCase().includes(q));
});

function flagSrc(code) {
    return camaFlagUrl(code, 24);
}

function toggle() {
    open.value = !open.value;
    if (open.value) searchQuery.value = '';
}

function select(country) {
    emit('update:modelValue', country.name);
    open.value = false;
}

function clear() {
    emit('update:modelValue', '');
    open.value = false;
}

function onDocumentClick(e) {
    if (!e.target.closest('.country-select-wrap')) {
        open.value = false;
    }
}

onMounted(() => document.addEventListener('click', onDocumentClick));
onBeforeUnmount(() => document.removeEventListener('click', onDocumentClick));
</script>

<template>
    <div class="country-select-wrap relative">
        <button type="button" :class="buttonClass" @click.stop="toggle">
            <img v-if="selected" :src="flagSrc(selected.code)" width="24" height="18" alt="" class="rounded-sm border border-black/5 shrink-0" />
            <span :class="selected ? 'text-on-surface truncate' : 'text-on-surface-variant truncate'">{{ selected ? selected.name : placeholder }}</span>
            <span class="material-symbols-outlined text-[18px] text-on-surface-variant ml-auto">expand_more</span>
        </button>
        <div
            v-show="open"
            class="absolute z-[70] mt-1 left-0 w-full min-w-[220px] bg-white border border-outline-variant rounded-lg shadow-xl overflow-hidden"
        >
            <div class="p-2 border-b border-outline-variant">
                <input
                    v-model="searchQuery"
                    type="text"
                    class="w-full px-2 py-1.5 text-xs border border-outline-variant rounded outline-none focus:ring-2 focus:ring-primary"
                    placeholder="Rechercher un pays…"
                    @click.stop
                />
            </div>
            <div class="max-h-52 overflow-y-auto py-1">
                <button
                    type="button"
                    class="w-full flex items-center gap-2 px-3 py-2 text-xs text-left text-on-surface-variant hover:bg-surface-container-low"
                    @click.stop="clear"
                >
                    <span class="material-symbols-outlined text-[16px]">block</span> Aucune
                </button>
                <button
                    v-for="country in filtered"
                    :key="country.code"
                    type="button"
                    class="w-full flex items-center gap-2 px-3 py-2 text-xs text-left hover:bg-surface-container-low"
                    :class="country.name === modelValue ? 'bg-primary/5 font-semibold' : ''"
                    @click.stop="select(country)"
                >
                    <img :src="flagSrc(country.code)" width="24" height="18" alt="" class="rounded-sm border border-black/5 shrink-0" />
                    <span class="truncate flex-1">{{ country.name }}</span>
                </button>
                <p v-if="!filtered.length" class="px-3 py-2 text-xs text-center text-on-surface-variant">Aucun pays</p>
            </div>
        </div>
    </div>
</template>
