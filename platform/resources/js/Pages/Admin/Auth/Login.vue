<script setup>
import CamaLoadingButton from '@/Components/CamaLoadingButton.vue';
import Checkbox from '@/Components/Checkbox.vue';
import CamaLogo from '@/Components/CamaLogo.vue';
import FlashMessage from '@/Components/FlashMessage.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    status: String,
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('admin.login.store'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Connexion administration" />

    <div class="min-h-screen bg-[#2f2f2f] flex flex-col">
        <main class="flex-1 flex items-center justify-center px-4 py-10">
            <div class="w-full max-w-md bg-white rounded-xl shadow-xl overflow-hidden">
                <div class="bg-primary-container px-6 py-5 text-white">
                    <CamaLogo :show-subtitle="false" size="lg" />
                    <p class="mt-3 text-sm opacity-90">Back-office CAMA — accès réservé au personnel autorisé</p>
                </div>

                <div class="p-6 md:p-8">
                    <h1 class="font-headline text-xl font-bold text-on-surface mb-4">Connexion</h1>

                    <div class="mb-4 rounded-lg border border-outline-variant bg-surface-container-low px-4 py-3 text-xs text-on-surface-variant">
                        <p class="font-bold text-on-surface mb-1">Comptes démo back-office</p>
                        <p><span class="font-semibold">Administrateur :</span> admin@cama.bf</p>
                        <p><span class="font-semibold">Gestionnaire :</span> gestionnaire@cama.bf</p>
                        <p class="mt-1"><span class="font-semibold">Mot de passe :</span> Demo2026!</p>
                        <p class="mt-2 text-[11px] text-primary">URL : <span class="font-mono">/admin/connexion</span> (pas l'espace assuré)</p>
                    </div>

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

                        <label class="flex items-center gap-2 text-sm text-on-surface-variant">
                            <Checkbox v-model:checked="form.remember" name="remember" />
                            Maintenir la session
                        </label>

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

                    <p class="mt-6 text-center">
                        <Link href="/" class="text-xs text-on-surface-variant hover:text-primary">← Retour au site public</Link>
                    </p>
                </div>
            </div>
        </main>
    </div>
</template>
