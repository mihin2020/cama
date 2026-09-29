<script setup>
import CamaLoadingButton from '@/Components/CamaLoadingButton.vue';
import CamaLogo from '@/Components/CamaLogo.vue';
import FlashMessage from '@/Components/FlashMessage.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';

const props = defineProps({
    email: String,
    codePending: Boolean,
});

const form = useForm({ code: '' });
const digits = ref(['', '', '', '']);
const inputs = ref([]);
const resending = ref(false);

function syncCode() {
    form.code = digits.value.join('');
}

function onDigitInput(index, event) {
    const value = event.target.value.replace(/\D/g, '');
    digits.value[index] = value.slice(-1);
    syncCode();
    if (value && index < 3) {
        nextTick(() => inputs.value[index + 1]?.focus());
    }
    if (form.code.length === 4) {
        submit();
    }
}

function onDigitKeydown(index, event) {
    if (event.key === 'Backspace' && !digits.value[index] && index > 0) {
        inputs.value[index - 1]?.focus();
    }
}

function onPaste(event) {
    const pasted = (event.clipboardData?.getData('text') ?? '').replace(/\D/g, '').slice(0, 4);
    if (!pasted) return;
    event.preventDefault();
    pasted.split('').forEach((d, i) => (digits.value[i] = d));
    syncCode();
    nextTick(() => inputs.value[Math.min(pasted.length, 3)]?.focus());
    if (form.code.length === 4) submit();
}

function submit() {
    if (form.code.length !== 4 || form.processing) return;
    form.post(route('assure.verification.verify'), {
        onError: () => {
            digits.value = ['', '', '', ''];
            form.code = '';
            nextTick(() => inputs.value[0]?.focus());
        },
    });
}

function resend() {
    resending.value = true;
    router.post(route('assure.verification.resend'), {}, {
        preserveScroll: true,
        onFinish: () => (resending.value = false),
    });
}

function logout() {
    router.post(route('assure.logout'));
}
</script>

<template>
    <Head title="Vérification de l'e-mail" />

    <div class="min-h-screen bg-surface flex flex-col">
        <header class="bg-white border-b border-outline-variant px-4 py-4">
            <div class="max-w-md mx-auto">
                <Link href="/"><CamaLogo /></Link>
            </div>
        </header>

        <main class="flex-1 flex items-center justify-center px-4 py-10">
            <div class="w-full max-w-md bg-white border border-outline-variant rounded-xl shadow-sm p-6 md:p-8">
                <div class="w-12 h-12 rounded-full bg-primary/10 text-primary flex items-center justify-center mb-4">
                    <span class="material-symbols-outlined">mark_email_unread</span>
                </div>
                <h1 class="font-headline text-2xl font-bold text-on-surface mb-1">Vérifiez votre e-mail</h1>
                <p class="text-sm text-on-surface-variant mb-6">
                    Un code à <strong class="text-on-surface">4 chiffres</strong> a été envoyé à
                    <strong class="text-on-surface">{{ email }}</strong>. Saisissez-le pour confirmer votre adresse.
                </p>

                <FlashMessage />

                <form @submit.prevent="submit">
                    <div class="flex justify-center gap-3 mb-2" @paste="onPaste">
                        <input
                            v-for="(d, i) in digits"
                            :key="i"
                            :ref="(el) => (inputs[i] = el)"
                            v-model="digits[i]"
                            type="text"
                            inputmode="numeric"
                            autocomplete="one-time-code"
                            maxlength="1"
                            class="w-14 h-16 text-center text-2xl font-bold border border-outline-variant rounded-xl focus:border-primary focus:ring-primary"
                            :class="form.errors.code ? 'border-error' : ''"
                            @input="onDigitInput(i, $event)"
                            @keydown="onDigitKeydown(i, $event)"
                        />
                    </div>
                    <InputError :message="form.errors.code" class="text-center mb-3" />

                    <CamaLoadingButton
                        type="submit"
                        button-class="w-full justify-center mt-2"
                        :loading="form.processing"
                        :disabled="form.code.length !== 4"
                    >
                        Vérifier mon adresse e-mail
                    </CamaLoadingButton>
                </form>

                <div class="mt-6 pt-5 border-t border-outline-variant text-center text-xs text-on-surface-variant space-y-3">
                    <p>
                        Vous n'avez rien reçu ? Vérifiez vos courriers indésirables, puis
                        <button type="button" class="text-primary font-bold hover:underline disabled:opacity-50" :disabled="resending" @click="resend">
                            {{ resending ? 'Envoi en cours…' : 'renvoyer le code' }}
                        </button>
                        (valable 15 minutes).
                    </p>
                    <p>
                        Mauvaise adresse ?
                        <button type="button" class="text-primary font-bold hover:underline" @click="logout">Se déconnecter</button>
                    </p>
                </div>
            </div>
        </main>
    </div>
</template>
