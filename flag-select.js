/**
 * Améliore un <select> de pays en un menu déroulant affichant les vrais drapeaux
 * (images flagcdn). Le <select> natif est conservé (caché) comme porteur de valeur,
 * de l'attribut required et du data-field : la validation et la collecte existantes
 * continuent de fonctionner sans changement.
 *
 * Dépend de window.CamaCountries (countries.js).
 */
(function (global) {
    'use strict';

    const flagUrl = code => `https://flagcdn.com/24x18/${code.toLowerCase()}.png`;
    const flagUrl2x = code => `https://flagcdn.com/48x36/${code.toLowerCase()}.png`;

    function nameToCode() {
        const m = {};
        (global.CamaCountries || []).forEach(c => { m[c.name] = c.code; });
        return m;
    }

    function flagImg(code) {
        if (!code) return '<span class="material-symbols-outlined text-[18px] text-on-surface-variant shrink-0">flag</span>';
        return `<img src="${flagUrl(code)}" srcset="${flagUrl2x(code)} 2x" width="24" height="18" alt="" loading="lazy" class="rounded-sm shrink-0 border border-black/5"/>`;
    }

    function enhance(sel) {
        if (!sel || sel.dataset.flagEnhanced) return;
        sel.dataset.flagEnhanced = '1';
        const map = nameToCode();

        const baseClass = sel.className.replace(/\bhidden\b/g, '').trim();
        sel.classList.add('hidden');

        const wrap = document.createElement('div');
        wrap.className = 'relative';
        sel.parentNode.insertBefore(wrap, sel.nextSibling);

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = baseClass + ' flex items-center justify-between gap-2 text-left bg-white';
        wrap.appendChild(btn);

        const panel = document.createElement('div');
        panel.className = 'hidden absolute z-[60] mt-1 w-full bg-white border border-outline-variant rounded-lg shadow-xl overflow-hidden';
        panel.innerHTML = '<div class="p-2 border-b border-outline-variant">'
            + '<input type="text" class="flag-search w-full px-3 py-2 text-sm border border-outline-variant rounded-md outline-none focus:ring-2 focus:ring-primary" placeholder="Rechercher un pays…"/></div>'
            + '<div class="flag-list max-h-60 overflow-y-auto py-1"></div>';
        wrap.appendChild(panel);

        const listEl = panel.querySelector('.flag-list');
        const searchEl = panel.querySelector('.flag-search');

        function placeholderLabel() {
            const ph = Array.from(sel.options).find(o => o.value === '');
            return ph ? (ph.textContent.replace(/[—–-]+/g, ' ').trim() || 'Sélectionner un pays') : 'Sélectionner un pays';
        }

        function renderButton() {
            const val = sel.value;
            const chevron = '<span class="material-symbols-outlined text-[20px] text-on-surface-variant shrink-0">expand_more</span>';
            if (!val) {
                btn.innerHTML = `<span class="text-on-surface-variant truncate">${placeholderLabel()}</span>${chevron}`;
            } else {
                btn.innerHTML = `<span class="flex items-center gap-2 min-w-0">${flagImg(map[val])} <span class="truncate">${val}</span></span>${chevron}`;
            }
        }

        function renderList(q) {
            q = (q || '').trim().toLowerCase();
            const items = Array.from(sel.options).filter(o => o.value !== '' && o.value.toLowerCase().includes(q));
            listEl.innerHTML = items.map(o => {
                const active = o.value === sel.value;
                return `<button type="button" data-val="${o.value.replace(/"/g, '&quot;')}" class="w-full flex items-center gap-2.5 px-3 py-2 text-sm text-left hover:bg-surface-container-low ${active ? 'bg-primary/5 font-semibold' : ''}">${flagImg(map[o.value])} <span class="truncate">${o.value}</span></button>`;
            }).join('') || '<p class="px-3 py-3 text-xs text-on-surface-variant text-center">Aucun pays trouvé.</p>';
            listEl.querySelectorAll('[data-val]').forEach(b => b.addEventListener('click', () => {
                sel.value = b.dataset.val;
                sel.dispatchEvent(new Event('change', { bubbles: true }));
                close();
            }));
        }

        function open() { panel.classList.remove('hidden'); searchEl.value = ''; renderList(''); setTimeout(() => searchEl.focus(), 10); }
        function close() { panel.classList.add('hidden'); }

        btn.addEventListener('click', e => { e.stopPropagation(); panel.classList.contains('hidden') ? open() : close(); });
        searchEl.addEventListener('input', () => renderList(searchEl.value));
        searchEl.addEventListener('click', e => e.stopPropagation());
        document.addEventListener('click', e => { if (!wrap.contains(e.target)) close(); });
        sel.addEventListener('change', renderButton);

        // Refléter l'état d'erreur (.is-invalid posé par la validation) sur le bouton.
        const obs = new MutationObserver(() => {
            const bad = sel.classList.contains('is-invalid');
            btn.style.borderColor = bad ? '#ba1a1a' : '';
            if (bad) { btn.scrollIntoView({ block: 'center', behavior: 'smooth' }); }
        });
        obs.observe(sel, { attributes: true, attributeFilter: ['class'] });

        renderButton();
    }

    function enhanceAll(selector, root) {
        (root || document).querySelectorAll(selector).forEach(enhance);
    }

    global.CamaFlagSelect = { enhance, enhanceAll, flagImg };
})(window);
