<script setup>
import CamaLoadingButton from '@/Components/CamaLoadingButton.vue';
import Checkbox from '@/Components/Checkbox.vue';
import FlashMessage from '@/Components/FlashMessage.vue';
import CamaCodeInput from '@/Components/CamaCodeInput.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, ref, watch } from 'vue';

const props = defineProps({
    step: {
        type: String,
        default: 'credentials',
    },
    maskedEmail: String,
    status: String,
});

const page = usePage();
const is2fa = computed(() => props.step === '2fa');
const dev2faCode = computed(() => page.props.flash?.dev_2fa_code ?? null);

const loginForm = useForm({
    email: '',
    password: '',
    remember: false,
});

const twoFaForm = useForm({ code: '' });
const codeInput = ref(null);
const CODE_LENGTH = 6;
const cardRef = ref(null);
const resending = ref(false);

const inputCls = 'w-full px-4 py-3 border border-outline-variant rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition-all outline-none text-sm';

function focusFirstDigit() {
    nextTick(() => codeInput.value?.focus());
}

function scrollToCard() {
    nextTick(() => cardRef.value?.scrollIntoView({ behavior: 'smooth', block: 'center' }));
}

onMounted(() => {
    if (is2fa.value) {
        scrollToCard();
        focusFirstDigit();
    }
});

watch(is2fa, (active) => {
    if (active) {
        scrollToCard();
        focusFirstDigit();
    }
});

const submitLogin = () => {
    if (loginForm.processing) return;
    loginForm.post(route('assure.login.store'), {
        preserveScroll: true,
        onFinish: () => loginForm.reset('password'),
    });
};

function submit2fa() {
    if (twoFaForm.code.length !== CODE_LENGTH || twoFaForm.processing) return;
    twoFaForm.post(route('assure.login.2fa.store'), {
        onError: () => {
            twoFaForm.code = '';
            focusFirstDigit();
        },
    });
}

function resendCode() {
    resending.value = true;
    router.post(route('assure.login.2fa.resend'), {}, {
        preserveScroll: true,
        onFinish: () => {
            resending.value = false;
            twoFaForm.clearErrors();
            twoFaForm.code = '';
            focusFirstDigit();
        },
    });
}

function cancel2fa() {
    router.post(route('assure.login.2fa.cancel'));
}
</script>

