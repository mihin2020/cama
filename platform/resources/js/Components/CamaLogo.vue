<script setup>
defineProps({
    size: {
        type: String,
        default: 'md',
    },
    showSubtitle: {
        type: Boolean,
        default: true,
    },
});

const sizes = {
    sm: 'h-8 w-8',
    md: 'h-10 w-10',
    lg: 'h-14 w-14',
};
</script>

<template>
    <div class="flex items-center gap-3">
        <img
            :class="[sizes[size], 'object-contain']"
            src="/images/logo_cama.png"
            alt="Logo CAMA"
        />
        <div v-if="showSubtitle" class="leading-tight">
            <span class="font-headline font-extrabold tracking-tight text-on-surface text-lg">CAMA</span>
            <span class="block text-[9px] font-normal tracking-wide text-on-surface-variant uppercase">Caisse d'Assurance Maladie des Armées</span>
        </div>
    </div>
</template>
