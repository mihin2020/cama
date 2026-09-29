<script setup>
import { camaFlagUrl } from '@/Data/camaCountries';
import { createPhoneRow, findDial, formatPhones, getDialList, parsePhoneString } from '@/Utils/camaPhone';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    modelValue: { type: String, default: '' },
    max: { type: Number, default: 2 },
    required: { type: Boolean, default: false },
    defaultCountry: { type: String, default: 'BF' },
    inputClass: { type: String, default: 'reg-input flex-1 min-w-0' },
});

const emit = defineEmits(['update:modelValue']);

const dialList = getDialList();
const rows = ref([]);
const openPanelIdx = ref(null);
const searchQuery = ref('');

function syncFromModel(value) {
    const parsed = parsePhoneString(value);
    rows.value = parsed.length ? parsed : [createPhoneRow(props.defaultCountry)];
}

watch(() => props.modelValue, (v) => syncFromModel(v), { immediate: true });

watch(rows, () => {
    emit('update:modelValue', formatPhones(rows.value));
}, { deep: true });

const filteredCountries = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    if (!q) return dialList;
    return dialList.filter(
        (c) => c.name.toLowerCase().includes(q) || c.dial.includes(q) || c.code.toLowerCase().includes(q),
    );
});

function flagSrc(code) {
    return camaFlagUrl(code, 24);
}

function flagSrc2x(code) {
    return camaFlagUrl(code, 48);
}

function togglePanel(idx) {
    if (openPanelIdx.value === idx) {
        openPanelIdx.value = null;
        return;
    }
    openPanelIdx.value = idx;
    searchQuery.value = '';
}

function selectCountry(idx, code) {
    const entry = findDial(code);
    rows.value[idx].country = entry.code;
    rows.value[idx].dial = entry.dial;
    openPanelIdx.value = null;
}

function addRow() {
    if (rows.value.length >= props.max) return;
    rows.value.push(createPhoneRow(props.defaultCountry));
}

function removeRow(idx) {
    if (rows.value.length <= 1) {
        rows.value[0].number = '';
        return;
    }
    rows.value.splice(idx, 1);
}

function onDocumentClick(e) {
    if (!e.target.closest('.phone-country-wrap')) {
        openPanelIdx.value = null;
    }
}

onMounted(() => document.addEventListener('click', onDocumentClick));
onBeforeUnmount(() => document.removeEventListener('click', onDocumentClick));
</script>

<template>
    <div class="phone-input-root">
        <div class="space-y-2">
            <div v-for="(row, idx) in rows" :key="idx" class="flex items-center gap-2 phone-row">
                <div class="phone-country-wrap relative shrink-0">
                    <button
                        type="button"
                        class="phone-country-btn flex items-center gap-1.5 px-2 py-2 text-xs border border-outline-variant rounded-lg bg-white min-w-[130px] max-w-[160px]"
                        @click.stop="togglePanel(idx)"
                    >
                        <img
                            :src="flagSrc(row.country)"
                            :srcset="`${flagSrc2x(row.country)} 2x`"
                            width="24"
                            height="18"
                            alt=""
                            class="rounded-sm border border-black/5 shrink-0"
                        />
                        <span class="font-bold text-on-surface shrink-0">{{ row.dial }}</span>
                        <span class="material-symbols-outlined text-[18px] text-on-surface-variant ml-auto">expand_more</span>
                    </button>
                    <div
                        v-show="openPanelIdx === idx"
                        class="phone-country-panel absolute z-[70] mt-1 left-0 w-64 bg-white border border-outline-variant rounded-lg shadow-xl overflow-hidden"
                    >
                        <div class="p-2 border-b border-outline-variant">
                            <input
                                v-model="searchQuery"
                                type="text"
                                class="phone-country-search w-full px-2 py-1.5 text-xs border border-outline-variant rounded outline-none focus:ring-2 focus:ring-primary"
                                placeholder="Rechercher un pays…"
                                @click.stop
                            />
                        </div>
                        <div class="phone-country-list max-h-52 overflow-y-auto py-1">
                            <button
                                v-for="country in filteredCountries"
                                :key="country.code"
                                type="button"
                                class="w-full flex items-center gap-2 px-3 py-2 text-xs text-left hover:bg-surface-container-low"
                                :class="country.code === row.country ? 'bg-primary/5 font-semibold' : ''"
                                @click.stop="selectCountry(idx, country.code)"
                            >
                                <img :src="flagSrc(country.code)" width="24" height="18" alt="" class="rounded-sm border border-black/5 shrink-0" />
                                <span class="truncate flex-1">{{ country.name }}</span>
                                <span class="font-bold text-on-surface-variant shrink-0">{{ country.dial }}</span>
                            </button>
                            <p v-if="!filteredCountries.length" class="px-3 py-2 text-xs text-center text-on-surface-variant">Aucun pays</p>
                        </div>
                    </div>
                </div>
                <input
                    v-model="rows[idx].number"
                    type="tel"
                    placeholder="70 12 34 56"
                    :class="inputClass"
                    :required="required && idx === 0"
                />
                <button
                    v-if="rows.length > 1 || idx > 0"
                    type="button"
                    class="phone-remove text-error shrink-0"
                    title="Retirer"
                    @click="removeRow(idx)"
                >
                    <span class="material-symbols-outlined text-[18px]">close</span>
                </button>
            </div>
        </div>
        <button
            v-if="rows.length < max"
            type="button"
            class="phone-add-btn mt-2 text-primary text-xs font-bold flex items-center gap-1 hover:underline"
            @click="addRow"
        >
            <span class="material-symbols-outlined text-[16px]">add</span>
            Ajouter un numéro
        </button>
    </div>
</template>
