/**
 * Contenu public CAMAlocalStorage partagé entre le site et le CMS.
 */
(function (global) {
    const SLIDES_KEY = 'cama_site_slides';
    const CHIFFRES_KEY = 'cama_site_chiffres';
    const ARTICLES_KEY = 'cama_site_articles';
    const FAQ_KEY = 'cama_site_faq';

    const DEFAULT_SLIDES = [
        { id: 1, titre: 'La santé de nos héros, notre priorité', sousTitre: 'Lancée officiellement le 13 février 2025, la CAMA assure une couverture santé robuste aux militaires et à leurs familles.', image: 'images/CAMA_8.jfif', ordre: 1, actif: true, lien: 'espace-assure.html', lienLabel: 'Espace Assuré' },
        { id: 2, titre: 'Une solidarité au service des forces armées', sousTitre: 'Une cotisation de 5,5 % pour une prise en charge à 80 % des soins, dans la dignité et la transparence.', image: 'images/CAMA_6.jfif', ordre: 2, actif: true, lien: 'services.html', lienLabel: 'Nos prestations' },
        { id: 3, titre: 'Une institution moderne et accessible', sousTitre: 'Successeur de la MUFAN, la CAMA poursuit la digitalisation de ses services pour mieux servir ses assurés.', image: 'images/CAMA_1.jfif', ordre: 3, actif: true, lien: 'actualite.html', lienLabel: 'Voir les actualités' }
    ];

    const DEFAULT_CHIFFRES = [
        { id: 1, valeur: 5.5, suffixe: '%', libelle: 'Taux de cotisation mensuelle', icone: 'percent', ordre: 1 },
        { id: 2, valeur: 150, suffixe: '+', libelle: 'Structures de soins partenaires', icone: 'local_hospital', ordre: 2 },
        { id: 3, valeur: 250, suffixe: 'k', libelle: 'Bénéficiaires couverts', icone: 'groups', ordre: 3 },
        { id: 4, valeur: 2020, suffixe: '', libelle: 'Année de fondation (MUFAN)', icone: 'history', ordre: 4 }
    ];

    const DEFAULT_ARTICLES = [
        {
            id: 1,
            slug: 'lancement-officiel-cama',
            titre: "Lancement officiel de la Caisse d'Assurance Maladie des Armées",
            categorie: 'Institution',
            statut: 'Publié',
            auteur: 'CAMA',
            date: '13/02/2025',
            image: 'images/CAMA_8.jfif',
            resume: "Au siège de l'ex-État-Major Général des Armées à Bilbalogho, la CAMA a été officiellement lancée en présence du Ministre d'État chargé de la Défense.",
            contenu: "<p>Au siège de l'ex-État-Major Général des Armées à Bilbalogho, la CAMA a été officiellement lancée en présence du Général de Brigade Céléstin Simporé, Ministre d'État chargé de la Défense.</p><p>L'institution succède à la MUFAN et élargit la couverture santé aux conjoints et enfants des militaires, conformément au décret n°2020-0272.</p>",
            featured: true
        },
        {
            id: 2,
            slug: 'inauguration-siege-bilbalogho',
            titre: 'Inauguration du siège de la CAMA à Bilbalogho',
            categorie: 'Institution',
            statut: 'Publié',
            auteur: 'CAMA',
            date: '13/02/2025',
            image: 'images/CAMA_1.jfif',
            resume: "Les locaux de l'ex-État-Major Général des Armées accueillent désormais la direction générale de la CAMA.",
            contenu: "<p>Les locaux de l'ex-État-Major Général des Armées accueillent désormais la direction générale de la CAMA, au cœur de Ouagadougou.</p><p>Ce site centralise l'accueil des assurés, le traitement des dossiers et la coordination avec les antennes régionales.</p>"
        },
        {
            id: 3,
            slug: 'coupure-ruban-cama',
            titre: 'Coupure du ruban : la CAMA ouvre officiellement ses portes',
            categorie: 'Événement',
            statut: 'Publié',
            auteur: 'CAMA',
            date: '13/02/2025',
            image: 'images/CAMA_6.jfif',
            resume: 'Une cérémonie solennelle a marqué le démarrage des activités de la caisse au bénéfice des militaires et de leurs familles.',
            contenu: '<p>Une cérémonie solennelle a marqué le démarrage des activités de la caisse au bénéfice des militaires et de leurs familles.</p><p>Les autorités ont salué une étape majeure dans la modernisation de la protection sociale des Forces Armées Nationales.</p>'
        },
        {
            id: 4,
            slug: 'allocution-secretaire-general',
            titre: 'Allocution du Secrétaire Général du Ministère de la Défense',
            categorie: 'Institution',
            statut: 'Publié',
            auteur: 'CAMA',
            date: '13/11/2025',
            image: 'images/CAMA_4.jfif',
            resume: 'Lors de la passation de service, la CAMA a été présentée comme un instrument de solidarité pour les forces armées.',
            contenu: "<p>Lors de la passation de service, la CAMA a été présentée comme « un instrument de solidarité, de dignité et de stabilité » pour les forces armées.</p><p>Le pharmacien lieutenant-colonel Ousmane Sinaré a été installé à la tête de l'institution.</p>"
        },
        {
            id: 5,
            slug: 'campagne-sensibilisation-2026',
            titre: 'Campagne de sensibilisation 2026 sur la couverture santé',
            categorie: 'Communiqué',
            statut: 'Publié',
            auteur: 'Cdt. Paul SAWADOGO',
            date: '18/06/2026',
            image: 'images/CAMA_3.3.jfif',
            resume: 'La CAMA lance une campagne nationale pour informer les militaires et leurs familles sur leurs droits et démarches.',
            contenu: '<p>La CAMA lance une campagne nationale pour informer les militaires et leurs familles sur leurs droits, les prestations couvertes et les démarches d\'enrôlement en ligne.</p>'
        },
        {
            id: 6,
            slug: 'partenariat-chu-yalgado',
            titre: "Signature d'un partenariat avec le CHU Yalgado Ouédraogo",
            categorie: 'Partenariat',
            statut: 'Publié',
            auteur: 'Ing. Awa OUÉDRAOGO',
            date: '10/06/2026',
            image: 'images/CAMA_7.jfif',
            resume: 'Un accord de prise en charge renforce le réseau de soins conventionnés pour les assurés CAMA.',
            contenu: '<p>Un accord de prise en charge renforce le réseau de soins conventionnés pour les assurés CAMA à Ouagadougou et dans la région du Centre.</p>'
        }
    ];

    const DEFAULT_FAQ = [
        { id: 1, question: 'Comment obtenir un remboursement pour une consultation ?', reponse: "Présentez votre carte d'assuré CAMA lors de la consultation. Pour les établissements conventionnés, le tiers-payant peut s'appliquer. Sinon, déposez votre feuille de soins et la facture originale à votre guichet CAMA habituel.", categorie: 'Remboursements', ordre: 1, publie: true },
        { id: 2, question: "Qu'est-ce qu'une demande d'entente préalable ?", reponse: "C'est une demande formelle à remplir par votre médecin avant certains actes coûteux (actes chirurgicaux, IRM, prothèses), afin que la CAMA valide la prise en charge avant l'intervention.", categorie: 'Prestations', ordre: 1, publie: true },
        { id: 3, question: 'Mes ayants-droit bénéficient-ils des mêmes tarifs ?', reponse: "Oui, les membres de votre famille (conjoint et enfants) inscrits sur votre dossier bénéficient du même panier de soins et des mêmes taux de remboursement que l'assuré principal.", categorie: 'Enrôlement', ordre: 1, publie: true },
        { id: 4, question: 'Comment se fait le remboursement ?', reponse: 'Les remboursements sont effectués par virement bancaire après réception et traitement complet de votre dossier par les services de la CAMA.', categorie: 'Remboursements', ordre: 2, publie: true },
        { id: 5, question: 'Qui peut s\'inscrire sur la plateforme ?', reponse: 'Tout militaire en fonction et ayant un numéro CAMA valide peut créer un compte assuré.', categorie: 'Compte assuré', ordre: 1, publie: true },
        { id: 6, question: 'Quels justificatifs pour un enfant ?', reponse: 'Acte de naissance, copie CNIB du parent assuré, et certificat de scolarité le cas échéant.', categorie: 'Enrôlement', ordre: 2, publie: true }
    ];

    const FAQ_ICONS = {
        Prestations: { icon: 'receipt_long', color: 'primary' },
        Remboursements: { icon: 'account_balance_wallet', color: 'on-background' },
        Enrôlement: { icon: 'diversity_3', color: 'tertiary' },
        'Compte assuré': { icon: 'manage_accounts', color: 'secondary' }
    };

    function readJson(key, fallback) {
        try {
            const raw = localStorage.getItem(key);
            if (!raw) return fallback.slice();
            const parsed = JSON.parse(raw);
            return Array.isArray(parsed) ? parsed : fallback.slice();
        } catch {
            return fallback.slice();
        }
    }

    function writeJson(key, data) {
        localStorage.setItem(key, JSON.stringify(data));
    }

    function getSlides() {
        return readJson(SLIDES_KEY, DEFAULT_SLIDES)
            .filter(s => s.actif !== false)
            .sort((a, b) => (a.ordre || 0) - (b.ordre || 0));
    }

    function getChiffres() {
        return readJson(CHIFFRES_KEY, DEFAULT_CHIFFRES)
            .sort((a, b) => (a.ordre || 0) - (b.ordre || 0));
    }

    function getArticles() {
        const stored = readJson(ARTICLES_KEY, DEFAULT_ARTICLES);
        return stored.length ? stored : DEFAULT_ARTICLES.slice();
    }

    function saveArticles(articles) {
        writeJson(ARTICLES_KEY, articles);
    }

    function getFaq() {
        const stored = readJson(FAQ_KEY, DEFAULT_FAQ);
        return stored.length ? stored : DEFAULT_FAQ.slice();
    }

    function saveFaq(items) {
        writeJson(FAQ_KEY, items);
    }

    function getPublishedFaq() {
        return getFaq()
            .filter(f => f.publie !== false)
            .sort((a, b) => (a.ordre || 0) - (b.ordre || 0));
    }

    function getPublishedArticles() {
        return getArticles().filter(a => a.statut === 'Publié');
    }

    function getArticle(id) {
        const num = parseInt(id, 10);
        return getArticles().find(a => a.id === num || a.slug === id) || null;
    }

    function articleUrl(article) {
        return `article.html?id=${article.id}`;
    }

    function escapeHtml(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function formatDateShort(dateStr) {
        if (!dateStr || dateStr === '—') return '';
        const parts = dateStr.split('/');
        if (parts.length !== 3) return dateStr;
        const months = ['JAN', 'FÉV', 'MAR', 'AVR', 'MAI', 'JUN', 'JUL', 'AOÛ', 'SEP', 'OCT', 'NOV', 'DÉC'];
        return `${parts[0]} ${months[parseInt(parts[1], 10) - 1] || ''} ${parts[2]}`;
    }

    function initHomeHero() {
        const root = document.getElementById('hero-carousel');
        if (!root) return;

        const slides = getSlides();
        if (!slides.length) return;

        const slideHtml = slides.map((slide, i) => `
            <div class="hero-slide ${i === 0 ? 'is-active opacity-100' : 'opacity-0'} absolute inset-0">
                <div class="hero-slide-bg absolute inset-0 bg-cover bg-center" style="background-image: linear-gradient(100deg, rgba(20,20,20,0.78) 20%, rgba(20,20,20,0.35) 65%, rgba(20,20,20,0.15) 100%), url('${escapeHtml(slide.image)}');"></div>
                <div class="relative z-10 h-full max-w-container-max-width mx-auto px-4 md:px-margin-desktop flex items-center">
                    <div class="hero-slide-content max-w-2xl space-y-stack-md">
                        <h1 class="text-white font-headline-lg text-headline-lg md:text-[46px] md:leading-[1.12] leading-tight drop-shadow-md">${escapeHtml(slide.titre)}</h1>
                        <p class="text-white/90 font-body-lg text-body-lg max-w-xl">${escapeHtml(slide.sousTitre)}</p>
                        ${slide.lien ? `<div class="flex flex-wrap gap-4 pt-2"><a class="bg-primary-container text-on-primary-container px-8 py-4 rounded-lg font-bold text-lg hover:bg-primary transition-all shadow-lg active:scale-95" href="${escapeHtml(slide.lien)}">${escapeHtml(slide.lienLabel || 'En savoir plus')}</a></div>` : ''}
                    </div>
                </div>
            </div>`).join('');

        const dotsHtml = slides.map((_, i) => `
            <button aria-label="Slide ${i + 1}" class="hero-dot ${i === 0 ? 'is-active' : ''} relative w-10 h-1 rounded-full bg-white/30 overflow-hidden">
                <span class="hero-progress-fill absolute inset-y-0 left-0 bg-white block"></span>
            </button>`).join('');

        root.innerHTML = `
            ${slideHtml}
            <div class="absolute top-6 right-6 md:right-10 z-20 flex items-center gap-1 text-white font-headline-md font-bold tracking-wide">
                <span class="hero-counter-current text-2xl">01</span>
                <span class="text-white/50 text-base mx-1">/</span>
                <span class="text-white/50 text-base">${String(slides.length).padStart(2, '0')}</span>
            </div>
            <button aria-label="Image précédente" class="hero-prev hidden md:flex absolute left-4 md:left-6 top-1/2 -translate-y-1/2 z-20 w-11 h-11 rounded-full bg-white/10 hover:bg-white/25 border border-white/20 backdrop-blur-md text-white items-center justify-center transition-all active:scale-90">
                <span class="material-symbols-outlined">chevron_left</span>
            </button>
            <button aria-label="Image suivante" class="hero-next hidden md:flex absolute right-4 md:right-6 top-1/2 -translate-y-1/2 z-20 w-11 h-11 rounded-full bg-white/10 hover:bg-white/25 border border-white/20 backdrop-blur-md text-white items-center justify-center transition-all active:scale-90">
                <span class="material-symbols-outlined">chevron_right</span>
            </button>
            <div class="hero-dots absolute bottom-7 left-1/2 -translate-x-1/2 z-20 flex gap-2">${dotsHtml}</div>`;

        bindHeroCarousel(root);
    }

    function bindHeroCarousel(root) {
        const slides = root.querySelectorAll('.hero-slide');
        const dots = root.querySelectorAll('.hero-dot');
        const fills = root.querySelectorAll('.hero-progress-fill');
        const counter = root.querySelector('.hero-counter-current');
        const INTERVAL = 6000;
        let current = 0;
        let timer;

        function show(index) {
            slides[current].classList.replace('opacity-100', 'opacity-0');
            slides[current].classList.remove('is-active');
            dots[current].classList.remove('is-active');
            fills[current].style.transition = 'none';
            fills[current].style.width = '0%';

            current = (index + slides.length) % slides.length;

            slides[current].classList.replace('opacity-0', 'opacity-100');
            slides[current].classList.add('is-active');
            dots[current].classList.add('is-active');
            if (counter) counter.textContent = String(current + 1).padStart(2, '0');

            requestAnimationFrame(() => {
                fills[current].style.transition = `width ${INTERVAL}ms linear`;
                fills[current].style.width = '100%';
            });
        }

        function next() { show(current + 1); }
        function prev() { show(current - 1); }
        function restart() {
            clearInterval(timer);
            timer = setInterval(next, INTERVAL);
        }

        root.querySelector('.hero-next')?.addEventListener('click', () => { next(); restart(); });
        root.querySelector('.hero-prev')?.addEventListener('click', () => { prev(); restart(); });
        dots.forEach((dot, i) => dot.addEventListener('click', () => { show(i); restart(); }));

        requestAnimationFrame(() => {
            if (fills[current]) {
                fills[current].style.transition = `width ${INTERVAL}ms linear`;
                fills[current].style.width = '100%';
            }
        });
        restart();
    }

    function initHomeChiffres() {
        const grid = document.getElementById('cms-chiffres-grid');
        if (!grid) return;

        const chiffres = getChiffres();
        const palettes = [
            'bg-on-background text-white',
            'bg-primary-container text-on-primary-container',
            'bg-tertiary-container text-on-tertiary-container',
            'bg-secondary text-on-secondary'
        ];

        grid.innerHTML = chiffres.map((c, i) => `
            <div class="${palettes[i % palettes.length]} p-6 md:p-8 rounded-xl flex flex-col justify-between min-h-[160px] relative overflow-hidden group transition-all duration-300 hover:-translate-y-1 hover:shadow-xl">
                <span class="material-symbols-outlined text-4xl opacity-80 relative z-10">${escapeHtml(c.icone || 'monitoring')}</span>
                <div class="relative z-10 mt-4">
                    <div class="text-3xl md:text-4xl font-bold"><span class="js-counter" data-target="${c.valeur}" data-decimals="${Number.isInteger(c.valeur) ? 0 : 1}">0</span>${escapeHtml(c.suffixe || '')}</div>
                    <div class="text-sm font-label-md uppercase mt-1 opacity-90">${escapeHtml(c.libelle)}</div>
                </div>
            </div>`).join('');

        bindCounters(grid);
    }

    function bindCounters(scope) {
        const counters = scope.querySelectorAll('.js-counter');
        if (!counters.length) return;

        function animateCounter(el) {
            const target = parseFloat(el.dataset.target);
            const decimals = parseInt(el.dataset.decimals || '0', 10);
            const duration = 1500;
            const start = performance.now();

            function tick(now) {
                const progress = Math.min((now - start) / duration, 1);
                const eased = 1 - Math.pow(1 - progress, 3);
                const value = target * eased;
                el.textContent = decimals ? value.toFixed(decimals) : Math.round(value);
                if (progress < 1) requestAnimationFrame(tick);
            }
            requestAnimationFrame(tick);
        }

        const observer = new IntersectionObserver((entries, obs) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    animateCounter(entry.target);
                    obs.unobserve(entry.target);
                }
            });
        }, { threshold: 0.5 });

        counters.forEach(el => observer.observe(el));
    }

    function initHomeNews() {
        const mount = document.getElementById('cms-home-news');
        if (!mount) return;

        const published = getPublishedArticles();
        if (!published.length) {
            mount.innerHTML = '<p class="text-on-surface-variant text-center py-8">Aucune actualité publiée pour le moment.</p>';
            return;
        }

        const featured = published.find(a => a.featured) || published[0];
        const others = published.filter(a => a.id !== featured.id).slice(0, 2);

        mount.innerHTML = `
            <article class="lg:col-span-2 group relative rounded-xl overflow-hidden border border-outline-variant shadow-sm hover:shadow-2xl transition-all duration-300">
                <div class="relative h-72 lg:h-full min-h-[420px] overflow-hidden">
                    <img alt="${escapeHtml(featured.titre)}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700" src="${escapeHtml(featured.image || 'images/CAMA_8.jfif')}"/>
                    <div class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/30 to-transparent"></div>
                    <span class="absolute top-5 left-5 bg-primary text-on-primary px-3 py-1 rounded text-xs font-bold uppercase tracking-wider shadow-sm">À la une</span>
                    <div class="absolute bottom-0 left-0 right-0 p-6 lg:p-8 text-white">
                        <span class="text-sm font-label-md text-white/80 mb-2 block">${formatDateShort(featured.date)}</span>
                        <h3 class="font-headline-md text-headline-md mb-3 max-w-xl">${escapeHtml(featured.titre)}</h3>
                        <p class="text-white/85 font-body-md mb-5 max-w-xl line-clamp-2 hidden md:block">${escapeHtml(featured.resume)}</p>
                        <a class="inline-flex items-center gap-2 font-bold text-white group/btn" href="${articleUrl(featured)}">
                            Lire la suite <span class="material-symbols-outlined text-[18px] group-hover/btn:translate-x-1 transition-transform">arrow_forward</span>
                        </a>
                    </div>
                </div>
            </article>
            <div class="flex flex-col gap-stack-lg">
                ${others.map(a => `
                <article class="group flex-1 flex gap-4 bg-white rounded-xl border border-outline-variant p-4 hover:shadow-lg hover:border-primary/30 transition-all duration-300">
                    <div class="w-24 h-24 shrink-0 rounded-lg overflow-hidden">
                        <img alt="${escapeHtml(a.titre)}" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500" src="${escapeHtml(a.image || 'images/CAMA_6.jfif')}"/>
                    </div>
                    <div class="flex flex-col min-w-0">
                        <span class="text-xs font-label-md text-tertiary uppercase tracking-wide mb-1">${formatDateShort(a.date)}</span>
                        <h3 class="font-title-lg text-title-lg leading-snug mb-2 line-clamp-2 group-hover:text-primary transition-colors">${escapeHtml(a.titre)}</h3>
                        <p class="text-on-surface-variant text-sm font-body-md mb-3 line-clamp-2">${escapeHtml(a.resume)}</p>
                        <a class="mt-auto text-primary text-sm font-bold flex items-center gap-1 group/btn" href="${articleUrl(a)}">
                            Lire la suite <span class="material-symbols-outlined text-[16px] group-hover/btn:translate-x-1 transition-transform">chevron_right</span>
                        </a>
                    </div>
                </article>`).join('')}
            </div>`;
    }

    function initActualitesList() {
        const featuredMount = document.getElementById('news-featured');
        const gridMount = document.getElementById('news-grid');
        const paginationMount = document.getElementById('news-pagination');
        if (!gridMount) return;

        let activeCategory = 'Tout';
        let searchQuery = '';
        let currentPage = 1;
        const perPage = 6;

        const params = new URLSearchParams(window.location.search);
        if (params.get('q')) searchQuery = params.get('q').trim();

        const searchInput = document.getElementById('news-search');
        if (searchInput) {
            searchInput.value = searchQuery;
            searchInput.addEventListener('input', () => {
                searchQuery = searchInput.value.trim().toLowerCase();
                currentPage = 1;
                render();
            });
        }

        const categories = ['Tout', ...new Set(getPublishedArticles().map(a => a.categorie).filter(Boolean))];
        const filtersMount = document.getElementById('news-filters');
        if (filtersMount) {
            filtersMount.innerHTML = categories.map(cat => `
                <button type="button" data-cat="${escapeHtml(cat)}" class="news-filter px-4 py-2 rounded-full font-label-md text-label-md transition-colors ${cat === activeCategory ? 'bg-primary text-on-primary' : 'bg-surface-container-high text-on-surface-variant hover:bg-outline-variant'}">${escapeHtml(cat)}</button>
            `).join('');
            filtersMount.querySelectorAll('.news-filter').forEach(btn => {
                btn.addEventListener('click', () => {
                    activeCategory = btn.dataset.cat;
                    currentPage = 1;
                    filtersMount.querySelectorAll('.news-filter').forEach(b => {
                        const on = b.dataset.cat === activeCategory;
                        b.classList.toggle('bg-primary', on);
                        b.classList.toggle('text-on-primary', on);
                        b.classList.toggle('bg-surface-container-high', !on);
                        b.classList.toggle('text-on-surface-variant', !on);
                    });
                    render();
                });
            });
        }

        function filtered() {
            return getPublishedArticles().filter(a => {
                const matchCat = activeCategory === 'Tout' || a.categorie === activeCategory;
                const q = searchQuery.toLowerCase();
                const matchSearch = !q || a.titre.toLowerCase().includes(q) || (a.resume || '').toLowerCase().includes(q);
                return matchCat && matchSearch;
            });
        }

        function render() {
            const all = filtered();
            const featured = all.find(a => a.featured) || all[0];
            const list = all.filter(a => !featured || a.id !== featured.id);
            const totalPages = Math.max(1, Math.ceil(list.length / perPage));
            if (currentPage > totalPages) currentPage = totalPages;
            const pageItems = list.slice((currentPage - 1) * perPage, currentPage * perPage);

            if (featuredMount && featured) {
                featuredMount.innerHTML = `
                    <article class="lg:col-span-8 group relative overflow-hidden rounded-xl bg-white border border-outline-variant shadow-sm hover:shadow-md transition-shadow">
                        <div class="aspect-video overflow-hidden">
                            <img alt="${escapeHtml(featured.titre)}" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105" src="${escapeHtml(featured.image || 'images/CAMA_8.jfif')}"/>
                        </div>
                        <div class="p-6 md:p-8">
                            <div class="flex items-center gap-3 mb-4">
                                <span class="bg-primary text-on-primary px-3 py-1 rounded text-caption font-bold uppercase tracking-wider">${escapeHtml(featured.categorie)}</span>
                                <span class="text-on-surface-variant font-label-md text-label-md">${escapeHtml(featured.date)}</span>
                            </div>
                            <h2 class="font-headline-md text-headline-md mb-4 text-on-surface group-hover:text-primary transition-colors">${escapeHtml(featured.titre)}</h2>
                            <p class="font-body-md text-body-md text-on-surface-variant mb-6 line-clamp-3">${escapeHtml(featured.resume)}</p>
                            <a class="inline-flex items-center gap-2 text-primary font-bold hover:underline" href="${articleUrl(featured)}">
                                Lire la suite <span class="material-symbols-outlined">arrow_forward</span>
                            </a>
                        </div>
                    </article>`;
            } else if (featuredMount) {
                featuredMount.innerHTML = '';
            }

            gridMount.innerHTML = pageItems.length ? pageItems.map(a => `
                <article class="bg-white border border-outline-variant rounded-xl overflow-hidden shadow-sm hover:shadow-md transition-shadow">
                    <div class="h-48 overflow-hidden">
                        <img alt="${escapeHtml(a.titre)}" class="w-full h-full object-cover" src="${escapeHtml(a.image || 'images/CAMA_1.jfif')}"/>
                    </div>
                    <div class="p-6">
                        <span class="text-on-surface-variant font-label-md text-label-md mb-2 block">${escapeHtml(a.date)}</span>
                        <h3 class="font-title-lg text-title-lg mb-3 text-on-surface">${escapeHtml(a.titre)}</h3>
                        <p class="font-body-md text-body-md text-on-surface-variant mb-6 line-clamp-2">${escapeHtml(a.resume)}</p>
                        <a class="text-primary font-bold flex items-center gap-2 group" href="${articleUrl(a)}">
                            Lire l'article <span class="material-symbols-outlined group-hover:translate-x-1 transition-transform">arrow_forward</span>
                        </a>
                    </div>
                </article>`).join('') : '<p class="md:col-span-2 lg:col-span-3 text-center text-on-surface-variant py-12">Aucun article ne correspond à votre recherche.</p>';

            if (paginationMount) {
                if (totalPages <= 1) {
                    paginationMount.innerHTML = '';
                    return;
                }
                let pages = '';
                for (let p = 1; p <= totalPages; p++) {
                    pages += `<button type="button" data-page="${p}" class="news-page w-10 h-10 flex items-center justify-center rounded-lg font-bold ${p === currentPage ? 'bg-primary text-on-primary' : 'border border-outline text-on-surface-variant hover:bg-surface-container-high transition-colors'}">${p}</button>`;
                }
                paginationMount.innerHTML = `
                    <button type="button" data-page="prev" class="news-page w-10 h-10 flex items-center justify-center rounded-lg border border-outline text-on-surface-variant hover:bg-surface-container-high transition-colors" ${currentPage === 1 ? 'disabled' : ''}>
                        <span class="material-symbols-outlined">chevron_left</span>
                    </button>
                    ${pages}
                    <button type="button" data-page="next" class="news-page w-10 h-10 flex items-center justify-center rounded-lg border border-outline text-on-surface-variant hover:bg-surface-container-high transition-colors" ${currentPage === totalPages ? 'disabled' : ''}>
                        <span class="material-symbols-outlined">chevron_right</span>
                    </button>`;
                paginationMount.querySelectorAll('.news-page').forEach(btn => {
                    btn.addEventListener('click', () => {
                        const p = btn.dataset.page;
                        if (p === 'prev') currentPage = Math.max(1, currentPage - 1);
                        else if (p === 'next') currentPage = Math.min(totalPages, currentPage + 1);
                        else currentPage = parseInt(p, 10);
                        render();
                        document.getElementById('news-grid')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    });
                });
            }
        }

        render();
    }

    function initArticlePage() {
        const mount = document.getElementById('article-content');
        if (!mount) return;

        const params = new URLSearchParams(window.location.search);
        const article = getArticle(params.get('id'));
        if (!article || article.statut !== 'Publié') {
            mount.innerHTML = `
                <div class="text-center py-20">
                    <h1 class="font-headline-md text-xl mb-4">Article introuvable</h1>
                    <a class="text-primary font-bold hover:underline" href="actualite.html">Retour aux actualités</a>
                </div>`;
            document.title = 'Article introuvable | CAMA';
            return;
        }

        document.title = `${article.titre} | CAMA`;
        mount.innerHTML = `
            <article class="max-w-3xl mx-auto">
                <a class="inline-flex items-center gap-1 text-sm font-semibold text-primary mb-6 hover:underline" href="actualite.html">
                    <span class="material-symbols-outlined text-[18px]">arrow_back</span> Toutes les actualités
                </a>
                <div class="flex flex-wrap items-center gap-3 mb-4">
                    <span class="bg-primary text-on-primary px-3 py-1 rounded text-caption font-bold uppercase tracking-wider">${escapeHtml(article.categorie)}</span>
                    <span class="text-on-surface-variant text-sm">${escapeHtml(article.date)}</span>
                    ${article.auteur ? `<span class="text-on-surface-variant text-sm">· ${escapeHtml(article.auteur)}</span>` : ''}
                </div>
                <h1 class="font-headline-lg text-headline-lg text-on-surface mb-6">${escapeHtml(article.titre)}</h1>
                ${article.image ? `<div class="rounded-xl overflow-hidden mb-8 border border-outline-variant"><img alt="" class="w-full max-h-[420px] object-cover" src="${escapeHtml(article.image)}"/></div>` : ''}
                ${article.resume ? `<p class="font-body-lg text-body-lg text-on-surface-variant mb-8 border-l-4 border-primary pl-4">${escapeHtml(article.resume)}</p>` : ''}
                <div class="prose-article font-body-md text-body-md text-on-surface space-y-4 leading-relaxed">${article.contenu || `<p>${escapeHtml(article.resume)}</p>`}</div>
                <div class="mt-10 pt-8 border-t border-outline-variant flex flex-wrap gap-3">
                    <span class="text-sm text-on-surface-variant mr-2">Partager :</span>
                    <a class="text-sm font-semibold text-primary hover:underline" href="https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(window.location.href)}" rel="noopener" target="_blank">Facebook</a>
                    <a class="text-sm font-semibold text-primary hover:underline" href="https://twitter.com/intent/tweet?url=${encodeURIComponent(window.location.href)}&text=${encodeURIComponent(article.titre)}" rel="noopener" target="_blank">X</a>
                    <a class="text-sm font-semibold text-primary hover:underline" href="mailto:?subject=${encodeURIComponent(article.titre)}&body=${encodeURIComponent(window.location.href)}">E-mail</a>
                </div>
            </article>`;
    }

    function initServicesFaq() {
        const mount = document.getElementById('faq-accordion');
        if (!mount) return;
        const items = getPublishedFaq();
        if (!items.length) {
            mount.innerHTML = '<p class="text-on-surface-variant text-sm text-center py-8">Aucune question publiée pour le moment.</p>';
            return;
        }
        mount.innerHTML = items.map((f, i) => {
            const meta = FAQ_ICONS[f.categorie] || { icon: 'help', color: 'primary' };
            const bgClass = meta.color === 'on-background' ? 'bg-surface-container-high' : `${meta.color}/10`;
            return `
                <details class="group bg-white rounded-xl border border-outline-variant overflow-hidden hover:border-primary/30 transition-colors"${i === 0 ? ' open' : ''}>
                    <summary class="flex justify-between items-center gap-4 p-6 cursor-pointer hover:bg-surface-container-low transition-colors">
                        <span class="flex items-center gap-4">
                            <span class="material-symbols-outlined text-${meta.color} bg-${bgClass} p-2 rounded-lg shrink-0">${meta.icon}</span>
                            <span class="font-title-lg text-title-lg">${escapeHtml(f.question)}</span>
                        </span>
                        <span class="material-symbols-outlined text-on-surface-variant group-open:rotate-180 transition-transform shrink-0">expand_more</span>
                    </summary>
                    <div class="px-6 pb-6 pl-[4.5rem] text-on-surface-variant font-body-md border-t border-outline-variant pt-4">${escapeHtml(f.reponse)}</div>
                </details>`;
        }).join('');
    }

    function initHome() {
        initHomeHero();
        initHomeChiffres();
        initHomeNews();
    }

    global.CamaSiteContent = {
        SLIDES_KEY,
        CHIFFRES_KEY,
        ARTICLES_KEY,
        FAQ_KEY,
        DEFAULT_ARTICLES,
        DEFAULT_FAQ,
        getSlides,
        getChiffres,
        getArticles,
        saveArticles,
        getFaq,
        saveFaq,
        getPublishedFaq,
        getPublishedArticles,
        getArticle,
        articleUrl,
        initHome,
        initServicesFaq,
        initActualitesList,
        initArticlePage
    };
})(window);
