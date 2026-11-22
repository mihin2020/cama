// Pages publiques (visiteurs anonymes). k6 run tests/Load/public.js
import { sleep } from 'k6';
import { visit } from './common.js';
export { options } from './common.js';

export default function () {
    visit('/', 'accueil');
    sleep(1);
    visit('/actualites', 'actualites');
    sleep(1);
    visit('/partenaires', 'partenaires');
    sleep(1);
    visit('/contact', 'contact');
    sleep(2);
}
