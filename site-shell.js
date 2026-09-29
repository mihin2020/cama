/**
 * Site vitrine CAMAshell partagé (header, footer, bandeau, cookies, menu mobile)
 * Usage : <body data-page="accueil"> + <div id="site-shell-top"></div> + <div id="site-shell-bottom"></div>
 */
(function () {
    'use strict';

    const BANNER_KEY = 'cama_site_banner';
    const BANNER_DISMISS_KEY = 'cama_site_banner_dismissed';
    const COOKIE_KEY = 'cama_cookie_consent';

    const NAV = [
        { id: 'accueil', label: 'Accueil', href: 'index.html', icon: 'home' },
        { id: 'apropos', label: 'À propos', href: 'apropos.html', icon: 'info' },
        { id: 'services', label: 'Services', href: 'services.html', icon: 'medical_services' },
        { id: 'ressources', label: 'Ressources', href: 'ressources.html', icon: 'folder_open' },
        { id: 'actualites', label: 'Actualités', href: 'actualite.html', icon: 'newspaper' },
        { id: 'contact', label: 'Contact', href: 'contact.html', icon: 'mail' }
    ];

    const DEFAULT_BANNER = {
        active: true,
        type: 'info',
        message: 'Campagne d\'enrôlement 2026 : créez votre espace assuré et enrôlez vos ayants droit en ligne.',
        link: 'inscription-assure.html',
        linkLabel: 'Commencer l\'enrôlement'
    };

    function getPageId() {
        return document.body.dataset.page || 'accueil';
    }

    function getBanner() {
        try {
            const stored = localStorage.getItem(BANNER_KEY);
            if (stored) return JSON.parse(stored);
        } catch (_) { /* ignore */ }
        return DEFAULT_BANNER;
    }

    function navLinkClass(id, pageId) {
        return id === pageId ? 'site-nav-link is-active' : 'site-nav-link';
    }

    function mobileLinkClass(id, pageId) {
        return id === pageId ? 'mobile-nav-link is-active' : 'mobile-nav-link';
    }

    function renderTop(pageId) {
        const banner = getBanner();
        const dismissed = sessionStorage.getItem(BANNER_DISMISS_KEY) === '1';
        const showBanner = banner.active && !dismissed;

        const desktopNav = NAV.map(n =>
            `<a class="${navLinkClass(n.id, pageId)}" href="${n.href}">${n.label}</a>`
        ).join('');

        const mobileNav = NAV.map(n =>
            `<a class="${mobileLinkClass(n.id, pageId)}" href="${n.href}">
                <span class="material-symbols-outlined text-[20px]">${n.icon}</span>${n.label}
            </a>`
        ).join('');

        const bannerHtml = showBanner ? `
            <div class="site-header-accent"></div>
            <div id="site-info-banner" class="${banner.type === 'warning' ? 'is-warning' : ''}">
                <div class="max-w-container-max-width mx-auto px-4 md:px-8 py-2 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2 min-w-0 flex-1">
                        <span class="material-symbols-outlined text-[18px] shrink-0">${banner.type === 'warning' ? 'warning' : 'campaign'}</span>
                        <p class="truncate md:whitespace-normal">${banner.message}</p>
                        ${banner.link ? `<a href="${banner.link}" class="hidden sm:inline-flex shrink-0 underline font-semibold ml-2">${banner.linkLabel || 'En savoir plus'}</a>` : ''}
                    </div>
                    <button type="button" class="banner-close shrink-0 p-1 rounded" id="banner-close" aria-label="Fermer le bandeau">
                        <span class="material-symbols-outlined text-[18px]">close</span>
                    </button>
                </div>
            </div>` : '';

        return `
            ${bannerHtml}
            <header id="site-header" class="sticky top-0 z-50">
                ${showBanner ? '' : '<div class="site-header-accent"></div>'}
                <nav class="flex justify-between items-center w-full px-4 md:px-margin-desktop max-w-container-max-width mx-auto h-[72px] md:h-20 gap-3">
                    <div class="flex items-center gap-2 md:gap-3 min-w-0">
                        <button type="button" class="site-menu-btn md:hidden w-10 h-10 flex items-center justify-center rounded-lg shrink-0" id="site-menu-toggle" aria-label="Ouvrir le menu" aria-expanded="false">
                            <span class="material-symbols-outlined" id="site-menu-icon">menu</span>
                        </button>
                        <a class="flex items-center gap-2 md:gap-3 min-w-0" href="index.html">
                            <img alt="Logo CAMA" class="h-10 w-10 md:h-12 md:w-12 object-contain shrink-0" src="images/logo_cama.png"/>
                            <span class="text-base md:text-title-lg font-headline-lg font-extrabold text-primary tracking-tight leading-tight truncate">
                                CAMA
                                <span class="hidden sm:block text-[10px] font-body-md font-normal text-on-surface-variant tracking-wide normal-case">Caisse d'Assurance Maladie des Armées</span>
                            </span>
                        </a>
                    </div>
                    <div class="hidden md:flex items-center gap-6 lg:gap-8">${desktopNav}</div>
                    <div class="flex items-center gap-1 md:gap-2 shrink-0">
                        <button type="button" class="site-search-btn w-10 h-10 flex items-center justify-center rounded-lg text-on-surface-variant" id="site-search-toggle" aria-label="Rechercher">
                            <span class="material-symbols-outlined text-[22px]">search</span>
                        </button>
                        <div class="relative espace-assure-wrap">
                            <button type="button" class="espace-assure-btn px-3 md:px-5 py-2 rounded-lg font-bold text-xs md:text-sm flex items-center gap-1.5 md:gap-2 shadow-sm" id="espace-assure-toggle" aria-haspopup="true" aria-expanded="false">
                                <span class="material-symbols-outlined text-[20px]">account_circle</span>
                                <span class="hidden sm:inline">Espace Assuré</span>
                                <span class="material-symbols-outlined text-[16px] hidden sm:inline">expand_more</span>
                            </button>
                            <div class="espace-dropdown absolute right-0 top-full mt-2 w-60 bg-white rounded-lg border border-outline-variant shadow-lg overflow-hidden z-50" id="espace-dropdown">
                                <a class="flex items-center gap-3 px-4 py-3 hover:bg-surface-container-low transition-colors" href="espace-assure.html">
                                    <span class="material-symbols-outlined text-primary">login</span>
                                    <span>
                                        <span class="block font-bold text-sm text-on-surface">Se connecter</span>
                                        <span class="block text-caption text-on-surface-variant">Accéder à mon espace</span>
                                    </span>
                                </a>
                                <div class="h-px bg-outline-variant"></div>
                                <a class="flex items-center gap-3 px-4 py-3 hover:bg-surface-container-low transition-colors" href="inscription-assure.html">
                                    <span class="material-symbols-outlined text-secondary">person_add</span>
                                    <span>
                                        <span class="block font-bold text-sm text-on-surface">Créer un compte</span>
                                        <span class="block text-caption text-on-surface-variant">Enrôler ma famille</span>
                                    </span>
                                </a>
                                <div class="h-px bg-outline-variant"></div>
                                <a class="flex items-center gap-3 px-4 py-3 hover:bg-surface-container-low transition-colors" href="assure/dashboard.html">
                                    <span class="material-symbols-outlined text-tertiary">dashboard</span>
                                    <span>
                                        <span class="block font-bold text-sm text-on-surface">Tableau de bord</span>
                                        <span class="block text-caption text-on-surface-variant">Démo espace assuré</span>
                                    </span>
                                </a>
                            </div>
                        </div>
                    </div>
                </nav>
                <div id="site-search-bar">
                    <form class="max-w-container-max-width mx-auto px-4 md:px-margin-desktop flex gap-2" id="site-search-form" role="search">
                        <input type="search" name="q" class="flex-1 px-4 py-2.5 text-sm border border-outline-variant rounded-lg bg-white focus:ring-2 focus:ring-primary focus:border-primary outline-none" placeholder="Rechercher une actualité, un service…" autocomplete="off"/>
                        <button type="submit" class="bg-primary text-on-primary px-4 py-2.5 rounded-lg text-sm font-bold shrink-0">Rechercher</button>
                    </form>
                </div>
                <div id="site-mobile-nav" aria-hidden="true">${mobileNav}
                    <div class="border-t border-outline-variant mx-4 my-2"></div>
                    <a class="mobile-nav-link" href="espace-assure.html"><span class="material-symbols-outlined text-[20px]">login</span>Se connecter</a>
                    <a class="mobile-nav-link" href="inscription-assure.html"><span class="material-symbols-outlined text-[20px]">person_add</span>Créer un compte</a>
                </div>
            </header>`;
    }

    function renderBottom() {
        return `
            <footer id="site-footer">
                <div class="max-w-container-max-width mx-auto px-4 md:px-margin-desktop py-12 md:py-16">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-10 md:gap-12 mb-12">
                        <div class="col-span-1">
                            <div class="flex items-center gap-3 mb-5">
                                <img alt="Logo CAMA" class="h-11 w-11 object-contain bg-white rounded-full p-0.5" src="images/logo_cama.png"/>
                                <span class="text-xl font-bold text-white font-headline-lg">CAMA</span>
                            </div>
                            <p class="text-surface-variant text-sm mb-5 opacity-85 leading-relaxed">
                                Caisse d'Assurance Maladie des Armées du Burkina Faso. « La santé de nos héros, notre priorité ! »
                            </p>
                            <div class="space-y-2 text-surface-variant text-xs">
                                <div class="flex items-start gap-2">
                                    <span class="material-symbols-outlined text-[16px] mt-0.5 shrink-0">location_on</span>
                                    Ex-État-Major Général des Armées, Bilbalogho, Ouagadougou
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="material-symbols-outlined text-[16px]">call</span>
                                    +226 25 30 XX XX
                                </div>
                            </div>
                        </div>
                        <div>
                            <h4 class="font-bold mb-5 text-white uppercase text-xs tracking-widest">Institution</h4>
                            <ul class="space-y-3 text-sm">
                                <li><a class="text-surface-variant hover:text-white transition-colors" href="apropos.html">À propos &amp; Missions</a></li>
                                <li><a class="text-surface-variant hover:text-white transition-colors" href="actualite.html">Actualités</a></li>
                                <li><a class="text-surface-variant hover:text-white transition-colors" href="https://www.defense.gov.bf" rel="noopener" target="_blank">Ministère de la Guerre et de la Défense patriotique</a></li>
                                <li><a class="text-surface-variant hover:text-white transition-colors" href="https://www.gouvernement.gov.bf" rel="noopener" target="_blank">Gouvernement</a></li>
                            </ul>
                        </div>
                        <div>
                            <h4 class="font-bold mb-5 text-white uppercase text-xs tracking-widest">Services</h4>
                            <ul class="space-y-3 text-sm">
                                <li><a class="text-surface-variant hover:text-white transition-colors" href="services.html">Prestations &amp; remboursements</a></li>
                                <li><a class="text-surface-variant hover:text-white transition-colors" href="espace-assure.html">Espace assuré</a></li>
                                <li><a class="text-surface-variant hover:text-white transition-colors" href="contact.html">Contact &amp; réclamations</a></li>
                                <li><a class="text-surface-variant hover:text-white transition-colors" href="contact.html#section-carte">Cartographie &amp; antennes</a></li>
                            </ul>
                        </div>
                        <div>
                            <h4 class="font-bold mb-5 text-white uppercase text-xs tracking-widest">Légal</h4>
                            <ul class="space-y-3 text-sm">
                                <li><a class="text-surface-variant hover:text-white transition-colors" href="mention_legales.html">Mentions légales</a></li>
                                <li><a class="text-surface-variant hover:text-white transition-colors" href="mention_legales.html#rgpd">Confidentialité</a></li>
                                <li><a class="text-surface-variant hover:text-white transition-colors" href="accessibilite.html">Accessibilité</a></li>
                                <li><a class="text-surface-variant hover:text-white transition-colors" href="mention_legales.html#cookies">Cookies</a></li>
                            </ul>
                            <div class="flex gap-3 mt-6">
                                <a class="w-9 h-9 rounded-full border border-surface-variant/30 flex items-center justify-center hover:bg-white/10 transition-colors" href="#" aria-label="Facebook"><svg class="w-4 h-4 fill-current" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg></a>
                                <a class="w-9 h-9 rounded-full border border-surface-variant/30 flex items-center justify-center hover:bg-white/10 transition-colors" href="#" aria-label="X"><svg class="w-4 h-4 fill-current" viewBox="0 0 24 24"><path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.84 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/></svg></a>
                            </div>
                        </div>
                    </div>
                    <div class="pt-6 border-t border-white/10 flex flex-col md:flex-row justify-between items-center gap-3 text-surface-variant text-xs">
                        <p>© 2026 CAMACaisse d'Assurance Maladie des Armées. Tous droits réservés.</p>
                        <div class="flex gap-5">
                            <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-secondary"></span> Burkina Faso</span>
                            <span>Depuis 2020</span>
                        </div>
                    </div>
                </div>
            </footer>
            <div id="site-cookie-bar" role="dialog" aria-label="Consentement cookies">
                <div class="max-w-container-max-width mx-auto px-4 md:px-margin-desktop py-4 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                    <div class="flex items-start gap-3 flex-1">
                        <span class="material-symbols-outlined text-primary shrink-0 mt-0.5">cookie</span>
                        <p class="text-sm text-on-surface leading-snug">Ce site utilise des cookies essentiels au fonctionnement et, avec votre accord, des cookies de mesure d'audience. <a href="mention_legales.html#cookies" class="text-primary font-semibold underline">En savoir plus</a></p>
                    </div>
                    <div class="flex gap-2 shrink-0 w-full sm:w-auto">
                        <button type="button" class="flex-1 sm:flex-none px-4 py-2 text-sm font-bold border border-outline-variant rounded-lg hover:bg-surface-container-low" id="cookie-refuse">Essentiels uniquement</button>
                        <button type="button" class="flex-1 sm:flex-none px-4 py-2 text-sm font-bold bg-primary text-on-primary rounded-lg hover:opacity-90" id="cookie-accept">Tout accepter</button>
                    </div>
                </div>
            </div>`;
    }

    function bindEvents() {
        const menuToggle = document.getElementById('site-menu-toggle');
        const mobileNav = document.getElementById('site-mobile-nav');
        const menuIcon = document.getElementById('site-menu-icon');
        const searchToggle = document.getElementById('site-search-toggle');
        const searchBar = document.getElementById('site-search-bar');
        const searchForm = document.getElementById('site-search-form');
        const espaceToggle = document.getElementById('espace-assure-toggle');
        const espaceDropdown = document.getElementById('espace-dropdown');
        const bannerClose = document.getElementById('banner-close');
        const cookieBar = document.getElementById('site-cookie-bar');
        const cookieAccept = document.getElementById('cookie-accept');
        const cookieRefuse = document.getElementById('cookie-refuse');

        let menuOpen = false;
        let searchOpen = false;

        function closeMenu() {
            menuOpen = false;
            mobileNav.classList.remove('is-open');
            mobileNav.setAttribute('aria-hidden', 'true');
            menuToggle.setAttribute('aria-expanded', 'false');
            menuIcon.textContent = 'menu';
            document.body.classList.remove('site-nav-open');
        }

        function closeSearch() {
            searchOpen = false;
            searchBar.classList.remove('is-open');
        }

        menuToggle?.addEventListener('click', () => {
            menuOpen = !menuOpen;
            if (menuOpen) {
                closeSearch();
                mobileNav.classList.add('is-open');
                mobileNav.setAttribute('aria-hidden', 'false');
                menuToggle.setAttribute('aria-expanded', 'true');
                menuIcon.textContent = 'close';
                document.body.classList.add('site-nav-open');
            } else {
                closeMenu();
            }
        });

        searchToggle?.addEventListener('click', () => {
            searchOpen = !searchOpen;
            if (searchOpen) {
                closeMenu();
                searchBar.classList.add('is-open');
                searchBar.querySelector('input')?.focus();
            } else {
                closeSearch();
            }
        });

        searchForm?.addEventListener('submit', (e) => {
            e.preventDefault();
            const q = new FormData(searchForm).get('q')?.toString().trim();
            if (q) {
                window.location.href = `actualite.html?q=${encodeURIComponent(q)}`;
            }
        });

        espaceToggle?.addEventListener('click', (e) => {
            e.stopPropagation();
            const open = espaceDropdown.classList.toggle('is-open');
            espaceToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });

        document.addEventListener('click', () => {
            espaceDropdown?.classList.remove('is-open');
            espaceToggle?.setAttribute('aria-expanded', 'false');
        });

        bannerClose?.addEventListener('click', () => {
            document.getElementById('site-info-banner')?.classList.add('is-hidden');
            sessionStorage.setItem(BANNER_DISMISS_KEY, '1');
        });

        function hideCookieBar(choice) {
            localStorage.setItem(COOKIE_KEY, choice);
            cookieBar.classList.remove('is-visible');
            setTimeout(() => cookieBar.classList.add('is-hidden'), 400);
        }

        cookieAccept?.addEventListener('click', () => hideCookieBar('all'));
        cookieRefuse?.addEventListener('click', () => hideCookieBar('essential'));

        if (!localStorage.getItem(COOKIE_KEY)) {
            cookieBar.classList.remove('is-hidden');
            requestAnimationFrame(() => cookieBar.classList.add('is-visible'));
        } else {
            cookieBar.classList.add('is-hidden');
        }

        window.addEventListener('resize', () => {
            if (window.innerWidth >= 768) closeMenu();
            updateHeaderOffset();
        });
    }

    let headerResizeObserver = null;

    function updateHeaderOffset() {
        const header = document.getElementById('site-header');
        if (!header) return;
        const height = Math.ceil(header.getBoundingClientRect().height);
        document.documentElement.style.setProperty('--site-header-height', `${height}px`);
    }

    function observeHeaderHeight() {
        headerResizeObserver?.disconnect();
        const header = document.getElementById('site-header');
        if (!header) return;
        updateHeaderOffset();
        if (typeof ResizeObserver === 'undefined') return;
        headerResizeObserver = new ResizeObserver(updateHeaderOffset);
        headerResizeObserver.observe(header);
    }

    function initAnchorTop() {
        const run = () => window.CamaAnchorTop?.init();
        if (window.CamaAnchorTop) {
            run();
            return;
        }
        const p = window.location.pathname.replace(/\\/g, '/');
        let src = 'anchor-top.js';
        if (p.includes('/admin/cms/') || p.includes('/assure/')) src = '../../anchor-top.js';
        else if (p.includes('/admin/')) src = '../anchor-top.js';
        const existing = document.querySelector('script[data-cama-anchor-top]');
        if (existing) {
            existing.addEventListener('load', run, { once: true });
            return;
        }
        const script = document.createElement('script');
        script.src = src;
        script.dataset.camaAnchorTop = '1';
        script.onload = run;
        document.head.appendChild(script);
    }

    function init() {
        const pageId = getPageId();
        const topMount = document.getElementById('site-shell-top');
        const bottomMount = document.getElementById('site-shell-bottom');

        if (topMount) topMount.innerHTML = renderTop(pageId);
        if (bottomMount) bottomMount.innerHTML = renderBottom();

        bindEvents();
        observeHeaderHeight();
        initAnchorTop();
        document.dispatchEvent(new CustomEvent('cama:site-shell-ready'));
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    window.CamaSiteShell = {
        setBanner(config) {
            localStorage.setItem(BANNER_KEY, JSON.stringify(config));
            sessionStorage.removeItem(BANNER_DISMISS_KEY);
            init();
        },
        getBanner
    };
})();
