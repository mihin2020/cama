import http from 'k6/http';
import { check } from 'k6';

export const BASE = __ENV.BASE_URL || 'http://localhost:8080';

// Paliers de montée en charge (utilisateurs virtuels simultanés) ; VUS_MAX pour changer le plafond.
const max = Number(__ENV.VUS_MAX || 200);
export const options = {
    stages: [
        { duration: '1m', target: Math.min(10, max) },
        { duration: '2m', target: Math.min(50, max) },
        { duration: '2m', target: Math.min(100, max) },
        { duration: '3m', target: max },
        { duration: '1m', target: 0 },
    ],
    thresholds: {
        http_req_failed: ['rate<0.01'],
        http_req_duration: ['p(95)<1500', 'p(99)<3000'],
    },
};

/** Connexion Inertia (jeton CSRF lu dans le cookie XSRF-TOKEN) ; retourne true si la session est ouverte. */
export function login(path, email, password) {
    http.get(`${BASE}${path}`);
    const jar = http.cookieJar();
    const xsrf = (jar.cookiesForURL(BASE)['XSRF-TOKEN'] || [''])[0];

    const res = http.post(`${BASE}${path}`, JSON.stringify({ email, password }), {
        headers: {
            'Content-Type': 'application/json',
            Accept: 'text/html, application/xhtml+xml',
            'X-Inertia': 'true',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': decodeURIComponent(xsrf),
        },
    });

    return check(res, { 'connexion réussie': (r) => r.status === 200 || r.status === 302 || r.status === 409 });
}

export function visit(path, name) {
    const res = http.get(`${BASE}${path}`, { tags: { name } });
    check(res, { [`${name} : 200`]: (r) => r.status === 200 });

    return res;
}
