<script setup>
/**
 * Cases des permissions (fiche d'un rôle et fiche d'un compte interne).
 *
 * Chaque case se coche et se décoche librement. « Accès complet au studio de contenu » est un
 * raccourci : il coche toutes les permissions du studio (sauf le HTML libre) ; décocher l'une
 * d'elles le décoche. Ce qui est coché est exactement ce que le compte pourra faire.
 */
import { computed } from 'vue';

const props = defineProps({
    catalog: { type: Array, required: true },
    modelValue: { type: Array, default: () => [] },
    // (key) => '' si la case est modifiable, sinon la raison (affichée au survol).
    disabledReason: { type: Function, default: () => '' },
    // Toutes les cases cochées et figées (ex. rôle Administrateur).
    allLocked: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const CMS_FULL = 'cms.manage';
const CMS_EXPLICIT_ONLY = ['cms.html'];

const allKeys = computed(() => props.catalog.flatMap((group) => group.items.map((item) => item.key)));
const studioKeys = computed(() => allKeys.value.filter(
    (key) => key.startsWith('cms.') && key !== CMS_FULL && !CMS_EXPLICIT_ONLY.includes(key),
));

function isChecked(key) {
    return props.allLocked || props.modelValue.includes(key);
}

function reason(key) {
    if (props.allLocked) return "L'administrateur a toujours toutes les permissions.";
    return props.disabledReason(key) || '';
}

function normalize(list) {
    let next = [...new Set(list)];
    const studioComplete = studioKeys.value.every((key) => next.includes(key));
    // Le raccourci reflète exactement l'état des cases du studio.
    if (studioComplete && !next.includes(CMS_FULL) && !reason(CMS_FULL)) next.push(CMS_FULL);
    if (!studioComplete) next = next.filter((key) => key !== CMS_FULL);
    return next;
}

function toggle(key) {
    if (reason(key)) return;
    const checked = props.modelValue.includes(key);
    let next = [...props.modelValue];

    if (key === CMS_FULL) {
        const editable = studioKeys.value.filter((k) => !reason(k));
        next = checked
            ? next.filter((k) => k !== CMS_FULL && !editable.includes(k))
            : [...next, CMS_FULL, ...editable];
    } else {
        next = checked ? next.filter((k) => k !== key) : [...next, key];
    }

    emit('update:modelValue', normalize(next));
}

function groupKeys(group) {
    return group.items.map((item) => item.key).filter((key) => !reason(key));
}

function groupAllChecked(group) {
    const keys = groupKeys(group);
    return keys.length > 0 && keys.every((key) => props.modelValue.includes(key));
}

function toggleGroup(group) {
    const keys = groupKeys(group);
    const next = groupAllChecked(group)
        ? props.modelValue.filter((key) => !keys.includes(key))
        : [...props.modelValue, ...keys];
    emit('update:modelValue', normalize(next));
}
</script>

<template>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
        <div v-for="group in catalog" :key="group.group" class="border border-outline-variant rounded-lg p-3">
            <div class="flex items-center justify-between gap-2 mb-1.5">
                <p class="text-[11px] font-bold uppercase text-on-surface-variant">{{ group.group }}</p>
                <button
                    v-if="!allLocked && group.items.length > 1 && groupKeys(group).length"
                    class="text-[10px] font-bold text-primary hover:underline"
                    type="button"
                    @click="toggleGroup(group)"
                >
                    {{ groupAllChecked(group) ? 'Tout décocher' : 'Tout cocher' }}
                </button>
            </div>
            <div class="space-y-1.5">
                <label
                    v-for="item in group.items"
                    :key="item.key"
                    class="flex items-start gap-2 text-xs"
                    :class="reason(item.key) ? 'opacity-55 cursor-not-allowed' : 'cursor-pointer'"
                    :title="reason(item.key)"
                >
                    <input
                        type="checkbox"
                        class="mt-0.5 rounded border-outline-variant text-primary disabled:cursor-not-allowed"
                        :checked="isChecked(item.key)"
                        :disabled="!!reason(item.key)"
                        @change="toggle(item.key)"
                    />
                    <span>
                        {{ item.label }}
                        <span v-if="item.key === 'cms.manage'" class="block text-[10px] text-on-surface-variant">Coche d'un coup tout le studio, sauf le HTML libre.</span>
                        <span v-if="reason(item.key) && !allLocked" class="block text-[10px] text-on-surface-variant">{{ reason(item.key) }}</span>
                    </span>
                </label>
            </div>
        </div>
    </div>
</template>
