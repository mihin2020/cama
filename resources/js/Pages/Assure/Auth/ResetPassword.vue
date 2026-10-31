<script setup>
import CamaLoadingButton from '@/Components/CamaLoadingButton.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    token: { type: String, required: true },
    email: { type: String, required: true },
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const inputCls = 'w-full px-4 py-3 border border-outline-variant rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition-all outline-none text-sm';

const submit = () => {
    if (form.processing) return;
    form.post(route('assure.password.update'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Nouveau mot de passe | Espace Assuré CAMA" />

    <PublicLayout>
        <main>
            <section class="relative py-16 md:py-20 overflow-hidden bg-on-background text-white">
                <div class="shield-pattern absolute inset-0 opacity-20" />
                <div class="relative z-10 max-w-container-max-width mx-auto px-4 md:px-margin-desktop text-center">
                    <span class="inline-flex items-center gap-2 bg-white/10 backdrop-blur-md border border-white/20 px-4 py-1.5 rounded-full font-label-md text-label-md uppercase tracking-wider mb-6">
                        <span class="material-symbols-outlined text-[18px]">lock_reset</span>
                        Espace assuré
                    </span>
                    <h1 class="font-headline-lg text-headline-lg mb-4">Nouveau mot de passe</h1>
                    <p class="font-body-lg text-body-lg text-white/85 max-w-2xl mx-auto">
                        Choisissez le mot de passe de votre espace assuré.
                    </p>
                </div>
            </section>

            <section class="py-stack-lg bg-surface-container-low">
                <div class="max-w-container-max-width mx-auto px-4 md:px-margin-desktop">
                    <div class="flex justify-center">
                        <div class="w-full max-w-md bg-white rounded-2xl border border-outline-variant shadow-sm overflow-hidden">
                            <div class="p-8">
                                <p class="text-xs text-on-surface-variant mb-5">
                                    Compte : <span class="font-semibold text-on-surface">{{ email }}</span>
                                </p>

                                <form class="space-y-5" @submit.prevent="submit">
                                    <div class="space-y-2">
                                        <label class="font-label-md text-label-md text-on-surface-variant ml-1" for="password">Nouveau mot de passe</label>
                                        <TextInput
                                            id="password"
                                            v-model="form.password"
                                            type="password"
                                            :class="inputCls"
                                            required
                                            autofocus
                                            autocomplete="new-password"
                                        />
                                        <p class="text-[11px] text-on-surface-variant ml-1">Minimum 12 caractères.</p>
                                        <InputError :message="form.errors.password" />
                                    </div>

                                    <div class="space-y-2">
                                        <label class="font-label-md text-label-md text-on-surface-variant ml-1" for="password_confirmation">Confirmer le mot de passe</label>
                                        <TextInput
                                            id="password_confirmation"
                                            v-model="form.password_confirmation"
                                            type="password"
                                            :class="inputCls"
                                            required
                                            autocomplete="new-password"
                                        />
                                        <InputError :message="form.errors.password_confirmation" />
                                    </div>

                                    <InputError :message="form.errors.email" />

                                    <CamaLoadingButton
                                        type="submit"
                                        button-class="w-full py-3.5 rounded-lg font-bold shadow-md flex items-center justify-center gap-2"
                                        :loading="form.processing"
                                        loading-text="Enregistrement…"
                                    >
                                        <span class="material-symbols-outlined">lock_reset</span>
                                        Modifier mon mot de passe
                                    </CamaLoadingButton>
                                </form>
                            </div>

                            <div class="border-t border-outline-variant bg-surface-container-low/50 px-8 py-5 text-center">
                                <Link :href="route('assure.login')" class="text-sm text-primary font-bold hover:underline">← Retour à la connexion</Link>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </PublicLayout>
</template>
