import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';

const viewedKey = (adminId) => `cama_admin_dossiers_vus_${adminId}`;

const viewedFamilies = ref(new Set());
let loadedForAdmin = null;

function loadViewed(adminId) {
    if (!adminId) {
        viewedFamilies.value = new Set();
        return;
    }
    if (loadedForAdmin === adminId) {
        return;
    }
    loadedForAdmin = adminId;
    try {
        const raw = localStorage.getItem(viewedKey(adminId));
        viewedFamilies.value = new Set(raw ? JSON.parse(raw) : []);
    } catch {
        viewedFamilies.value = new Set();
    }
}

function saveViewed(adminId) {
    if (!adminId) return;
    localStorage.setItem(viewedKey(adminId), JSON.stringify([...viewedFamilies.value]));
}

export function useAdminDossierBadge() {
    const page = usePage();
    const adminId = computed(() => page.props.auth?.admin?.id ?? null);
    const pendingIds = computed(() => page.props.app?.adminPendingDossierFamilies ?? []);

    loadViewed(adminId.value);

    const badgeCount = computed(() => {
        loadViewed(adminId.value);
        const pending = pendingIds.value.map((id) => Number(id));
        return pending.filter((id) => !viewedFamilies.value.has(id)).length;
    });

    function markFamilyViewed(assureId) {
        if (!assureId || !adminId.value) return;
        loadViewed(adminId.value);
        const next = new Set(viewedFamilies.value);
        next.add(Number(assureId));
        viewedFamilies.value = next;
        saveViewed(adminId.value);
    }

    return { badgeCount, markFamilyViewed };
}
