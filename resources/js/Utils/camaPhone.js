import { CAMA_COUNTRIES } from '@/Data/camaCountries';
import { CAMA_DIAL_CODES } from '@/Data/camaDialCodes';

export function getDialList() {
    const list = CAMA_COUNTRIES.map((c) => ({
        code: c.code,
        name: c.name,
        dial: CAMA_DIAL_CODES[c.code] || '',
    })).filter((c) => c.dial);

    list.sort((a, b) => a.name.localeCompare(b.name, 'fr'));
    const bf = list.findIndex((c) => c.code === 'BF');
    if (bf > 0) {
        const [item] = list.splice(bf, 1);
        list.unshift(item);
    }

    return list;
}

export function findDial(countryCode) {
    const list = getDialList();
    return list.find((d) => d.code === countryCode) || list[0] || { code: 'BF', dial: '+226', name: 'Burkina Faso' };
}

export function parsePhoneString(raw) {
    if (!raw) return [];
    if (Array.isArray(raw)) return raw.map((p) => (typeof p === 'string' ? parseOne(p) : p));
    return String(raw).split('|').map((s) => s.trim()).filter(Boolean).map(parseOne);
}

export function parseOne(value) {
    const list = getDialList();
    const match = String(value).match(/^(\+\d{1,4})\s*(.*)$/);
    if (match) {
        const dial = match[1];
        const entry = list.find((d) => d.dial === dial) || list[0];
        return { country: entry.code, dial, number: match[2].trim() };
    }
    return { country: 'BF', dial: '+226', number: String(value).trim() };
}

export function formatPhones(rows) {
    return rows
        .filter((r) => r.number && r.number.trim())
        .map((r) => `${r.dial} ${r.number.trim()}`)
        .join('|');
}

export function formatPhonesDisplay(raw) {
    if (!raw) return '—';
    return String(raw).split('|').map((s) => s.trim()).filter(Boolean).join(' · ');
}

export function createPhoneRow(defaultCountry = 'BF') {
    const d = findDial(defaultCountry);
    return { country: d.code, dial: d.dial, number: '' };
}
