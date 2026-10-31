<script setup>
import CamaLoadingButton from '@/Components/CamaLoadingButton.vue';
import CamaLogo from '@/Components/CamaLogo.vue';
import FlashMessage from '@/Components/FlashMessage.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({ email: '' });

const submit = () => {
    form.post(route('admin.password.email'), {
        onSuccess: () => form.reset('email'),
    });
};
</script>

<template>
    <Head title="Mot de passe oublié" />

    <div class="min-h-screen bg-[#2f2f2f] flex flex-col">
        <main class="flex-1 flex items-center justify-center px-4 py-10">
            <div class="w-full max-w-md bg-white rounded-xl shadow-xl overflow-hidden">
                <div class="bg-primary-container px-6 py-5 text-white">
                    <CamaLogo :show-subtitle="false" size="lg" />
                    <p class="mt-3 text-sm opacity-90">Back-office CAMA — accès réservé au personnel autorisé</p>
                </div>

                <div class="p-6 md:p-8">
                    <h1 class="font-headline text-xl font-bold text-on-surface mb-2">Mot de passe oublié</h1>
                    <p class="text-sm text-on-surface-variant mb-4">
                        Saisissez votre e-mail professionnel : si un compte actif correspond, vous recevrez un lien pour choisir un nouveau mot de passe.
                    </p>

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
                                autocomplete="username"
                            />
                            <InputError class="mt-1" :message="form.errors.email" />
                        </div>

                        <CamaLoadingButton
                            type="submit"
                            variant="success"
                            button-class="w-full py-3 text-sm"
                            :loading="form.processing"
                            loading-text="Envoi…"
                        >
                            Envoyer le lien
                        </CamaLoadingButton>
                    </form>

                    <p class="mt-6 text-center">
                        <Link :href="route('admin.login')" class="text-xs text-on-surface-variant hover:text-primary">← Retour à la connexion</Link>
                    </p>
                </div>
            </div>
        </main>
    </div>
</template>
