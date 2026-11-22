// Parcours d'un assuré : connexion, tableau de bord, ma famille, notifications, profil.
// Comptes créés par LoadTestSeeder (assure0001@charge.test …). k6 run tests/Load/assure.js
import { sleep } from 'k6';
import { login, visit } from './common.js';
export { options } from './common.js';

const ASSURES = Number(__ENV.ASSURES || 50000);

export default function () {
    const n = 1 + Math.floor(Math.random() * Math.min(ASSURES, 9999));
    const email = `assure${String(n).padStart(4, '0')}@charge.test`;

    if (!login('/espace-assure/connexion', email, 'Charge2026!')) return;
    sleep(1);
    visit('/espace-assure/tableau-de-bord', 'assure-tableau-de-bord');
    sleep(2);
    visit('/espace-assure/ma-famille', 'assure-ma-famille');
    sleep(2);
    visit('/espace-assure/notifications', 'assure-notifications');
    sleep(1);
    visit('/espace-assure/profil', 'assure-profil');
    sleep(2);
}
