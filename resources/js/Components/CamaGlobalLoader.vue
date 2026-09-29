<script setup>
import { onMounted, onUnmounted, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import CamaSpinner from '@/Components/CamaSpinner.vue';

const visible = ref(false);
let showTimer = null;
let hideTimer = null;
let activeVisits = 0;

const MUTATING_METHODS = new Set(['post', 'put', 'patch', 'delete']);

function isMutating(event) {
    const method = (event?.detail?.visit?.method || event?.detail?.method || '').toLowerCase();
    return MUTATING_METHODS.has(method);
}

function clearTimers() {
    if (showTimer) {
        clearTimeout(showTimer);
        showTimer = null;
    }
    if (hideTimer) {
        clearTimeout(hideTimer);
        hideTimer = null;
    }
}

function onStart(event) {
    if (!isMutating(event)) {
        return;
    }
    activeVisits += 1;
    clearTimers();

    showTimer = setTimeout(() => {
        visible.value = true;
    }, 400);
}

function onFinish(event) {
    if (!isMutating(event)) {
        return;
    }
    activeVisits = Math.max(0, activeVisits - 1);

    if (activeVisits > 0) {
        return;
    }

    clearTimers();
    hideTimer = setTimeout(() => {
        visible.value = false;
    }, 120);
}

let removeStart;
let removeFinish;
let removeError;
let removeCancel;

onMounted(() => {
    removeStart = router.on('start', onStart);
    removeFinish = router.on('finish', onFinish);
    removeError = router.on('error', onFinish);
    removeCancel = router.on('cancel', onFinish);
});

onUnmounted(() => {
    clearTimers();
    removeStart?.();
    removeFinish?.();
    removeError?.();
    removeCancel?.();
});
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition-opacity duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="visible"
                class="fixed inset-0 z-[200] flex items-center justify-center bg-black/25 backdrop-blur-[1px]"
                aria-live="polite"
                aria-busy="true"
            >
                <div class="bg-white rounded-2xl shadow-2xl px-6 py-5 flex flex-col items-center gap-3 min-w-[180px]">
                    <CamaSpinner size="lg" />
                    <p class="text-xs font-semibold text-on-surface">Chargement…</p>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
