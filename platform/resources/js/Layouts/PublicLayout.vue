<script setup>
import CamaAnchorTop from '@/Components/CamaAnchorTop.vue';
import CamaCookieBar from '@/Components/CamaCookieBar.vue';
import { socialIcon, socialLabel } from '@/Data/socialNetworks.js';
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const page = usePage();
const menuOpen = ref(false);
const searchOpen = ref(false);
const assureOpen = ref(false);
const bannerVisible = ref(true);

const site = computed(() => page.props.publicSite ?? {});
const menu = computed(() => site.value.menu ?? []);
const banner = computed(() => site.value.banner ?? null);
const footer = computed(() => site.value.footer ?? {});
const footerPhones = computed(() => (footer.value.phones ?? []).filter(Boolean));
const footerEmails = computed(() => (footer.value.emails ?? []).filter(Boolean));
const footerSocials = computed(() => (footer.value.socials ?? []).filter((s) => s && s.network && s.url));
const footerLinks = computed(() => (footer.value.useful_links ?? []).filter((l) => l && l.label && l.url));
function telHref(phone) {
    return 'tel:' + String(phone).replace(/[^+0-9]/g, '');
}
const currentPath = computed(() => page.url.split('?')[0]);

function isActive(item) {
    if (item.href === '/') {
        return currentPath.value === '/';
    }

    return currentPath.value.startsWith(item.href);
}

function closeMobile() {
    menuOpen.value = false;
}

function toggleSearch() {
    searchOpen.value = !searchOpen.value;
    if (searchOpen.value) {
        menuOpen.value = false;
    }
}
</script>

