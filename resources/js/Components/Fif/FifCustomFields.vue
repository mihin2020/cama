<script setup>
import { reactive } from 'vue';
import UploadField from '@/Components/UploadField.vue';

/**
 * Sections et champs ajoutés par l'administration (formulaire FIF configurable).
 * Une valeur par champ, indexée par le code du champ : { code: valeur }.
 */
const props = defineProps({
    sections: { type: Array, default: () => [] },
    modelValue: { type: Object, default: () => ({}) },
    // Messages d'erreur du serveur par code de champ.
    errors: { type: Object, default: () => ({}) },
    idPrefix: { type: String, default: 'fif' },
});

const emit = defineEmits(['update:modelValue']);

// Fichiers : PDF uniquement, 5 Mo maximum (le serveur refait ces contrôles sur le contenu réel).
const MAX_FILE_SIZE = 5 * 1024 * 1024;
const fileErrors = reactive({});

function onFile(field, event) {
    fileErrors[field.code] = '';
    const file = event.target.files?.[0];
    if (!file) return;
    if (file.type !== 'application/pdf') {
        fileErrors[field.code] = 'Format non accepté : PDF uniquement.';
        event.target.value = '';
        return;
    }
    if (file.size > MAX_FILE_SIZE) {
        fileErrors[field.code] = 'Fichier trop volumineux (5 Mo max).';
        event.target.value = '';
        return;
    }
    set(field.code, file);
}

const fileLabel = (field) => {
    const value = props.modelValue?.[field.code];
    if (value instanceof File) return value.name;
    return value?.filename ?? null;
};

const inputClass = 'w-full px-3 py-2.5 border border-outline-variant rounded-lg text-sm bg-white focus:ring-2 focus:ring-primary focus:border-primary outline-none';

function set(code, value) {
    emit('update:modelValue', { ...props.modelValue, [code]: value });
}

const inputType = (type) => ({ number: 'number', date: 'date', email: 'email', tel: 'tel' }[type] ?? 'text');
const fieldId = (field) => `${props.idPrefix}-${field.code}`;
</script>

<template>
    <div v-if="sections.length" class="space-y-4">
        <section v-for="section in sections" :key="section.id">
            <h3 class="text-xs font-semibold text-on-surface-variant mb-0.5">{{ section.titre }}</h3>
            <p v-if="section.description" class="text-[11px] text-on-surface-variant mb-2">{{ section.description }}</p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div
                    v-for="field in section.fields"
                    :key="field.id"
                    class="space-y-1"
                    :class="field.type === 'textarea' ? 'md:col-span-2' : ''"
                >
                    <label v-if="field.type !== 'checkbox' && field.type !== 'fichier'" class="text-xs font-medium text-on-surface-variant" :for="fieldId(field)">
                        {{ field.libelle }}<span v-if="field.obligatoire" class="text-error"> *</span>
                    </label>

                    <textarea
                        v-if="field.type === 'textarea'"
                        :id="fieldId(field)"
                        :class="inputClass"
                        maxlength="2000"
                        rows="3"
                        :value="modelValue?.[field.code] ?? ''"
                        @input="set(field.code, $event.target.value)"
                    />

                    <select
                        v-else-if="field.type === 'select'"
                        :id="fieldId(field)"
                        :class="inputClass"
                        :value="modelValue?.[field.code] ?? ''"
                        @change="set(field.code, $event.target.value)"
                    >
                        <option value="">— Choisir —</option>
                        <option v-for="option in field.options" :key="option" :value="option">{{ option }}</option>
                    </select>

                    <div v-else-if="field.type === 'radio'" class="flex flex-wrap gap-x-4 gap-y-1 pt-1" role="radiogroup">
                        <label v-for="option in field.options" :key="option" class="flex items-center gap-1.5 text-sm cursor-pointer">
                            <input
                                type="radio"
                                class="text-primary"
                                :name="fieldId(field)"
                                :value="option"
                                :checked="modelValue?.[field.code] === option"
                                @change="set(field.code, option)"
                            />
                            {{ option }}
                        </label>
                    </div>

                    <label v-else-if="field.type === 'checkbox'" class="flex items-start gap-2 text-sm cursor-pointer pt-1">
                        <input
                            :id="fieldId(field)"
                            type="checkbox"
                            class="mt-0.5 rounded border-outline-variant text-primary"
                            :checked="!!modelValue?.[field.code]"
                            @change="set(field.code, $event.target.checked)"
                        />
                        <span>{{ field.libelle }}<span v-if="field.obligatoire" class="text-error"> *</span></span>
                    </label>

                    

                    <div v-else-if="field.type === 'fichier'">
                        <p class="text-xs font-medium text-on-surface-variant">
                            {{ field.libelle }}<span v-if="field.obligatoire" class="text-error"> *</span>
                        </p>
                        <UploadField :id="fieldId(field)" class="mt-1" :file-name="fileLabel(field) ?? ''" @change="onFile(field, $event)" />
                        <p class="text-[11px] text-on-surface-variant mt-1">PDF uniquement — 5 Mo max.</p>
                        <p v-if="fileErrors[field.code]" class="text-error text-xs">{{ fileErrors[field.code] }}</p>
                    </div>

                    <input
                        v-else
                        :id="fieldId(field)"
                        :class="inputClass"
                        :maxlength="field.type === 'tel' ? 50 : 255"
                        :type="inputType(field.type)"
                        :value="modelValue?.[field.code] ?? ''"
                        @input="set(field.code, $event.target.value)"
                    />

                    <p v-if="field.aide" class="text-[11px] text-on-surface-variant">{{ field.aide }}</p>
                    <p v-if="errors?.[field.code]" class="text-error text-xs">{{ errors[field.code] }}</p>
                </div>
            </div>
        </section>
    </div>
</template>
