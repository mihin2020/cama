<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useAdminPermissions } from '@/Composables/useAdminPermissions';

import { useAdminDossierBadge } from '@/Composables/useAdminDossierBadge';

defineProps({
    activeNav: { type: String, default: '' },
});

const page = usePage();
const { can } = useAdminPermissions();
const { badgeCount: dossiersBadge } = useAdminDossierBadge();
const app = computed(() => page.props.app ?? {});
const contactCount = computed(() => Number(app.value?.cmsNewContactCount ?? 0));
const newsletterCount = computed(() => Number(app.value?.cmsActiveNewsletterCount ?? 0));
</script>

<template>
    <div class="flex items-center gap-1">
        <Link
            v-show="can('dossiers.view')"
            :href="route('admin.dossiers')"
            class="relative w-9 h-9 flex items-center justify-center rounded-full transition-colors hover:bg-black/5"
            :class="activeNav === 'dossiers' ? 'bg-primary/10 text-primary' : 'text-on-surface-variant hover:text-on-surface'"
            title="Dossiers à traiter"
            aria-label="Dossiers à traiter"
        >
            <span class="material-symbols-outlined text-[22px]">folder_shared</span>
            <span
                v-if="dossiersBadge > 0"
                class="absolute top-0 right-0 bg-primary text-on-primary text-[9px] font-bold rounded-full min-w-[16px] h-4 px-1 flex items-center justify-center ring-2 ring-white"
            >{{ dossiersBadge > 99 ? '99+' : dossiersBadge }}</span>
        </Link>
        <Link
            v-show="can('cms.contacts')"
            :href="route('admin.cms.contacts')"
            class="relative w-9 h-9 flex items-center justify-center rounded-full transition-colors hover:bg-black/5"
            :class="activeNav === 'contacts' ? 'bg-primary/10 text-primary' : 'text-on-surface-variant hover:text-on-surface'"
            title="Messages contact"
            aria-label="Messages contact"
        >
            <span class="material-symbols-outlined text-[22px]">contact_mail</span>
            <span
                v-if="contactCount > 0"
                class="absolute top-0 right-0 bg-primary text-on-primary text-[9px] font-bold rounded-full min-w-[16px] h-4 px-1 flex items-center justify-center ring-2 ring-white"
            >{{ contactCount > 99 ? '99+' : contactCount }}</span>
        </Link>
        <Link
            v-show="can('cms.newsletter')"
            :href="route('admin.cms.newsletter')"
            class="relative w-9 h-9 flex items-center justify-center rounded-full transition-colors hover:bg-black/5"
            :class="activeNav === 'newsletter' ? 'bg-primary/10 text-primary' : 'text-on-surface-variant hover:text-on-surface'"
            title="Newsletter"
            aria-label="Newsletter"
        >
            <span class="material-symbols-outlined text-[22px]">mark_email_read</span>
            <span
                v-if="newsletterCount > 0"
                class="absolute top-0 right-0 bg-primary text-on-primary text-[9px] font-bold rounded-full min-w-[16px] h-4 px-1 flex items-center justify-center ring-2 ring-white"
            >{{ newsletterCount > 99 ? '99+' : newsletterCount }}</span>
        </Link>
    </div>
</template>
