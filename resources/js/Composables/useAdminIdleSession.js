import { router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

// Interactions qui comptent comme « activité » (en plus des changements de page).
const ACTIVITY_EVENTS = ['mousedown', 'mousemove', 'keydown', 'scroll', 'touchstart'];
// Au plus un ping serveur toutes les 30 s, et seulement si l'admin a interagi.
const PING_EVERY_MS = 30_000;
const WARNING_SECONDS = 60;

// État partagé par le layout et la page profil (un seul minuteur pour toute l'appli).
const policy = ref(null);
const now = ref(Date.now());
const lastActivityAt = ref(Date.now());
const startedAt = ref(Date.now());

let users = 0;
let ticker = null;
let interacted = false;
let pinging = false;
let expiring = false;

function sync(state) {
    policy.value = state ?? null;
    if (!state) return;

    // Décalage entre l'horloge du serveur et celle du poste.
    const offset = Date.now() - state.serverTime * 1000;
    lastActivityAt.value = state.lastActivity * 1000 + offset;
    startedAt.value = state.startedAt * 1000 + offset;
    expiring = false;
}

const remainingSeconds = computed(() => {
    if (!policy.value) return 0;
    const left = policy.value.idleTimeout * 1000 - (now.value - lastActivityAt.value);
    return Math.max(0, Math.ceil(left / 1000));
});

const elapsedSeconds = computed(() => Math.max(0, Math.floor((now.value - startedAt.value) / 1000)));

function markActivity() {
    interacted = true;
}

async function ping() {
    if (pinging) return;
    pinging = true;
    interacted = false;
    try {
        const { data } = await axios.post(route('admin.session.ping'));
        sync(data);
    } catch (error) {
        if ([401, 419].includes(error.response?.status)) {
            router.visit(route('admin.login'));
        }
    } finally {
        pinging = false;
    }
}

// Option désactivée par défaut côté admin ; jamais appliquée à un appareil mémorisé.
const active = computed(() => !!policy.value?.enabled && !policy.value.remembered);

function tick() {
    now.value = Date.now();
    if (!active.value) return;

    if (interacted && now.value - lastActivityAt.value >= PING_EVERY_MS) {
        ping();
    }

    // Échéance atteinte : le serveur tranche (déconnexion, ou session prolongée
    // si l'admin est resté actif dans un autre onglet).
    if (remainingSeconds.value <= 0 && !expiring) {
        expiring = true;
        router.reload();
    }
}

export function formatDuration(totalSeconds) {
    const h = Math.floor(totalSeconds / 3600);
    const m = String(Math.floor((totalSeconds % 3600) / 60)).padStart(2, '0');
    const s = String(totalSeconds % 60).padStart(2, '0');
    return h > 0 ? `${h}:${m}:${s}` : `${m}:${s}`;
}

export function useAdminIdleSession() {
    const page = usePage();

    watch(() => page.props.adminSession, sync, { immediate: true });

    onMounted(() => {
        if (users++ > 0) return;
        ACTIVITY_EVENTS.forEach((name) => window.addEventListener(name, markActivity, { passive: true }));
        ticker = window.setInterval(tick, 1000);
    });

    onUnmounted(() => {
        if (--users > 0) return;
        ACTIVITY_EVENTS.forEach((name) => window.removeEventListener(name, markActivity));
        window.clearInterval(ticker);
        ticker = null;
    });

    return {
        policy,
        remainingSeconds,
        elapsedSeconds,
        active,
        showWarning: computed(() => active.value && remainingSeconds.value <= WARNING_SECONDS),
        stayConnected: ping,
    };
}
