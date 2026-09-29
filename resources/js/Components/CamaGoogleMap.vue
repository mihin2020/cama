<script setup>
import { loadGoogleMaps } from '@/Composables/useGoogleMaps';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    apiKey: { type: String, default: '' },
    points: { type: Array, default: () => [] },
    height: { type: String, default: '380px' },
    defaultCenter: { type: Object, default: () => ({ lat: 12.3714, lng: -1.5197 }) },
    defaultZoom: { type: Number, default: 6 },
    focusPoint: { type: Object, default: null },
});

const mapContainer = ref(null);
const loadError = ref(null);
const loading = ref(true);

let mapInstance = null;
let infoWindow = null;
const markers = [];

const MARKER_COLORS = {
    'Siège CAMA': '#1a3a6b',
    'Antenne CAMA': '#5c403f',
    'Centre de santé': '#9e001f',
    'Partenaire': '#006e27',
    default: '#5c403f',
};

function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

function pinIcon(color) {
    const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="36" height="48" viewBox="0 0 36 48">
        <path fill="${color}" stroke="#fff" stroke-width="2" d="M18 0C8.06 0 0 8.06 0 18c0 13.5 18 30 18 30s18-16.5 18-30C36 8.06 27.94 0 18 0z"/>
        <circle fill="#fff" cx="18" cy="18" r="7"/>
    </svg>`;
    return {
        url: `data:image/svg+xml;charset=UTF-8,${encodeURIComponent(svg)}`,
        scaledSize: new google.maps.Size(36, 48),
        anchor: new google.maps.Point(18, 48),
    };
}

function buildInfoContent(point) {
    const gmapsUrl = point.mapsUrl
        || (typeof point.lat === 'number' && typeof point.lon === 'number'
            ? `https://www.google.com/maps/search/?api=1&query=${point.lat},${point.lon}`
            : null);

    const imageBlock = point.imageSrc
        ? `<img src="${esc(point.imageSrc)}" alt="" style="width:100%;height:90px;object-fit:cover;border-radius:8px;margin-bottom:8px;" />`
        : '';

    const descBlock = point.description
        ? `<p style="margin:0 0 8px;color:#5c403f;font-size:11px;line-height:1.4;">${esc(point.description)}</p>`
        : '';

    const cityBlock = point.city
        ? `<div style="display:flex;align-items:center;gap:4px;color:#5c403f;font-size:11px;margin-bottom:6px;">${esc(point.city)}</div>`
        : '';

    const gmapsBtn = gmapsUrl
        ? `<a href="${esc(gmapsUrl)}" target="_blank" rel="noopener" style="display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:600;color:#1a73e8;text-decoration:none;">Ouvrir dans Google Maps</a>`
        : '';

    return `
        <div style="min-width:200px;max-width:260px;font-family:Roboto,Arial,sans-serif;padding:4px;">
            ${imageBlock}
            <div style="font-weight:700;font-size:14px;color:#202124;margin-bottom:4px;">${esc(point.name)}</div>
            <div style="font-size:11px;color:#5f6368;margin-bottom:6px;">${esc(point.type)}</div>
            ${cityBlock}
            ${descBlock}
            ${gmapsBtn}
        </div>`;
}

function clearMarkers() {
    markers.forEach(m => m.setMap(null));
    markers.length = 0;
}

function renderMarkers() {
    if (!mapInstance || !window.google?.maps) return;

    clearMarkers();

    const valid = props.points.filter(p => typeof p.lat === 'number' && typeof p.lon === 'number');

    valid.forEach((point) => {
        const color = MARKER_COLORS[point.type] || MARKER_COLORS.default;
        const marker = new google.maps.Marker({
            position: { lat: point.lat, lng: point.lon },
            map: mapInstance,
            title: point.name,
            icon: pinIcon(color),
            animation: google.maps.Animation.DROP,
        });

        marker.addListener('click', () => {
            infoWindow.setContent(buildInfoContent(point));
            infoWindow.open({ map: mapInstance, anchor: marker });
        });

        markers.push(marker);
    });

    if (valid.length === 1) {
        mapInstance.setCenter({ lat: valid[0].lat, lng: valid[0].lon });
        mapInstance.setZoom(14);
    } else if (valid.length > 1) {
        const bounds = new google.maps.LatLngBounds();
        valid.forEach(p => bounds.extend({ lat: p.lat, lng: p.lon }));
        mapInstance.fitBounds(bounds, { top: 40, right: 40, bottom: 40, left: 40 });
    } else {
        mapInstance.setCenter(props.defaultCenter);
        mapInstance.setZoom(props.defaultZoom);
    }
}

