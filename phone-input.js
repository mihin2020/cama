/**
 * Champs téléphone avec drapeau, indicatif pays (tous les pays) et lignes multiples.
 * Dépend de window.CamaCountries (countries.js) et window.CamaDialCodes (dial-codes.js).
 */
(function (global) {
    'use strict';

    const flagUrl = code => `https://flagcdn.com/24x18/${(code || 'bf').toLowerCase()}.png`;
    const flagUrl2x = code => `https://flagcdn.com/48x36/${(code || 'bf').toLowerCase()}.png`;

    function getDialList() {
        const dialMap = global.CamaDialCodes || {};
        const list = (global.CamaCountries || []).map(c => ({
            code: c.code,
            name: c.name,
            dial: dialMap[c.code] || ''
        })).filter(c => c.dial);
        list.sort((a, b) => a.name.localeCompare(b.name, 'fr'));
        const bf = list.findIndex(c => c.code === 'BF');
        if (bf > 0) { const [item] = list.splice(bf, 1); list.unshift(item); }
        return list;
    }

    function findDial(countryCode) {
        const list = getDialList();
        return list.find(d => d.code === countryCode) || list[0] || { code: 'BF', dial: '+226', name: 'Burkina Faso' };
    }

    function parsePhoneString(raw) {
        if (!raw) return [];
        if (Array.isArray(raw)) return raw.map(p => typeof p === 'string' ? parseOne(p) : p);
        return String(raw).split('|').map(s => s.trim()).filter(Boolean).map(parseOne);
    }

    function parseOne(s) {
        const list = getDialList();
        const m = String(s).match(/^(\+\d{1,4})\s*(.*)$/);
        if (m) {
            const dial = m[1];
            const entry = list.find(d => d.dial === dial) || list[0];
            return { country: entry.code, dial, number: m[2].trim() };
        }
        return { country: 'BF', dial: '+226', number: String(s).trim() };
    }

    function formatPhones(rows) {
        return rows
            .filter(r => r.number && r.number.trim())
            .map(r => `${r.dial} ${r.number.trim()}`)
            .join('|');
    }

    function escapeAttr(s) {
        return String(s || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
    }

    function mount(container, options) {
        const opts = Object.assign({ max: 2, required: false, defaultCountry: 'BF', values: [] }, options);
        const rows = parsePhoneString(opts.values);
        while (rows.length < 1) {
            const d = findDial(opts.defaultCountry);
            rows.push({ country: d.code, dial: d.dial, number: '' });
        }
        const dialList = getDialList();
        const inputCls = opts.inputClass || 'flex-1 px-3 py-2 text-sm border border-outline-variant rounded-lg focus:ring-2 focus:ring-primary focus:border-primary outline-none';

        function countryPickerHtml(row, idx) {
            const cur = dialList.find(d => d.code === row.country) || findDial(row.country);
            return `<div class="phone-country-wrap relative shrink-0" data-idx="${idx}">
                <button type="button" class="phone-country-btn flex items-center gap-1.5 px-2 py-2 text-xs border border-outline-variant rounded-lg bg-white min-w-[130px] max-w-[160px]" data-idx="${idx}">
                    <img src="${flagUrl(cur.code)}" srcset="${flagUrl2x(cur.code)} 2x" width="24" height="18" alt="" class="rounded-sm border border-black/5 shrink-0"/>
                    <span class="font-bold text-on-surface shrink-0">${cur.dial}</span>
                    <span class="material-symbols-outlined text-[18px] text-on-surface-variant ml-auto">expand_more</span>
                </button>
                <div class="phone-country-panel hidden absolute z-[70] mt-1 left-0 w-64 bg-white border border-outline-variant rounded-lg shadow-xl overflow-hidden">
                    <div class="p-2 border-b border-outline-variant">
                        <input type="text" class="phone-country-search w-full px-2 py-1.5 text-xs border border-outline-variant rounded outline-none focus:ring-2 focus:ring-primary" placeholder="Rechercher un pays…"/>
                    </div>
                    <div class="phone-country-list max-h-52 overflow-y-auto py-1"></div>
                </div>
            </div>`;
        }

        function renderList(panel, q, idx) {
            q = (q || '').trim().toLowerCase();
            const items = dialList.filter(c =>
                !q || c.name.toLowerCase().includes(q) || c.dial.includes(q) || c.code.toLowerCase().includes(q)
            );
            const listEl = panel.querySelector('.phone-country-list');
            listEl.innerHTML = items.map(c => {
                const active = c.code === rows[idx].country;
                return `<button type="button" data-code="${c.code}" class="w-full flex items-center gap-2 px-3 py-2 text-xs text-left hover:bg-surface-container-low ${active ? 'bg-primary/5 font-semibold' : ''}">
                    <img src="${flagUrl(c.code)}" width="24" height="18" alt="" class="rounded-sm border border-black/5 shrink-0"/>
                    <span class="truncate flex-1">${c.name}</span>
                    <span class="font-bold text-on-surface-variant shrink-0">${c.dial}</span>
                </button>`;
            }).join('') || '<p class="px-3 py-2 text-xs text-center text-on-surface-variant">Aucun pays</p>';
        }

        function bindCountryPickers() {
            container.querySelectorAll('.phone-country-wrap').forEach(wrap => {
                const idx = parseInt(wrap.dataset.idx, 10);
                const btn = wrap.querySelector('.phone-country-btn');
                const panel = wrap.querySelector('.phone-country-panel');
                const search = wrap.querySelector('.phone-country-search');
                const listEl = wrap.querySelector('.phone-country-list');

                function closeAll() {
                    container.querySelectorAll('.phone-country-panel').forEach(p => p.classList.add('hidden'));
                }
                function open() {
                    closeAll();
                    panel.classList.remove('hidden');
                    search.value = '';
                    renderList(panel, '', idx);
                    setTimeout(() => search.focus(), 10);
                }

                btn.addEventListener('click', e => { e.stopPropagation(); panel.classList.contains('hidden') ? open() : closeAll(); });
                search.addEventListener('input', () => renderList(panel, search.value, idx));
                search.addEventListener('click', e => e.stopPropagation());
                listEl.addEventListener('click', e => {
                    const b = e.target.closest('[data-code]');
                    if (!b) return;
                    const c = findDial(b.dataset.code);
                    rows[idx].country = c.code;
                    rows[idx].dial = c.dial;
                    closeAll();
                    render();
                });
            });
            if (!container._phoneDocClick) {
                container._phoneDocClick = true;
                document.addEventListener('click', e => {
                    if (!container.contains(e.target)) {
                        container.querySelectorAll('.phone-country-panel').forEach(p => p.classList.add('hidden'));
                    }
                });
            }
        }

        function rowHtml(row, i) {
            return `<div class="flex items-center gap-2 phone-row">
                ${countryPickerHtml(row, i)}
                <input class="phone-number ${inputCls}" data-idx="${i}" type="tel" placeholder="70 12 34 56" value="${escapeAttr(row.number)}" ${opts.required && i === 0 ? 'required' : ''}/>
                ${rows.length > 1 || i > 0 ? `<button type="button" class="phone-remove text-error shrink-0" data-idx="${i}" title="Retirer"><span class="material-symbols-outlined text-[18px]">close</span></button>` : ''}
            </div>`;
        }

        function render() {
            container.innerHTML = `
                <div class="phone-input-group space-y-2" data-max="${opts.max}">
                    ${rows.map((row, i) => rowHtml(row, i)).join('')}
                </div>
                ${rows.length < opts.max ? `<button type="button" class="phone-add-btn mt-2 text-primary text-xs font-bold flex items-center gap-1 hover:underline"><span class="material-symbols-outlined text-[16px]">add</span> Ajouter un numéro</button>` : ''}`;

            container.querySelectorAll('.phone-number').forEach(inp => {
                inp.addEventListener('input', e => {
                    rows[parseInt(e.target.dataset.idx, 10)].number = e.target.value;
                });
            });
            container.querySelectorAll('.phone-remove').forEach(btn => {
                btn.addEventListener('click', () => {
                    const idx = parseInt(btn.dataset.idx, 10);
                    if (rows.length <= 1) { rows[0].number = ''; render(); return; }
                    rows.splice(idx, 1);
                    render();
                });
            });
            const addBtn = container.querySelector('.phone-add-btn');
            if (addBtn) addBtn.addEventListener('click', () => {
                if (rows.length >= opts.max) return;
                const d = findDial(opts.defaultCountry);
                rows.push({ country: d.code, dial: d.dial, number: '' });
                render();
            });
            bindCountryPickers();
        }

        render();

        return {
            getValues() { return formatPhones(rows); },
            getPrimary() {
                const v = formatPhones(rows);
                return v.split('|')[0] || '';
            },
            isValid() {
                if (!opts.required) return true;
                return rows.some(r => r.number && r.number.trim());
            }
        };
    }

    global.CamaPhoneInput = { mount, parsePhoneString, formatPhones, getDialList };
})(window);
