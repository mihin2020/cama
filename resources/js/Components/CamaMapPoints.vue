<script setup>
import { themeColor } from '@/Utils/siteTheme';
import { computed } from 'vue';

/**
 * Liste des lieux (siège, antennes, partenaires) avec un lien « Voir sur la carte ».
 * Aucun script ni aucune image n'est chargé depuis un site tiers : la carte s'ouvre seulement si
 * l'utilisateur clique sur le lien.
 */
const props = defineProps({
    points: { type: Array, default: () => [] },
    height: { type: String, default: '380px' },
    focusPoint: { type: Object, default: null },
});

const MARKER_TOKENS = {
    'Antenne CAMA': 'on-surface-variant',
    'Centre de santé': 'primary',
    'Partenaire': 'secondary',
    default: 'on-surface-variant',
};

function pinColor(type) {
    if (type === 'Siège CAMA') return '#1a3a6b';
    return themeColor(MARKER_TOKENS[type] ?? MARKER_TOKENS.default);
}

function mapLink(point) {
    if (point.mapsUrl) return point.mapsUrl;
    if (typeof point.lat === 'number' && typeof point.lon === 'number') {
        return `https://www.google.com/maps/search/?api=1&query=${point.lat},${point.lon}`;
    }
    const query = [point.name, point.city].filter(Boolean).join(' ');
    return query ? `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(query)}` : null;
}

const isFocused = (point) => props.focusPoint
    && typeof props.focusPoint.lat === 'number'
    && props.focusPoint.lat === point.lat
    && props.focusPoint.lon === point.lon;

const items = computed(() => props.points.map((point) => ({ point, link: mapLink(point) })));
</script>

<template>
    <div class="rounded-xl border border-outline-variant bg-white overflow-y-auto divide-y divide-outline-variant" :style="{ maxHeight: height }">
        <p v-if="!items.length" class="p-6 text-center text-sm text-on-surface-variant">Aucun lieu à afficher.</p>
        <div
            v-for="({ point, link }, i) in items"
            :key="point.id ?? i"
            class="flex items-start gap-3 p-4"
            :class="isFocused(point) ? 'bg-primary/5' : ''"
        >
            <span class="material-symbols-outlined text-[26px] shrink-0" :style="{ color: pinColor(point.type) }" aria-hidden="true">location_on</span>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-bold text-on-surface break-words">{{ point.name }}</p>
                <p v-if="point.type || point.city" class="text-xs text-on-surface-variant">{{ [point.type, point.city].filter(Boolean).join(' · ') }}</p>
                <p v-if="point.description" class="text-xs text-on-surface-variant mt-1 break-words">{{ point.description }}</p>
            </div>
            <a
                v-if="link"
                :href="link"
                target="_blank"
                rel="noopener noreferrer"
                class="shrink-0 inline-flex items-center gap-1 text-xs font-bold text-primary hover:underline"
            >
                <span class="material-symbols-outlined text-[16px]">map</span> Voir sur la carte
            </a>
        </div>
    </div>
</template>
