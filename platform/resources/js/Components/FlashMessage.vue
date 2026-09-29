<script setup>
import { usePage } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

const page = usePage();
const message = computed(() => page.props.flash?.success || page.props.flash?.warning || page.props.flash?.error);
const type = computed(() => {
    if (page.props.flash?.success) return 'success';
    if (page.props.flash?.warning) return 'warning';
    return 'error';
});

let timer;
watch(message, (val) => {
    clearTimeout(timer);
    if (val) timer = setTimeout(() => {
        page.props.flash.success = null;
        page.props.flash.warning = null;
        page.props.flash.error = null;
    }, 5000);
});
</script>

<template>
    <div
        v-if="message"
        class="mb-4 rounded-lg px-4 py-3 text-sm font-medium"
        :class="{
            'bg-green-50 text-green-900 border border-green-200': type === 'success',
            'bg-amber-50 text-amber-900 border border-amber-200': type === 'warning',
            'bg-red-50 text-red-900 border border-red-200': type === 'error',
        }"
    >
        {{ message }}
    </div>
</template>
