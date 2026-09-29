/**
 * Ancre « Retour en haut » + navigation fluide vers les sections (#hash)
 * Usage : CamaAnchorTop.init() — chargé automatiquement par les shells CAMA
 */
(function () {
    'use strict';

    const TOP_ID = 'top';
    const BTN_ID = 'cama-anchor-top';
    const SHOW_AFTER = 280;

    function getScrollOffset() {
        const header = document.getElementById('site-header') || document.getElementById('app-header');
        if (header) return Math.ceil(header.getBoundingClientRect().height) + 8;
        const cssVar = getComputedStyle(document.documentElement).getPropertyValue('--site-header-height').trim();
        const parsed = parseInt(cssVar, 10);
        return Number.isFinite(parsed) && parsed > 0 ? parsed + 8 : 80;
    }

    function ensureTopAnchor() {
        if (document.getElementById(TOP_ID)) return;
        const anchor = document.createElement('a');
        anchor.id = TOP_ID;
        anchor.href = `#${TOP_ID}`;
        anchor.className = 'cama-top-anchor';
        anchor.setAttribute('tabindex', '-1');
        anchor.setAttribute('aria-hidden', 'true');
        document.body.insertBefore(anchor, document.body.firstChild);
    }

    function createButton() {
        if (document.getElementById(BTN_ID)) return;
        const btn = document.createElement('a');
        btn.id = BTN_ID;
        btn.href = `#${TOP_ID}`;
        btn.className = 'cama-anchor-top-btn';
        btn.setAttribute('aria-label', 'Retour en haut de la page');
        btn.innerHTML = '<span class="material-symbols-outlined" aria-hidden="true">keyboard_arrow_up</span>';
        document.body.appendChild(btn);
    }

    function scrollToTop() {
        window.scrollTo({ top: 0, behavior: 'smooth' });
        const top = document.getElementById(TOP_ID);
        if (top) top.focus({ preventScroll: true });
    }

    function scrollToTarget(target) {
        const y = target.getBoundingClientRect().top + window.pageYOffset - getScrollOffset();
        window.scrollTo({ top: Math.max(0, y), behavior: 'smooth' });
        target.setAttribute('tabindex', '-1');
        target.focus({ preventScroll: true });
    }

    function bindHashLinks() {
        document.addEventListener('click', (e) => {
            const link = e.target.closest('a[href^="#"]');
            if (!link) return;

            const hash = link.getAttribute('href');
            if (!hash || hash === '#') return;

            if (hash === `#${TOP_ID}`) {
                e.preventDefault();
                scrollToTop();
                return;
            }

            const target = document.querySelector(hash);
            if (!target) return;

            e.preventDefault();
            if (history.pushState) {
                history.pushState(null, '', hash);
            } else {
                location.hash = hash;
            }
            scrollToTarget(target);
        });
    }

    function updateButtonVisibility() {
        const btn = document.getElementById(BTN_ID);
        if (!btn) return;
        btn.classList.toggle('is-visible', window.scrollY > SHOW_AFTER);
    }

    function updateBottomOffset() {
        let bottom = window.innerWidth < 768 ? 20 : 24;

        const cookieBar = document.getElementById('site-cookie-bar');
        if (cookieBar && !cookieBar.classList.contains('is-hidden') && cookieBar.classList.contains('is-visible')) {
            bottom += cookieBar.offsetHeight || 0;
        }

        if (window.innerWidth < 768) {
            const bottomNav = document.querySelector('nav.fixed.bottom-0, nav[class*="fixed"][class*="bottom-0"]');
            if (bottomNav) bottom += bottomNav.offsetHeight || 64;
        }

        document.documentElement.style.setProperty('--cama-anchor-bottom', `${bottom}px`);
    }

    function initScrollPadding() {
        const apply = () => {
            document.documentElement.style.scrollPaddingTop = `${getScrollOffset()}px`;
        };
        apply();
        window.addEventListener('resize', apply);
        const header = document.getElementById('site-header') || document.getElementById('app-header');
        if (header && typeof ResizeObserver !== 'undefined') {
            new ResizeObserver(apply).observe(header);
        }
    }

    function handleInitialHash() {
        if (!location.hash || location.hash === `#${TOP_ID}`) return;
        const target = document.querySelector(location.hash);
        if (!target) return;
        setTimeout(() => scrollToTarget(target), 150);
    }

    function init() {
        ensureTopAnchor();
        createButton();
        bindHashLinks();
        initScrollPadding();
        updateBottomOffset();
        updateButtonVisibility();
        handleInitialHash();

        window.addEventListener('scroll', updateButtonVisibility, { passive: true });
        window.addEventListener('resize', updateBottomOffset);

        const cookieBar = document.getElementById('site-cookie-bar');
        if (cookieBar && typeof MutationObserver !== 'undefined') {
            new MutationObserver(updateBottomOffset).observe(cookieBar, {
                attributes: true,
                attributeFilter: ['class']
            });
        }
    }

    window.CamaAnchorTop = { init, scrollToTop, scrollToTarget };
})();
