<script setup>
defineProps({
    // Nom de route Ziggy du module lié (ex. 'admin.cms.chiffres_cles').
    routeName: { type: String, required: true },
    // Libellé affiché sur le bouton (ex. 'Chiffres clés').
    moduleLabel: { type: String, required: true },
});
</script>

<template>
    <div class="rounded-lg bg-primary/5 border border-primary/20 p-3 text-[11px] text-on-surface-variant space-y-2">
        <p><slot /></p>
        <a
            :href="route(routeName)"
            target="_blank"
            rel="noopener"
            class="inline-flex items-center gap-1.5 font-bold text-primary hover:underline"
        >
            <span class="material-symbols-outlined text-[15px]">open_in_new</span>
            Gérer « {{ moduleLabel }} » dans un nouvel onglet
        </a>
    </div>
</template>
