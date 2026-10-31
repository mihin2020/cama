<script setup>
import CamaLoadingButton from '@/Components/CamaLoadingButton.vue';
import FlashMessage from '@/Components/FlashMessage.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({ email: '' });

const inputCls = 'w-full px-4 py-3 border border-outline-variant rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition-all outline-none text-sm';

const submit = () => {
    if (form.processing) return;
    form.post(route('assure.password.email'), {
        preserveScroll: true,
        onSuccess: () => form.reset('email'),
    });
};
</script>

<template>
    <Head title="Mot de passe oublié | Espace Assuré CAMA" />

    <PublicLayout>
        <main>
            <section class="relative py-16 md:py-20 overflow-hidden bg-on-background text-white">
                <div class="shield-pattern absolute inset-0 opacity-20" />
                <div class="relative z-10 max-w-container-max-width mx-auto px-4 md:px-margin-desktop text-center">
                    <span class="inline-flex items-center gap-2 bg-white/10 backdrop-blur-md border border-white/20 px-4 py-1.5 rounded-full font-label-md text-label-md uppercase tracking-wider mb-6">
                        <span class="material-symbols-outlined text-[18px]">lock_reset</span>
                        Espace assuré
                    </span>
                    <h1 class="font-headline-lg text-headline-lg mb-4">Mot de passe oublié</h1>
                    <p class="font-body-lg text-body-lg text-white/85 max-w-2xl mx-auto">
                        Recevez par e-mail un lien pour choisir un nouveau mot de passe.
                    </p>
                </div>
            </section>

            <section class="py-stack-lg bg-surface-container-low">
                <div class="max-w-container-max-width mx-auto px-4 md:px-margin-desktop">
                    <div class="flex justify-center">
                        <div class="w-full max-w-md bg-white rounded-2xl border border-outline-variant shadow-sm overflow-hidden">
                            <div class="p-8">
                                <p class="text-on-surface-variant text-sm mb-6">
                                    Saisissez l'adresse e-mail de votre compte. Si elle correspond à un compte, le lien vous est envoyé (valable 60 minutes).
                                </p>

                                <FlashMessage />

                                <form class="space-y-5" @submit.prevent="submit">
                                    <div class="space-y-2">
                                        <label class="font-label-md text-label-md text-on-surface-variant ml-1" for="email">Adresse e-mail</label>
                                        <TextInput
                                            id="email"
                                            v-model="form.email"
                                            type="email"
                                            :class="inputCls"
                                            placeholder="vous@exemple.bf"
                                            required
                                            autofocus
                                            autocomplete="username"
                                        />
                                        <InputError :message="form.errors.email" />
                                    </div>

                                    <CamaLoadingButton
                                        type="submit"
                                        button-class="w-full py-3.5 rounded-lg font-bold shadow-md flex items-center justify-center gap-2"
                                        :loading="form.processing"
                                        loading-text="Envoi…"
                                    >
                                        <span class="material-symbols-outlined">mail</span>
                                        Envoyer le lien
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
