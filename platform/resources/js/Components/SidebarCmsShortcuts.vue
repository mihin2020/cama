<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useAdminPermissions } from '@/Composables/useAdminPermissions';

defineProps({
    activeNav: { type: String, default: '' },
});

const page = usePage();
const { can } = useAdminPermissions();
const app = computed(() => page.props.app ?? {});
const contactCount = computed(() => Number(app.value?.cmsNewContactCount ?? 0));
const newsletterCount = computed(() => Number(app.value?.cmsActiveNewsletterCount ?? 0));
</script>

<template>
    <div class="flex items-center gap-1 shrink-0">
        <Link
            v-show="can('cms.contacts')"
            :href="route('admin.cms.contacts')"
            class="sidebar-header-icon relative w-9 h-9 flex items-center justify-center"
            :class="{ 'is-active': activeNav === 'contacts' }"
            title="Messages contact"
            aria-label="Messages contact"
        >
            <span class="material-symbols-outlined text-[20px]">contact_mail</span>
            <span
                v-if="contactCount > 0"
                class="absolute -top-0.5 -right-0.5 bg-primary text-on-primary text-[9px] font-bold rounded-full min-w-[16px] h-4 px-1 flex items-center justify-center"
            >{{ contactCount > 99 ? '99+' : contactCount }}</span>
        </Link>
        <Link
            v-show="can('cms.newsletter')"
            :href="route('admin.cms.newsletter')"
            class="sidebar-header-icon relative w-9 h-9 flex items-center justify-center"
            :class="{ 'is-active': activeNav === 'newsletter' }"
            title="Newsletter"
            aria-label="Newsletter"
        >
            <span class="material-symbols-outlined text-[20px]">mark_email_read</span>
            <span
                v-if="newsletterCount > 0"
                class="absolute -top-0.5 -right-0.5 bg-primary text-on-primary text-[9px] font-bold rounded-full min-w-[16px] h-4 px-1 flex items-center justify-center"
            >{{ newsletterCount > 99 ? '99+' : newsletterCount }}</span>
        </Link>
    </div>
</template>