async function initMap() {
    if (!props.apiKey) {
        loadError.value = 'missing_key';
        loading.value = false;
        return;
    }

    try {
        await loadGoogleMaps(props.apiKey);

        mapInstance = new google.maps.Map(mapContainer.value, {
            center: props.defaultCenter,
            zoom: props.defaultZoom,
            mapTypeId: 'roadmap',
            mapTypeControl: true,
            mapTypeControlOptions: {
                style: google.maps.MapTypeControlStyle.HORIZONTAL_BAR,
                position: google.maps.ControlPosition.TOP_RIGHT,
            },
            streetViewControl: true,
            fullscreenControl: true,
            zoomControl: true,
            gestureHandling: 'cooperative',
            styles: [],
        });

        infoWindow = new google.maps.InfoWindow();
        renderMarkers();
        loading.value = false;
    } catch (err) {
        loadError.value = 'load_failed';
        loading.value = false;
    }
}

onMounted(initMap);

watch(() => props.points, renderMarkers, { deep: true });

watch(
    () => props.focusPoint,
    (point) => {
        if (!mapInstance || !point || typeof point.lat !== 'number' || typeof point.lon !== 'number') return;
        mapInstance.panTo({ lat: point.lat, lng: point.lon });
        mapInstance.setZoom(point.zoom ?? 14);
    },
    { deep: true },
);

onBeforeUnmount(() => {
    clearMarkers();
    mapInstance = null;
    infoWindow = null;
});
</script>

<template>
    <div class="cama-google-map rounded-xl overflow-hidden border border-outline-variant relative bg-surface-container-high">
        <div v-if="loading" class="absolute inset-0 flex items-center justify-center z-10 bg-surface-container-high">
            <div class="flex flex-col items-center gap-2 text-on-surface-variant">
                <span class="material-symbols-outlined text-[28px] animate-pulse">map</span>
                <span class="text-xs font-semibold">Chargement de Google Maps…</span>
            </div>
        </div>

        <div v-if="loadError === 'missing_key'" class="flex flex-col items-center justify-center gap-3 p-8 text-center" :style="{ height }">
            <span class="material-symbols-outlined text-[40px] text-on-surface-variant">key</span>
            <p class="text-sm font-semibold text-on-surface">Clé Google Maps requise</p>
            <p class="text-xs text-on-surface-variant max-w-sm">
                Ajoutez <code class="bg-surface-container-high px-1 rounded">GOOGLE_MAPS_API_KEY</code> dans votre fichier <code class="bg-surface-container-high px-1 rounded">.env</code>, puis activez l'API « Maps JavaScript API » sur Google Cloud Console.
            </p>
        </div>

        <div v-else-if="loadError === 'load_failed'" class="flex flex-col items-center justify-center gap-3 p-8 text-center" :style="{ height }">
            <span class="material-symbols-outlined text-[40px] text-error">error</span>
            <p class="text-sm font-semibold text-on-surface">Google Maps indisponible</p>
            <p class="text-xs text-on-surface-variant max-w-sm">Vérifiez que votre clé API est valide et que l'API Maps JavaScript est activée.</p>
        </div>

        <div v-show="!loadError" ref="mapContainer" :style="{ height, width: '100%' }" />
    </div>
</template>

<style>
.cama-google-map .gm-style .gm-style-iw-c {
    border-radius: 12px !important;
    padding: 0 !important;
}
.cama-google-map .gm-style .gm-style-iw-d {
    overflow: auto !important;
}
</style>
