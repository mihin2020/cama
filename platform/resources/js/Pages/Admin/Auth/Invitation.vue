<script setup>
import CamaLoadingButton from '@/Components/CamaLoadingButton.vue';
import CamaLogo from '@/Components/CamaLogo.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
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

const submit = () => {
    form.post(route('admin.invitation.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Activation du compte" />

    <div class="min-h-screen bg-[#2f2f2f] flex flex-col">
        <main class="flex-1 flex items-center justify-center px-4 py-10">
            <div class="w-full max-w-md bg-white rounded-xl shadow-xl overflow-hidden">
                <div class="bg-primary-container px-6 py-5 text-white">
                    <CamaLogo :show-subtitle="false" size="lg" />
                    <p class="mt-3 text-sm opacity-90">Finalisez votre accès au back-office CAMA</p>
                </div>

                <div class="p-6 md:p-8">
                    <h1 class="font-headline text-xl font-bold text-on-surface mb-2">Définir votre mot de passe</h1>
                    <p class="text-xs text-on-surface-variant mb-5">
                        Compte : <span class="font-semibold text-on-surface">{{ email }}</span>
                    </p>

                    <form class="space-y-4" @submit.prevent="submit">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wide text-on-surface-variant mb-1" for="password">Mot de passe</label>
                            <TextInput
                                id="password"
                                v-model="form.password"
                                type="password"
                                class="mt-1 block w-full"
                                required
                                autofocus
                            />
                            <p class="mt-1 text-[11px] text-on-surface-variant">Minimum 12 caractères.</p>
                            <InputError class="mt-1" :message="form.errors.password" />
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wide text-on-surface-variant mb-1" for="password_confirmation">Confirmer le mot de passe</label>
                            <TextInput
                                id="password_confirmation"
                                v-model="form.password_confirmation"
                                type="password"
                                class="mt-1 block w-full"
                                required
                            />
                            <InputError class="mt-1" :message="form.errors.password_confirmation" />
                        </div>

                        <InputError :message="form.errors.email" />

                        <CamaLoadingButton
                            type="submit"
                            variant="success"
                            button-class="w-full py-3 text-sm"
                            :loading="form.processing"
                            loading-text="Enregistrement…"
                        >
                            Activer mon compte
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
