<script setup>
import { computed } from 'vue';

/**
 * Pagination numérotée : « 1–20 sur 215 », pages avec « … », lignes par page.
 * Émet `change` (numéro de page) et `per-page` (nouvelle taille de page).
 */
const props = defineProps({
    currentPage: { type: Number, required: true },
    lastPage: { type: Number, required: true },
    total: { type: Number, required: true },
    perPage: { type: Number, default: 20 },
    perPageOptions: { type: Array, default: () => [20, 50, 100] },
    label: { type: String, default: 'élément(s)' },
});

const emit = defineEmits(['change', 'per-page']);

// Choix de la taille de page inutile quand tout tient déjà dans la plus petite page.
const showPerPage = computed(() => props.perPageOptions.length > 1 && props.total > Math.min(...props.perPageOptions));

const from = computed(() => (props.total ? (props.currentPage - 1) * props.perPage + 1 : 0));
const to = computed(() => Math.min(props.currentPage * props.perPage, props.total));

// Première et dernière page, plus deux pages autour de la page courante.
const pages = computed(() => {
    const last = props.lastPage;
    const current = props.currentPage;
    const wanted = new Set([1, last, current - 1, current, current + 1].filter((p) => p >= 1 && p <= last));
    const sorted = [...wanted].sort((a, b) => a - b);
    const out = [];
    sorted.forEach((page, index) => {
        if (index && page - sorted[index - 1] > 1) out.push(null);
        out.push(page);
    });
    return out;
});

function go(page) {
    if (page >= 1 && page <= props.lastPage && page !== props.currentPage) emit('change', page);
}
</script>

<template>
    <nav v-if="total > 0" class="flex flex-col sm:flex-row items-center justify-between gap-3 py-3" aria-label="Pagination">
        <div class="flex items-center gap-3 text-[11px] text-on-surface-variant">
            <span class="tabular-nums">{{ from }}–{{ to }} sur <strong class="text-on-surface">{{ total }}</strong> {{ label }}</span>
            <label v-if="showPerPage" class="inline-flex items-center gap-1.5">
                <span class="hidden sm:inline">Par page</span>
                <select
                    class="py-1 pl-2 pr-7 text-[11px] border border-outline-variant rounded-lg bg-white"
                    :value="perPage"
                    aria-label="Lignes par page"
                    @change="emit('per-page', Number($event.target.value))"
                >
                    <option v-for="size in perPageOptions" :key="size" :value="size">{{ size }}</option>
                </select>
            </label>
        </div>

        <div v-if="lastPage > 1" class="flex items-center gap-1">
            <button
                type="button"
                class="w-8 h-8 inline-flex items-center justify-center rounded-lg border border-outline-variant hover:bg-surface-container-low disabled:opacity-40 disabled:cursor-not-allowed"
                :disabled="currentPage <= 1"
                aria-label="Page précédente"
                @click="go(currentPage - 1)"
            >
                <span class="material-symbols-outlined text-[18px]">chevron_left</span>
            </button>
            <template v-for="(page, index) in pages" :key="page ?? `gap-${index}`">
                <span v-if="page === null" class="w-8 text-center text-xs text-on-surface-variant" aria-hidden="true">…</span>
                <button
                    v-else
                    type="button"
                    class="min-w-8 h-8 px-2 inline-flex items-center justify-center rounded-lg text-xs font-bold tabular-nums border transition-colors"
                    :class="page === currentPage ? 'bg-primary text-on-primary border-primary' : 'border-outline-variant hover:bg-surface-container-low'"
                    :aria-current="page === currentPage ? 'page' : undefined"
                    @click="go(page)"
                >
                    {{ page }}
                </button>
            </template>
            <button
                type="button"
                class="w-8 h-8 inline-flex items-center justify-center rounded-lg border border-outline-variant hover:bg-surface-container-low disabled:opacity-40 disabled:cursor-not-allowed"
                :disabled="currentPage >= lastPage"
                aria-label="Page suivante"
                @click="go(currentPage + 1)"
            >
                <span class="material-symbols-outlined text-[18px]">chevron_right</span>
            </button>
        </div>
    </nav>
</template>
