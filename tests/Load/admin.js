// Parcours d'un gestionnaire : connexion, liste des dossiers, assurés, inscriptions.
// k6 run tests/Load/admin.js   (20 comptes gestionnaires : charge-gest01@charge.test … 20)
import { sleep } from 'k6';
import { login, visit } from './common.js';
export { options } from './common.js';

export default function () {
    const n = 1 + Math.floor(Math.random() * 20);
    const email = `charge-gest${String(n).padStart(2, '0')}@charge.test`;

    if (!login('/admin/connexion', email, 'Charge2026!')) return;
    sleep(1);
    visit('/admin/dossiers', 'admin-dossiers');
    sleep(3);
    visit('/admin/assures', 'admin-assures');
    sleep(3);
    visit('/admin/inscriptions', 'admin-inscriptions');
    sleep(2);
}