<template>
    <div class="min-h-screen flex flex-col bg-background text-on-background">
        <a id="top" href="#top" class="cama-top-anchor" tabindex="-1" aria-hidden="true" />
        <div
            v-if="banner && bannerVisible"
            id="site-info-banner"
            :class="{ 'is-warning': banner.type === 'warning' }"
        >
            <div class="max-w-container-max-width mx-auto px-4 md:px-8 py-2 flex items-center justify-between gap-3">
                <div class="flex items-center gap-2 min-w-0 flex-1">
                    <span class="material-symbols-outlined text-[18px] shrink-0">{{ banner.type === 'warning' ? 'warning' : 'campaign' }}</span>
                    <p class="truncate md:whitespace-normal" v-html="banner.message" />
                    <a v-if="banner.link_url" :href="banner.link_url" class="hidden sm:inline-flex shrink-0 underline font-semibold ml-2">
                        {{ banner.link_label || 'En savoir plus' }}
                    </a>
                </div>
                <button type="button" class="banner-close shrink-0 p-1 rounded" aria-label="Fermer le bandeau" @click="bannerVisible = false">
                    <span class="material-symbols-outlined text-[18px]">close</span>
                </button>
            </div>
        </div>

        <header id="site-header" class="sticky top-0 z-50">
            <div class="site-header-accent" />
            <nav class="flex justify-between items-center w-full px-4 md:px-8 max-w-container-max-width mx-auto h-[72px] md:h-20 gap-3">
                <div class="flex items-center gap-2 md:gap-3 min-w-0">
                    <button
                        type="button"
                        class="site-menu-btn md:hidden w-10 h-10 flex items-center justify-center rounded-lg shrink-0"
                        aria-label="Ouvrir le menu"
                        :aria-expanded="menuOpen"
                        @click="menuOpen = !menuOpen"
                    >
                        <span class="material-symbols-outlined">{{ menuOpen ? 'close' : 'menu' }}</span>
                    </button>
                    <Link class="flex items-center gap-2 md:gap-3 min-w-0" href="/">
                        <img alt="Logo CAMA" class="h-10 w-10 md:h-12 md:w-12 object-contain shrink-0" src="/images/logo_cama.png" />
                        <span class="text-base md:text-title-lg font-headline-lg font-extrabold text-primary tracking-tight leading-tight truncate">
                            CAMA
                            <span class="hidden sm:block text-[10px] font-body-md font-normal text-on-surface-variant tracking-wide normal-case">
                                Caisse d'Assurance Maladie des Armées
                            </span>
                        </span>
                    </Link>
                </div>

                <div class="hidden md:flex items-center gap-6 lg:gap-8">
                    <div v-for="item in menu" :key="item.id" class="relative group">
                        <Link
                            v-if="!item.children?.length"
                            :href="item.href"
                            class="site-nav-link"
                            :class="{ 'is-active': isActive(item) }"
                        >
                            {{ item.label }}
                        </Link>
                        <button
                            v-else
                            type="button"
                            class="site-nav-link inline-flex items-center gap-1"
                            :class="{ 'is-active': isActive(item) }"
                        >
                            {{ item.label }}
                            <span class="material-symbols-outlined text-[15px]">expand_more</span>
                        </button>
                        <div
                            v-if="item.children?.length"
                            class="absolute left-0 top-full hidden group-hover:block bg-white border border-outline-variant rounded-lg shadow-lg py-1 min-w-[190px] z-50"
                        >
                            <Link
                                v-for="child in item.children"
                                :key="child.id"
                                :href="child.href"
                                class="block px-4 py-2 text-sm text-on-surface hover:bg-surface-container-low hover:text-primary"
                            >
                                {{ child.label }}
                            </Link>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-1 md:gap-2 shrink-0">
                    <button type="button" class="site-search-btn w-10 h-10 flex items-center justify-center rounded-lg text-on-surface-variant" aria-label="Rechercher" @click="toggleSearch">
                        <span class="material-symbols-outlined text-[22px]">search</span>
                    </button>
                    <div class="relative espace-assure-wrap">
                        <button
                            type="button"
                            class="espace-assure-btn px-3 md:px-5 py-2 rounded-lg font-bold text-xs md:text-sm flex items-center gap-1.5 md:gap-2 shadow-sm"
                            aria-haspopup="true"
                            :aria-expanded="assureOpen"
                            @click.stop="assureOpen = !assureOpen"
                        >
                            <span class="material-symbols-outlined text-[20px]">account_circle</span>
                            <span class="hidden sm:inline">Espace Assuré</span>
                            <span class="material-symbols-outlined text-[16px] hidden sm:inline">expand_more</span>
                        </button>
                        <div class="espace-dropdown absolute right-0 top-full mt-2 w-60 bg-white rounded-lg border border-outline-variant shadow-lg overflow-hidden z-50" :class="{ 'is-open': assureOpen }">
                            <Link class="flex items-center gap-3 px-4 py-3 hover:bg-surface-container-low transition-colors" :href="route('assure.login')">
                                <span class="material-symbols-outlined text-primary">login</span>
                                <span>
                                    <span class="block font-bold text-sm text-on-surface">Se connecter</span>
                                    <span class="block text-caption text-on-surface-variant">Accéder à mon espace</span>
                                </span>
                            </Link>
                            <div class="h-px bg-outline-variant" />
                            <Link class="flex items-center gap-3 px-4 py-3 hover:bg-surface-container-low transition-colors" :href="route('registration.create')">
                                <span class="material-symbols-outlined text-secondary">person_add</span>
                                <span>
                                    <span class="block font-bold text-sm text-on-surface">Créer un compte</span>
                                    <span class="block text-caption text-on-surface-variant">Enrôler ma famille</span>
                                </span>
                            </Link>
                        </div>
                    </div>
                </div>
            </nav>

            <div id="site-search-bar" :class="{ 'is-open': searchOpen }">
                <form class="max-w-container-max-width mx-auto px-4 md:px-8 flex gap-2" role="search" action="/recherche">
                    <label for="site-search-input" class="sr-only">Recherche globale</label>
                    <input id="site-search-input" type="search" name="q" class="flex-1 px-4 py-2.5 text-sm border border-outline-variant rounded-lg bg-white focus:ring-2 focus:ring-primary focus:border-primary outline-none" placeholder="Rechercher une page, actualité ou ressource…" autocomplete="off" />
                    <button type="submit" class="bg-primary text-on-primary px-4 py-2.5 rounded-lg text-sm font-bold shrink-0">Rechercher</button>
                </form>
            </div>

            <div id="site-mobile-nav" :class="{ 'is-open': menuOpen }" :aria-hidden="!menuOpen">
                <template v-for="item in menu" :key="item.id">
                    <Link class="mobile-nav-link" :class="{ 'is-active': isActive(item) }" :href="item.href" @click="closeMobile">
                        <span class="material-symbols-outlined text-[20px]">{{ item.icon }}</span>
                        {{ item.label }}
                    </Link>
                    <Link
                        v-for="child in item.children"
                        :key="child.id"
                        class="mobile-nav-link pl-9"
                        :href="child.href"
                        @click="closeMobile"
                    >
                        <span class="material-symbols-outlined text-[20px]">subdirectory_arrow_right</span>
                        {{ child.label }}
                    </Link>
                </template>
                <div class="border-t border-outline-variant mx-4 my-2" />
                <Link class="mobile-nav-link" :href="route('assure.login')" @click="closeMobile"><span class="material-symbols-outlined text-[20px]">login</span>Se connecter</Link>
                <Link class="mobile-nav-link" :href="route('registration.create')" @click="closeMobile"><span class="material-symbols-outlined text-[20px]">person_add</span>Créer un compte</Link>
            </div>
        </header>

        <main class="flex-1">
            <slot />
        </main>

        <footer id="site-footer">
            <div class="max-w-container-max-width mx-auto px-4 md:px-8 py-12 md:py-16">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-10 md:gap-12 mb-12">
                    <div class="col-span-1">
                        <div class="flex items-center gap-3 mb-5">
                            <img alt="Logo CAMA" class="h-11 w-11 object-contain bg-white rounded-full p-0.5" src="/images/logo_cama.png" />
                            <span class="text-xl font-bold text-white font-headline-lg">CAMA</span>
                        </div>
                        <p v-if="footer.tagline" class="text-surface-variant text-sm mb-5 opacity-85 leading-relaxed">
                            {{ footer.tagline }}
                        </p>
                        <div class="space-y-2 text-surface-variant text-xs">
                            <div v-if="footer.address" class="flex items-start gap-2">
                                <span class="material-symbols-outlined text-[16px] mt-0.5 shrink-0">location_on</span>
                                {{ footer.address }}
                            </div>
                            <div v-if="footerPhones.length" class="flex items-start gap-2">
                                <span class="material-symbols-outlined text-[16px] mt-0.5 shrink-0">call</span>
                                <span>
                                    <template v-for="(phone, i) in footerPhones" :key="phone">
                                        <span v-if="i > 0" class="opacity-60"> / </span>
                                        <a class="hover:text-white transition-colors" :href="telHref(phone)">{{ phone }}</a>
                                    </template>
                                </span>
                            </div>
                            <div v-for="mail in footerEmails" :key="mail" class="flex items-center gap-2">
                                <span class="material-symbols-outlined text-[16px] shrink-0">mail</span>
                                <a class="hover:text-white transition-colors" :href="`mailto:${mail}`">{{ mail }}</a>
                            </div>
                            <div v-if="footer.hours" class="flex items-start gap-2">
                                <span class="material-symbols-outlined text-[16px] mt-0.5 shrink-0">schedule</span>
                                {{ footer.hours }}
                            </div>
                        </div>
                        <a v-if="footer.play_store_url" class="inline-flex items-center gap-2 mt-5 bg-white/10 hover:bg-white/20 border border-white/20 rounded-lg px-3 py-2 transition-colors" :href="footer.play_store_url" rel="noopener" target="_blank" aria-label="Télécharger l'application CAMA sur Google Play">
                            <svg class="w-5 h-5 shrink-0" viewBox="0 0 24 24" aria-hidden="true"><path fill="#EA4335" d="M3.6 2.1 13.3 12 3.6 21.9c-.4-.2-.6-.6-.6-1.1V3.2c0-.5.2-.9.6-1.1z" opacity=".9"/><path fill="#34A853" d="M13.3 12 3.6 2.1c.1 0 .2-.1.4-.1.3 0 .5.1.8.2l11.3 6.5-2.8 3.3z"/><path fill="#FBBC04" d="m16.1 8.7 3.6 2.1c.8.5.8 1.7 0 2.2l-3.6 2.1L13.3 12l2.8-3.3z"/><path fill="#4285F4" d="m4 21.9 9.3-9.9 2.8 3.3L4.8 21.8c-.3.1-.5.2-.8.2z"/></svg>
                            <span class="leading-tight text-left">
                                <span class="block text-[9px] uppercase tracking-wide opacity-70">Disponible sur</span>
                                <span class="block text-xs font-bold text-white">Google Play</span>
                            </span>
                        </a>
                    </div>
                    <div>
                        <h4 class="font-bold mb-5 text-white uppercase text-xs tracking-widest">Institution</h4>
                        <ul class="space-y-3 text-sm">
                            <li><Link class="text-surface-variant hover:text-white transition-colors" href="/apropos">À propos &amp; Missions</Link></li>
                            <li><Link class="text-surface-variant hover:text-white transition-colors" href="/actualites">Actualités</Link></li>
                            <li v-for="link in footerLinks" :key="link.url"><a class="text-surface-variant hover:text-white transition-colors" :href="link.url" rel="noopener" target="_blank">{{ link.label }}</a></li>
                        </ul>
                    </div>
                    <div>
                        <h4 class="font-bold mb-5 text-white uppercase text-xs tracking-widest">Services</h4>
                        <ul class="space-y-3 text-sm">
                            <li><Link class="text-surface-variant hover:text-white transition-colors" href="/services">Prestations &amp; remboursements</Link></li>
                            <li><Link class="text-surface-variant hover:text-white transition-colors" :href="route('assure.login')">Espace assuré</Link></li>
                            <li><Link class="text-surface-variant hover:text-white transition-colors" href="/contact">Contact &amp; réclamations</Link></li>
                            <li><Link class="text-surface-variant hover:text-white transition-colors" href="/contact#section-carte">Cartographie &amp; antennes</Link></li>
                        </ul>
                    </div>
                    <div>
                        <h4 class="font-bold mb-5 text-white uppercase text-xs tracking-widest">Légal</h4>
                        <ul class="space-y-3 text-sm">
                            <li><Link class="text-surface-variant hover:text-white transition-colors" href="/mention_legales">Mentions légales</Link></li>
                            <li><Link class="text-surface-variant hover:text-white transition-colors" href="/mention_legales#rgpd">Confidentialité</Link></li>
                            <li><Link class="text-surface-variant hover:text-white transition-colors" href="/accessibilite">Accessibilité</Link></li>
                            <li><Link class="text-surface-variant hover:text-white transition-colors" href="/mention_legales#cookies">Cookies</Link></li>
                        </ul>
                        <div v-if="footerSocials.length" class="flex flex-wrap gap-3 mt-6">
                            <a v-for="social in footerSocials" :key="social.network + social.url" class="w-9 h-9 rounded-full border border-surface-variant/30 flex items-center justify-center hover:bg-white/10 transition-colors" :href="social.url" rel="noopener" target="_blank" :aria-label="socialLabel(social.network)">
                                <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24"><path :d="socialIcon(social.network)" /></svg>
                            </a>
                        </div>
                    </div>
                </div>
                <div class="pt-6 border-t border-white/10 flex flex-col md:flex-row justify-between items-center gap-3 text-surface-variant text-xs">
                    <p>© {{ new Date().getFullYear() }} CAMA — Caisse d'Assurance Maladie des Armées. Tous droits réservés.</p>
                    <div class="flex gap-5">
                        <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-secondary"></span> Burkina Faso</span>
                        <span>Depuis 2020</span>
                    </div>
                </div>
            </div>
        </footer>

        <CamaCookieBar />
        <CamaAnchorTop />
    </div>
</template>
