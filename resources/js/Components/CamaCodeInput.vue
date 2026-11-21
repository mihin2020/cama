<script setup>
/**
 * Saisie d'un code à usage unique (2FA, vérification d'e-mail) : une case par caractère.
 * Chiffres (0-9) et lettres (A-Z), convertis en majuscules ; les autres caractères (espaces,
 * tirets, symboles) sont ignorés. Le collage et le remplissage automatique du code
 * (one-time-code) répartissent les caractères dans les cases.
 */
import { computed, nextTick, ref } from 'vue';

const props = defineProps({
    modelValue: { type: String, default: '' },
    length: { type: Number, default: 6 },
    invalid: { type: Boolean, default: false },
    // Classes des cases (chaque écran garde son style).
    boxClass: { type: String, default: 'w-11 h-14 sm:w-12 sm:h-16 text-xl sm:text-2xl' },
});

const emit = defineEmits(['update:modelValue', 'complete']);

// Mêmes caractères que le serveur (OneTimeCodeService::ALPHABET).
const NOT_ALLOWED = /[^A-Z0-9]/g;
const inputs = ref([]);

const chars = computed(() => Array.from({ length: props.length }, (_, i) => props.modelValue[i] ?? ''));

function clean(value) {
    return String(value ?? '').toUpperCase().replace(NOT_ALLOWED, '');
}

function update(list, focusIndex) {
    const value = list.join('').slice(0, props.length);
    emit('update:modelValue', value);
    nextTick(() => inputs.value[Math.min(focusIndex, props.length - 1)]?.focus());
    if (value.length === props.length) emit('complete', value);
}

function fillFrom(index, text) {
    const list = [...chars.value];
    let position = index;
    for (const char of text) {
        if (position >= props.length) break;
        list[position] = char;
        position += 1;
    }
    // Pas de trou : les caractères restent collés au début.
    update(list.filter(Boolean), position);
}

function onInput(index, event) {
    const value = clean(event.target.value);
    if (!value) {
        event.target.value = chars.value[index];
        return;
    }
    fillFrom(index, value.length > 1 ? value : value.slice(-1));
}

function onKeydown(index, event) {
    if (event.key !== 'Backspace') return;
    event.preventDefault();
    const list = [...chars.value];
    if (list[index]) {
        list.splice(index, 1);
        update(list.filter(Boolean), index);
    } else if (index > 0) {
        list.splice(index - 1, 1);
        update(list.filter(Boolean), index - 1);
    }
}

function onPaste(index, event) {
    const pasted = clean(event.clipboardData?.getData('text'));
    if (!pasted) return;
    event.preventDefault();
    fillFrom(index, pasted);
}

function focus() {
    nextTick(() => inputs.value[Math.min(props.modelValue.length, props.length - 1)]?.focus());
}

defineExpose({ focus });
</script>

<template>
    <div class="flex justify-center gap-2 sm:gap-3" role="group" :aria-label="`Code de ${length} caractères`">
        <input
            v-for="(char, i) in chars"
            :key="i"
            :ref="(el) => (inputs[i] = el)"
            :value="char"
            type="text"
            inputmode="text"
            autocapitalize="characters"
            autocorrect="off"
            spellcheck="false"
            :autocomplete="i === 0 ? 'one-time-code' : 'off'"
            :maxlength="i === 0 ? length : 1"
            :aria-label="`Caractère ${i + 1} sur ${length}`"
            class="text-center font-bold uppercase border rounded-xl focus:border-primary focus:ring-primary"
            :class="[boxClass, invalid ? 'border-error bg-error/5' : 'border-outline-variant']"
            @input="onInput(i, $event)"
            @keydown="onKeydown(i, $event)"
            @paste="onPaste(i, $event)"
            @focus="$event.target.select()"
        />
    </div>
</template>
