<script setup>
/**
 * Liste de choix avec recherche : on tape quelques lettres, la liste se réduit. Pensée pour les listes
 * longues (ex. 100 gestionnaires) où une liste déroulante classique est inutilisable.
 *
 *  - recherche sans tenir compte des accents ni des majuscules ;
 *  - au clavier : ↑ ↓ pour naviguer, Entrée pour choisir, Échap pour fermer ;
 *  - la liste s'affiche en position fixe : elle n'est pas coupée par un tableau ou un panneau défilant.
 */
import { computed, nextTick, onBeforeUnmount, ref } from 'vue';

const props = defineProps({
    modelValue: { type: String, default: '' },
    // [{ value, label, hint?, disabled? }]
    options: { type: Array, default: () => [] },
    placeholder: { type: String, default: 'Choisir…' },
    searchPlaceholder: { type: String, default: 'Rechercher…' },
    // Texte affiché quand la valeur courante n'est pas dans la liste (ex. compte inactif).
    fallbackLabel: { type: String, default: '' },
    buttonClass: { type: String, default: 'px-3 py-2 text-xs' },
});

const emit = defineEmits(['update:modelValue']);

const open = ref(false);
const query = ref('');
const active = ref(0);
const root = ref(null);
const searchInput = ref(null);
const panelStyle = ref({});

const normalize = (text) => String(text ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();

const filtered = computed(() => {
    const needle = normalize(query.value).trim();
    // On cherche dans le nom et dans l'indication (ex. le rôle « Superviseur »).
    return needle ? props.options.filter((o) => normalize(`${o.label} ${o.hint ?? ''}`).includes(needle)) : props.options;
});

const currentLabel = computed(() => props.options.find((o) => o.value === props.modelValue)?.label
    ?? (props.modelValue ? props.fallbackLabel || props.modelValue : ''));

function place() {
    const rect = root.value?.getBoundingClientRect();
    if (!rect) return;
    const below = window.innerHeight - rect.bottom;
    const height = 280;
    panelStyle.value = {
        left: `${rect.left}px`,
        width: `${Math.max(rect.width, 240)}px`,
        ...(below < height && rect.top > below
            ? { bottom: `${window.innerHeight - rect.top + 4}px` }
            : { top: `${rect.bottom + 4}px` }),
    };
}

function openList() {
    query.value = '';
    active.value = Math.max(0, props.options.findIndex((o) => o.value === props.modelValue));
    place();
    open.value = true;
    window.addEventListener('scroll', close, true);
    window.addEventListener('resize', close);
    document.addEventListener('mousedown', onOutside);
    nextTick(() => searchInput.value?.focus());
}

function close() {
    open.value = false;
    window.removeEventListener('scroll', close, true);
    window.removeEventListener('resize', close);
    document.removeEventListener('mousedown', onOutside);
}

function onOutside(event) {
    if (!root.value?.contains(event.target) && !event.target.closest?.('[data-search-select-panel]')) close();
}

function choose(option) {
    if (!option || option.disabled) return;
    emit('update:modelValue', option.value);
    close();
}

function onKeydown(event) {
    const last = filtered.value.length - 1;
    if (event.key === 'ArrowDown') { event.preventDefault(); active.value = Math.min(last, active.value + 1); scrollActive(); }
    else if (event.key === 'ArrowUp') { event.preventDefault(); active.value = Math.max(0, active.value - 1); scrollActive(); }
    else if (event.key === 'Enter') { event.preventDefault(); choose(filtered.value[active.value]); }
    else if (event.key === 'Escape') { event.preventDefault(); close(); }
}

function scrollActive() {
    nextTick(() => document.querySelector('[data-search-select-panel] [data-active="true"]')?.scrollIntoView({ block: 'nearest' }));
}

onBeforeUnmount(close);
</script>

<template>
    <div ref="root" class="relative min-w-0">
        <button
            type="button"
            :class="buttonClass"
            class="w-full flex items-center justify-between gap-2 border border-outline-variant rounded-lg bg-white text-left hover:border-primary focus:border-primary focus:ring-1 focus:ring-primary outline-none"
            aria-haspopup="listbox"
            :aria-expanded="open"
            @click="open ? close() : openList()"
        >
            <span class="truncate" :class="currentLabel ? '' : 'text-on-surface-variant'">{{ currentLabel || placeholder }}</span>
            <span class="material-symbols-outlined text-[18px] text-on-surface-variant shrink-0">{{ open ? 'expand_less' : 'expand_more' }}</span>
        </button>

        <Teleport to="body">
            <div
                v-if="open"
                data-search-select-panel
                class="fixed z-[80] bg-white border border-outline-variant rounded-lg shadow-xl overflow-hidden"
                :style="panelStyle"
            >
                <div class="p-2 border-b border-outline-variant">
                    <input
                        ref="searchInput"
                        v-model="query"
                        class="w-full px-3 py-2 text-xs border border-outline-variant rounded-lg focus:border-primary focus:ring-1 focus:ring-primary outline-none"
                        :placeholder="searchPlaceholder"
                        type="search"
                        autocomplete="off"
                        @input="active = 0"
                        @keydown="onKeydown"
                    />
                </div>
                <ul class="max-h-56 overflow-y-auto py-1" role="listbox">
                    <li
                        v-for="(option, index) in filtered"
                        :key="option.value"
                        :aria-selected="option.value === modelValue"
                        :data-active="index === active"
                        class="px-3 py-2 text-xs flex items-center justify-between gap-3"
                        :class="[
                            option.disabled ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer',
                            index === active ? 'bg-primary/10' : 'hover:bg-surface-container-low',
                            option.value === modelValue ? 'font-bold text-primary' : '',
                        ]"
                        role="option"
                        @click="choose(option)"
                        @mousemove="active = index"
                    >
                        <span class="truncate">{{ option.label }}</span>
                        <span v-if="option.hint" class="text-[10px] text-on-surface-variant shrink-0">{{ option.hint }}</span>
                    </li>
                    <li v-if="!filtered.length" class="px-3 py-3 text-xs text-on-surface-variant italic">Aucun résultat pour « {{ query }} ».</li>
                </ul>
                <p v-if="filtered.length > 8" class="px-3 py-1.5 text-[10px] text-on-surface-variant border-t border-outline-variant">
                    {{ filtered.length }} résultat(s) — tapez pour affiner.
                </p>
            </div>
        </Teleport>
    </div>
</template>
