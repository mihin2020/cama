<script setup>
import CamaIcon from '@/Components/CamaIcon.vue';
import { useSiteLogo } from '@/Composables/useSiteLogo';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    status: { type: Number, required: true },
    espace: { type: String, default: 'site' },
    actions: { type: Array, default: () => [] },
});

const siteLogo = useSiteLogo();

const MESSAGES = {
    403: {
        icon: 'lock',
        title: 'Accès refusé',
        text: "Vous n'avez pas les droits nécessaires pour accéder à cette page.",
        admin: "Votre rôle ne comporte pas la permission requise pour cette page. Si vous pensez qu'il s'agit d'une erreur, contactez un administrateur de la plateforme.",
    },
    404: {
        icon: 'travel_explore',
        title: 'Page introuvable',
        text: "La page que vous cherchez n'existe pas, a été déplacée ou n'est plus disponible. Vérifiez l'adresse saisie.",
    },
    419: {
        icon: 'schedule',
        title: 'Session expirée',
        text: 'Votre session a expiré pour des raisons de sécurité. Rechargez la page puis recommencez.',
    },
    429: {
        icon: 'hourglass_top',
        title: 'Trop de tentatives',
        text: 'Vous avez effectué trop de demandes en peu de temps. Patientez quelques minutes avant de réessayer.',
    },
    500: {
        icon: 'error',
        title: 'Erreur interne',
        text: "Une erreur inattendue s'est produite de notre côté. L'incident a été enregistré ; réessayez dans quelques instants.",
    },
    503: {
        icon: 'construction',
        title: 'Service en maintenance',
        text: 'La plateforme est momentanément indisponible pour maintenance. Merci de revenir un peu plus tard.',
    },
};

const message = computed(() => {
    const entry = MESSAGES[props.status] ?? MESSAGES[500];

    return {
        ...entry,
        text: props.espace === 'admin' && entry.admin ? entry.admin : entry.text,
    };
});

const canGoBack = computed(() => typeof window !== 'undefined' && window.history.length > 1);

function goBack() {
    window.history.back();
}
</script>

<template>
    <Head :title="`${status} · ${message.title}`" />

    <main class="flex min-h-screen flex-col items-center justify-center bg-background px-4 py-12 text-on-surface">
        <img :src="siteLogo" alt="Logo CAMA" class="mb-8 h-16 w-16 object-contain" />

        <section
            class="w-full max-w-lg rounded-2xl border border-outline-variant/40 bg-surface-container-lowest p-8 text-center shadow-sm"
            aria-labelledby="error-title"
        >
            <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-primary/10 text-primary">
                <CamaIcon :name="message.icon" size="lg" />
            </div>

            <p class="font-headline text-5xl font-extrabold tracking-tight text-primary">{{ status }}</p>
            <h1 id="error-title" class="mt-2 font-headline text-2xl font-bold">{{ message.title }}</h1>
            <p class="mt-3 text-sm leading-relaxed text-on-surface-variant">{{ message.text }}</p>

            <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row sm:flex-wrap">
                <a
                    v-for="action in actions"
                    :key="action.href"
                    :href="action.href"
                    :class="action.primary
                        ? 'bg-primary text-on-primary hover:bg-primary/90'
                        : 'border border-outline-variant text-on-surface hover:bg-surface-container'"
                    class="inline-flex items-center justify-center rounded-lg px-5 py-2.5 text-sm font-semibold transition"
                >
                    {{ action.label }}
                </a>
                <button
                    v-if="canGoBack"
                    type="button"
                    class="inline-flex items-center justify-center rounded-lg border border-outline-variant px-5 py-2.5 text-sm font-semibold text-on-surface transition hover:bg-surface-container"
                    @click="goBack"
                >
                    Page précédente
                </button>
            </div>
        </section>

        <p class="mt-6 text-xs text-on-surface-variant">Caisse d'Assurance Maladie des Armées (CAMA)</p>
    </main>
</template>
