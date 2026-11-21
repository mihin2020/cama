<script setup>
import CamaCodeInput from '@/Components/CamaCodeInput.vue';
import CamaLoadingButton from '@/Components/CamaLoadingButton.vue';
import CamaLogo from '@/Components/CamaLogo.vue';
import FlashMessage from '@/Components/FlashMessage.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

const props = defineProps({
    step: {
        type: String,
        default: 'credentials',
    },
    status: String,
    maskedEmail: String,
    codeLength: {
        type: Number,
        default: 6,
    },
});

const page = usePage();
const is2fa = computed(() => props.step === '2fa');
const dev2faCode = computed(() => page.props.flash?.dev_2fa_code ?? null);

const form = useForm({
    email: '',
    password: '',
});

const twoFaForm = useForm({ code: '' });
const resending = ref(false);
const codeInput = ref(null);
onMounted(() => codeInput.value?.focus());

const submit = () => {
    form.post(route('admin.login.store'), {
        onFinish: () => form.reset('password'),
    });
};

function submit2fa() {
    if (twoFaForm.code.length !== props.codeLength || twoFaForm.processing) return;
    twoFaForm.post(route('admin.login.2fa.store'), {
        onError: () => {
            twoFaForm.reset('code');
            codeInput.value?.focus();
        },
    });
}

function resendCode() {
    resending.value = true;
    router.post(route('admin.login.2fa.resend'), {}, {
        onFinish: () => {
            resending.value = false;
            twoFaForm.reset('code');
            twoFaForm.clearErrors();
        },
    });
}

function cancel2fa() {
    router.post(route('admin.login.2fa.cancel'));
}
</script>

<template>
    <Head :title="is2fa ? 'Vérification en deux étapes' : 'Connexion administration'" />

    <div class="min-h-screen bg-[#2f2f2f] flex flex-col">
        <main class="flex-1 flex items-center justify-center px-4 py-10">
            <div class="w-full max-w-md bg-white rounded-xl shadow-xl overflow-hidden">
                <div class="bg-primary-container px-6 py-5 text-white">
                    <CamaLogo :show-subtitle="false" size="lg" />
                    <p class="mt-3 text-sm opacity-90">Back-office CAMA — accès réservé au personnel autorisé</p>
                </div>

                <div class="p-6 md:p-8">
                    <template v-if="is2fa">
                        <h1 class="font-headline text-xl font-bold text-on-surface mb-1">Vérification en deux étapes</h1>
                        <p class="text-sm text-on-surface-variant mb-4">
                            Saisissez le code de {{ codeLength }} caractères (chiffres et lettres) envoyé à <strong>{{ maskedEmail }}</strong>.
                        </p>

                        <FlashMessage />

                        <p v-if="dev2faCode" class="mb-4 rounded-lg border border-dashed border-outline-variant bg-surface-container-low px-3 py-2 text-xs text-on-surface-variant">
                            Mode développement (mailer « log ») — code : <strong class="font-mono tracking-widest">{{ dev2faCode }}</strong>
                        </p>

                        <form class="space-y-4" @submit.prevent="submit2fa">
                            <div>
                                <p class="block text-xs font-bold uppercase tracking-wide text-on-surface-variant mb-2">Code de vérification</p>
                                <CamaCodeInput
                                    ref="codeInput"
                                    v-model="twoFaForm.code"
                                    :length="codeLength"
                                    :invalid="!!twoFaForm.errors.code"
                                    box-class="w-10 h-12 sm:w-11 sm:h-14 text-xl"
                                    @complete="submit2fa"
                                />
                                <InputError class="mt-2 text-center" :message="twoFaForm.errors.code" />
                            </div>

                            <CamaLoadingButton
                                type="submit"
                                variant="success"
                                button-class="w-full py-3 text-sm"
                                :loading="twoFaForm.processing"
                                loading-text="Vérification…"
                            >
                                Valider le code
                            </CamaLoadingButton>
                        </form>

                        <div class="mt-4 flex items-center justify-between text-xs">
                            <button type="button" class="text-primary font-bold hover:underline disabled:opacity-50" :disabled="resending" @click="resendCode">
                                {{ resending ? 'Envoi…' : 'Renvoyer le code' }}
                            </button>
                            <button type="button" class="text-on-surface-variant hover:text-primary" @click="cancel2fa">
                                Annuler
                            </button>
                        </div>
                    </template>

                    <template v-else>
                        <h1 class="font-headline text-xl font-bold text-on-surface mb-4">Connexion</h1>

                        <FlashMessage />

                        <form class="space-y-4" @submit.prevent="submit">
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wide text-on-surface-variant mb-1" for="email">E-mail professionnel</label>
                                <TextInput
                                    id="email"
                                    v-model="form.email"
                                    type="email"
                                    class="mt-1 block w-full"
                                    required
                                    autofocus
                                />
                                <InputError class="mt-1" :message="form.errors.email" />
                            </div>

                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wide text-on-surface-variant mb-1" for="password">Mot de passe</label>
                                <TextInput
                                    id="password"
                                    v-model="form.password"
                                    type="password"
                                    class="mt-1 block w-full"
                                    required
                                />
                                <InputError class="mt-1" :message="form.errors.password" />
                            </div>

                            <p class="text-right">
                                <Link :href="route('admin.password.request')" class="text-xs font-bold text-primary hover:underline">Mot de passe oublié ?</Link>
                            </p>

                            <CamaLoadingButton
                                type="submit"
                                variant="success"
                                button-class="w-full py-3 text-sm"
                                :loading="form.processing"
                                loading-text="Connexion…"
                            >
                                Accéder au back-office
                            </CamaLoadingButton>
                        </form>
                    </template>

                    <p class="mt-6 text-center">
                        <Link href="/" class="text-xs text-on-surface-variant hover:text-primary">← Retour au site public</Link>
                    </p>
                </div>
            </div>
        </main>
    </div>
</template>
