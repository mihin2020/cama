<script setup>
import CmsLayout from '@/Layouts/CmsLayout.vue';
import CamaLoadingButton from '@/Components/CamaLoadingButton.vue';
import { SOCIAL_NETWORKS } from '@/Data/socialNetworks.js';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    footer: Object,
});

const form = useForm({
    tagline: props.footer?.tagline ?? '',
    address: props.footer?.address ?? '',
    phones: [...(props.footer?.phones ?? [])],
    emails: [...(props.footer?.emails ?? [])],
    hours: props.footer?.hours ?? '',
    play_store_url: props.footer?.play_store_url ?? '',
    socials: (props.footer?.socials ?? []).map((s) => ({ network: s.network, url: s.url })),
    useful_links: (props.footer?.useful_links ?? []).map((l) => ({ label: l.label, url: l.url })),
});

function save() {
    form.put(route('admin.cms.footer.update'), { preserveScroll: true });
}

function addPhone() {
    if (form.phones.length < 10) form.phones.push('');
}

function removePhone(index) {
    form.phones.splice(index, 1);
}

function addEmail() {
    if (form.emails.length < 10) form.emails.push('');
}

function removeEmail(index) {
    form.emails.splice(index, 1);
}

function addSocial() {
    if (form.socials.length < 15) form.socials.push({ network: 'facebook', url: '' });
}

function removeSocial(index) {
    form.socials.splice(index, 1);
}

function addLink() {
    if (form.useful_links.length < 15) form.useful_links.push({ label: '', url: '' });
}

function removeLink(index) {
    form.useful_links.splice(index, 1);
}
</script>

