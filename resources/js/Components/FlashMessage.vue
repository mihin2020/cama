<script setup>
import { currentToast, dismissToast, notify, toastHosts as hosts } from '@/Composables/useFlashToast';
import { usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, watch } from 'vue';

/**
 * Affiche le toast commun et y envoie les messages flash du serveur (success / warning / error).
 * Placé dans chaque mise en page ; si une page en contient un second, seul le premier monté s'affiche.
 */
const page = usePage();

watch(
    () => page.props.flash,
    (flash) => {
        if (!flash) return;
        if (flash.error) notify(flash.error, 'error');
        else if (flash.warning) notify(flash.warning, 'warning');
        else if (flash.success) notify(flash.success, 'success');
    },
    { immediate: true },
);

const hostId = Symbol('toast-host');
onMounted(() => hosts.value.push(hostId));
onBeforeUnmount(() => { hosts.value = hosts.value.filter((id) => id !== hostId); });
const isPrimary = computed(() => hosts.value[0] === hostId);
</script>

<template>
    <Teleport to="body">
        <div v-if="isPrimary" class="fixed bottom-6 right-4 left-4 sm:left-auto sm:right-6 z-[100] flex justify-end pointer-events-none" role="status" aria-live="polite">
            <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0 translate-y-2"
                leave-active-class="transition duration-150 ease-in"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="currentToast"
                    :key="currentToast.id"
                    class="pointer-events-auto max-w-md bg-on-background text-white pl-4 pr-2 py-3 rounded-lg shadow-xl flex items-start gap-2 text-sm font-semibold"
                >
                    <span class="material-symbols-outlined shrink-0" :class="{ 'text-red-300': currentToast.kind === 'error', 'text-amber-300': currentToast.kind === 'warning' }">{{ currentToast.icon }}</span>
                    <span class="flex-1 break-words">{{ currentToast.text }}</span>
                    <button type="button" class="shrink-0 opacity-70 hover:opacity-100" aria-label="Fermer le message" @click="dismissToast">
                        <span class="material-symbols-outlined text-[18px]">close</span>
                    </button>
                </div>
            </Transition>
        </div>
    </Teleport>
</template>