<template>
    <Head :title="is2fa ? 'Vérification en deux étapes | Espace Assuré CAMA' : 'Connexion | Espace Assuré CAMA'" />

    <PublicLayout>
        <main>
            <section class="relative py-16 md:py-20 overflow-hidden bg-on-background text-white">
                <div class="shield-pattern absolute inset-0 opacity-20" />
                <div class="relative z-10 max-w-container-max-width mx-auto px-4 md:px-margin-desktop text-center">
                    <span class="inline-flex items-center gap-2 bg-white/10 backdrop-blur-md border border-white/20 px-4 py-1.5 rounded-full font-label-md text-label-md uppercase tracking-wider mb-6">
                        <span class="material-symbols-outlined text-[18px]">{{ is2fa ? 'mail' : 'verified_user' }}</span>
                        {{ is2fa ? 'Étape 2 sur 2' : 'Plateforme d\'enrôlement' }}
                    </span>
                    <h1 class="font-headline-lg text-headline-lg mb-4">
                        {{ is2fa ? 'Vérification par e-mail' : 'Connexion à mon espace' }}
                    </h1>
                    <p class="font-body-lg text-body-lg text-white/85 max-w-2xl mx-auto">
                        <template v-if="is2fa">
                            Saisissez le code de 6 caractères envoyé à <strong class="text-white">{{ maskedEmail }}</strong>.
                        </template>
                        <template v-else>
                            Accédez à votre tableau de bord, gérez votre dossier familial et suivez vos demandes d'enrôlement.
                        </template>
                    </p>
                </div>
            </section>

            <section class="py-stack-lg bg-surface-container-low">
                <div class="max-w-container-max-width mx-auto px-4 md:px-margin-desktop">
                    <div class="flex justify-center">
                        <div ref="cardRef" class="w-full max-w-md">
                            <div
                                class="bg-white rounded-2xl border shadow-sm overflow-hidden transition-colors"
                                :class="is2fa ? 'border-primary/40 ring-2 ring-primary/20' : 'border-outline-variant'"
                            >
                                <!-- Étape 2FA -->
                                <template v-if="is2fa">
                                    <div class="bg-primary/5 border-b border-primary/20 px-8 py-4 flex items-start gap-3">
                                        <span class="material-symbols-outlined text-primary text-[28px] shrink-0">mark_email_read</span>
                                        <div>
                                            <p class="font-title-md text-title-md text-on-surface">Code de vérification requis</p>
                                            <p class="text-sm text-on-surface-variant mt-1">
                                                Consultez votre boîte mail (<strong class="text-on-surface">{{ maskedEmail }}</strong>) et saisissez le code ci-dessous.
                                            </p>
                                        </div>
                                    </div>

                                    <div class="p-8">
                                        <FlashMessage />

                                        <div
                                            v-if="dev2faCode"
                                            class="mb-5 rounded-lg border border-tertiary/40 bg-tertiary/10 p-3 text-xs text-on-surface-variant flex items-start gap-2"
                                        >
                                            <span class="material-symbols-outlined text-tertiary text-[18px] shrink-0">developer_mode</span>
                                            <span>
                                                <strong class="text-on-surface">Mailer « log » (dev) :</strong> code de test
                                                <strong class="text-on-surface tracking-[0.25em] ml-1 text-base">{{ dev2faCode }}</strong>
                                            </span>
                                        </div>

                                        <form @submit.prevent="submit2fa">
                                            <p class="text-center text-sm font-label-md text-on-surface-variant mb-4">Code de 6 caractères</p>
                                            <CamaCodeInput
                                                ref="codeInput"
                                                v-model="twoFaForm.code"
                                                class="mb-2"
                                                :length="CODE_LENGTH"
                                                :invalid="!!twoFaForm.errors.code"
                                                box-class="w-11 h-14 sm:w-12 sm:h-16 text-xl sm:text-2xl border-2 bg-surface-container-low"
                                                @complete="submit2fa"
                                            />
                                            <InputError :message="twoFaForm.errors.code" class="text-center mb-4" />

                                            <CamaLoadingButton
                                                type="submit"
                                                button-class="w-full py-3.5 rounded-lg font-bold shadow-md flex items-center justify-center gap-2"
                                                :loading="twoFaForm.processing"
                                                :disabled="twoFaForm.code.length !== CODE_LENGTH"
                                                loading-text="Vérification…"
                                            >
                                                <span class="material-symbols-outlined">verified_user</span>
                                                Vérifier et se connecter
                                            </CamaLoadingButton>
                                        </form>

                                        <div class="mt-6 pt-5 border-t border-outline-variant text-center text-sm text-on-surface-variant space-y-3">
                                            <p>
                                                Pas reçu ? Vérifiez les indésirables, puis
                                                <button
                                                    type="button"
                                                    class="text-primary font-bold hover:underline disabled:opacity-50"
                                                    :disabled="resending"
                                                    @click="resendCode"
                                                >
                                                    {{ resending ? 'Envoi en cours…' : 'renvoyer le code' }}
                                                </button>
                                                (valable 10 min).
                                            </p>
                                            <button
                                                type="button"
                                                class="text-sm text-on-surface-variant hover:text-primary inline-flex items-center gap-1 mx-auto"
                                                @click="cancel2fa"
                                            >
                                                <span class="material-symbols-outlined text-[18px]">arrow_back</span>
                                                Retour à la connexion
                                            </button>
                                        </div>
            </div>
                                </template>

                                <!-- Étape identifiants -->
                                <template v-else>
                                    <div class="p-8">
                                        <h3 class="font-title-lg text-title-lg mb-2">Connexion à mon espace</h3>
                                        <p class="text-on-surface-variant font-body-md text-sm mb-6">Accédez à votre tableau de bord assuré.</p>

                <FlashMessage />

                                        <div v-if="status" class="mb-4 flex items-start gap-2 p-3.5 rounded-lg text-sm bg-secondary-container text-on-secondary-container">
                                            <span class="material-symbols-outlined text-[20px] shrink-0">check_circle</span>
                                            <span>{{ status }}</span>
                                        </div>

                                        <form class="space-y-5" @submit.prevent="submitLogin">
                                            <div class="space-y-2">
                                                <label class="font-label-md text-label-md text-on-surface-variant ml-1" for="email">Adresse e-mail</label>
                        <TextInput
                            id="email"
                                                    v-model="loginForm.email"
                            type="email"
                                                    :class="inputCls"
                                                    placeholder="vous@exemple.bf"
                            required
                            autofocus
                            autocomplete="username"
                        />
                                                <InputError :message="loginForm.errors.email" />
                    </div>

                                            <div class="space-y-2">
                                                <label class="font-label-md text-label-md text-on-surface-variant ml-1" for="password">Mot de passe</label>
                        <TextInput
                            id="password"
                                                    v-model="loginForm.password"
                            type="password"
                                                    :class="inputCls"
                                                    placeholder="••••••••"
                            required
                            autocomplete="current-password"
                        />
                                                <InputError :message="loginForm.errors.password" />
                    </div>

                                            <div class="flex items-center justify-between">
                    <label class="flex items-center gap-2 text-sm text-on-surface-variant">
                                                    <Checkbox v-model:checked="loginForm.remember" name="remember" />
                        Se souvenir de moi
                    </label>
                                        <Link :href="route('assure.password.request')" class="text-sm font-bold text-primary hover:underline">Mot de passe oublié ?</Link>
                                            </div>

                    <CamaLoadingButton
                        type="submit"
                                                button-class="w-full py-3.5 rounded-lg font-bold shadow-md flex items-center justify-center gap-2"
                                                :loading="loginForm.processing"
                        loading-text="Connexion…"
                    >
                                                <span class="material-symbols-outlined">login</span>
                        Se connecter
                    </CamaLoadingButton>
                </form>
                                    </div>

                                    <div class="border-t border-outline-variant bg-surface-container-low/50 px-8 py-5 text-center">
                                        <p class="text-sm text-on-surface-variant">
                    Pas encore de compte ?
                    <Link :href="route('registration.create')" class="text-primary font-bold hover:underline">Créer un compte assuré</Link>
                </p>
                                        <p class="text-xs text-on-surface-variant mt-2">Inscription guidée en 6 étapes pour les militaires en fonction.</p>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
            </div>
            </section>
        </main>
    </PublicLayout>
</template>