<template>
    <Head title="Pied de page" />

    <CmsLayout active-nav="footer" title="Pied de page" subtitle="Coordonnées & liens affichés en bas de toutes les pages publiques">
        <form @submit.prevent="save" class="space-y-4 max-w-3xl">
            <div class="assure-card p-6">
                <h2 class="text-sm font-bold text-on-surface mb-1">Coordonnées de la CAMA</h2>
                <p class="text-xs text-on-surface-variant mb-4">
                    Ces informations s'affichent dans le pied de page de toutes les pages publiques du site.
                </p>

                <div class="space-y-4">
                    <div>
                        <label class="text-[11px] font-bold uppercase text-on-surface-variant block mb-1">Slogan / description</label>
                        <textarea v-model="form.tagline" rows="2" class="w-full px-3 py-2 text-sm border border-outline-variant rounded-lg" />
                        <p v-if="form.errors.tagline" class="text-error text-xs mt-1">{{ form.errors.tagline }}</p>
                    </div>
                    <div>
                        <label class="text-[11px] font-bold uppercase text-on-surface-variant block mb-1">Adresse du siège</label>
                        <textarea v-model="form.address" rows="2" class="w-full px-3 py-2 text-sm border border-outline-variant rounded-lg" />
                        <p v-if="form.errors.address" class="text-error text-xs mt-1">{{ form.errors.address }}</p>
                    </div>

                    <div>
                        <label class="text-[11px] font-bold uppercase text-on-surface-variant block mb-1">Téléphones</label>
                        <div class="space-y-2">
                            <div v-for="(phone, i) in form.phones" :key="i" class="flex items-center gap-2">
                                <input v-model="form.phones[i]" type="text" class="flex-1 px-3 py-2 text-sm border border-outline-variant rounded-lg" placeholder="+226 25 30 81 03" />
                                <button type="button" class="text-error text-xs px-2 py-1 rounded hover:bg-error/10" @click="removePhone(i)">Retirer</button>
                            </div>
                        </div>
                        <button v-if="form.phones.length < 5" type="button" class="text-primary text-xs font-semibold mt-2" @click="addPhone">+ Ajouter un numéro</button>
                    </div>

                    <div>
                        <label class="text-[11px] font-bold uppercase text-on-surface-variant block mb-1">E-mails publics</label>
                        <div class="space-y-2">
                            <div v-for="(email, i) in form.emails" :key="i" class="flex items-center gap-2">
                                <input v-model="form.emails[i]" type="email" class="flex-1 px-3 py-2 text-sm border border-outline-variant rounded-lg" placeholder="Cama_bf@gmail.com" />
                                <button type="button" class="text-error text-xs px-2 py-1 rounded hover:bg-error/10" @click="removeEmail(i)">Retirer</button>
                            </div>
                        </div>
                        <button v-if="form.emails.length < 10" type="button" class="text-primary text-xs font-semibold mt-2" @click="addEmail">+ Ajouter un e-mail</button>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="text-[11px] font-bold uppercase text-on-surface-variant block mb-1">Horaires</label>
                            <input v-model="form.hours" type="text" class="w-full px-3 py-2 text-sm border border-outline-variant rounded-lg" placeholder="Lundi au vendredi, 07h30 à 16h00" />
                            <p v-if="form.errors.hours" class="text-error text-xs mt-1">{{ form.errors.hours }}</p>
                        </div>
                        <div>
                            <label class="text-[11px] font-bold uppercase text-on-surface-variant block mb-1">Lien Google Play</label>
                            <input v-model="form.play_store_url" type="url" class="w-full px-3 py-2 text-sm border border-outline-variant rounded-lg" placeholder="https://play.google.com/store/apps/details?id=com.cama.bf" />
                            <p v-if="form.errors.play_store_url" class="text-error text-xs mt-1">{{ form.errors.play_store_url }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="assure-card p-6">
                <h2 class="text-sm font-bold text-on-surface mb-1">Réseaux sociaux</h2>
                <p class="text-xs text-on-surface-variant mb-4">Ajoutez autant de réseaux que vous voulez (Facebook, Instagram, X, LinkedIn, YouTube, WhatsApp…). L'icône est choisie automatiquement selon le réseau.</p>
                <div class="space-y-3">
                    <div v-for="(social, i) in form.socials" :key="i" class="grid grid-cols-1 sm:grid-cols-[180px_1fr_auto] gap-2 items-center border border-outline-variant rounded-lg p-3">
                        <select v-model="social.network" class="px-3 py-2 text-sm border border-outline-variant rounded-lg bg-surface">
                            <option v-for="net in SOCIAL_NETWORKS" :key="net.key" :value="net.key">{{ net.label }}</option>
                        </select>
                        <input v-model="social.url" type="text" class="px-3 py-2 text-sm border border-outline-variant rounded-lg" placeholder="https://..." />
                        <button type="button" class="text-error text-xs px-2 py-1 rounded hover:bg-error/10 justify-self-start" @click="removeSocial(i)">Retirer</button>
                    </div>
                </div>
                <button v-if="form.socials.length < 15" type="button" class="text-primary text-xs font-semibold mt-3" @click="addSocial">+ Ajouter un réseau social</button>
            </div>

            <div class="assure-card p-6">
                <h2 class="text-sm font-bold text-on-surface mb-1">Liens utiles</h2>
                <p class="text-xs text-on-surface-variant mb-4">Affichés dans la colonne « Institution / Liens utiles » (ex. MGDP, EMGA).</p>
                <div class="space-y-3">
                    <div v-for="(link, i) in form.useful_links" :key="i" class="grid grid-cols-1 sm:grid-cols-[1fr_1fr_auto] gap-2 items-center border border-outline-variant rounded-lg p-3">
                        <input v-model="link.label" type="text" class="px-3 py-2 text-sm border border-outline-variant rounded-lg" placeholder="Libellé (ex. MGDP)" />
                        <input v-model="link.url" type="text" class="px-3 py-2 text-sm border border-outline-variant rounded-lg" placeholder="https://..." />
                        <button type="button" class="text-error text-xs px-2 py-1 rounded hover:bg-error/10 justify-self-start" @click="removeLink(i)">Retirer</button>
                    </div>
                </div>
                <button v-if="form.useful_links.length < 10" type="button" class="text-primary text-xs font-semibold mt-3" @click="addLink">+ Ajouter un lien</button>
            </div>

            <CamaLoadingButton type="submit" :loading="form.processing">Publier le pied de page</CamaLoadingButton>
        </form>
    </CmsLayout>
</template>
