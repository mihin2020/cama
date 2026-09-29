<script setup>
import CamaLoadingButton from '@/Components/CamaLoadingButton.vue';
import CamaLogo from '@/Components/CamaLogo.vue';
import FlashMessage from '@/Components/FlashMessage.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';

const page = usePage();
const dev2faCode = computed(() => page.props.flash?.dev_2fa_code ?? null);

defineProps({
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
    form.post(route('assure.login.2fa.store'), {
        onError: () => {
            digits.value = ['', '', '', ''];
            form.code = '';
            nextTick(() => inputs.value[0]?.focus());
        },
    });
}

function resend() {
    resending.value = true;
    router.post(route('assure.login.2fa.resend'), {}, {
        preserveScroll: true,
        onFinish: () => (resending.value = false),
    });
}

function cancel() {
    router.post(route('assure.login.2fa.cancel'));
}
</script>

<template>
    <Head title="Vérification en deux étapes" />

    <div class="min-h-screen bg-surface flex flex-col">
        <header class="bg-white border-b border-outline-variant px-4 py-4">
            <div class="max-w-md mx-auto">
                <Link href="/"><CamaLogo /></Link>
            </div>
        </header>

        <main class="flex-1 flex items-center justify-center px-4 py-10">
            <div class="w-full max-w-md bg-white border border-outline-variant rounded-xl shadow-sm p-6 md:p-8">
                <button
                    type="button"
                    class="text-sm text-on-surface-variant hover:text-primary mb-4 inline-flex items-center gap-1"
                    @click="cancel"
                >
                    <span class="material-symbols-outlined text-[18px]">arrow_back</span> Retour à la connexion
                </button>

                <div class="w-12 h-12 rounded-full bg-secondary/10 text-secondary flex items-center justify-center mb-4">
                    <span class="material-symbols-outlined">verified_user</span>
                </div>
                <h1 class="font-headline text-2xl font-bold text-on-surface mb-1">Vérification en deux étapes</h1>
                <p class="text-sm text-on-surface-variant mb-6">
                    Un code à <strong class="text-on-surface">4 chiffres</strong> a été envoyé à
                    <strong class="text-on-surface">{{ email }}</strong>. Saisissez-le pour finaliser la connexion.
                    <span class="block mt-2 text-[11px] italic">La réception peut prendre jusqu'à une minute selon votre messagerie.</span>
                </p>

                <FlashMessage />

                <div
                    v-if="dev2faCode"
                    class="mb-4 rounded-lg border border-tertiary/40 bg-tertiary/10 p-3 text-xs text-on-surface-variant flex items-start gap-2"
                >
                    <span class="material-symbols-outlined text-tertiary text-[18px] shrink-0">developer_mode</span>
                    <span>
                        <strong class="text-on-surface">Mailer « log » (dev) :</strong> aucun e-mail réel — code de test
                        <strong class="text-on-surface tracking-[0.2em] ml-1">{{ dev2faCode }}</strong>.
                    </span>
                </div>

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
                        <span class="material-symbols-outlined text-[18px]">verified_user</span>
                        Vérifier et se connecter
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
                </div>
            </div>
        </main>
    </div>
</template>
