import { router } from '@inertiajs/vue3';
import { computed, nextTick, ref, unref, watch } from 'vue';

/** Ramène le haut de la liste à l'écran après un changement de page (sinon on reste en bas, sur la pagination). */
function scrollToList(anchor) {
    const target = unref(anchor);
    // Référence posée dans une boucle (v-for) : Vue fournit un tableau d'éléments.
    const element = Array.isArray(target) ? target.find(Boolean) : target;
    if (!element?.getBoundingClientRect) return;
    if (element.getBoundingClientRect().top < 0) {
        element.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

/**
 * Liste paginée par le serveur : la page et la taille sont dans l'adresse (?page=&per_page=),
 * les autres filtres sont fournis par `params()`. Seules les données listées sont rechargées.
 *
 * @param {() => object} pagination  données de pagination reçues du serveur (Pagination::meta)
 * @param {{ routeName: string, params?: () => object, only?: string[], anchor?: import('vue').Ref }} options
 */
export function useServerPagination(pagination, { routeName, params = () => ({}), only, anchor } = {}) {
    function visit(extra = {}) {
        const meta = pagination();
        const defaultSize = meta?.perPageOptions?.[0];
        const query = {
            ...params(),
            per_page: meta?.perPage && meta.perPage !== defaultSize ? meta.perPage : undefined,
            ...extra,
        };
        Object.keys(query).forEach((key) => (query[key] === undefined || query[key] === null || query[key] === '') && delete query[key]);

        router.get(route(routeName), query, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            ...(only ? { only } : {}),
            onSuccess: () => nextTick(() => scrollToList(anchor)),
        });
    }

    return {
        /** Recharge avec les filtres courants, en revenant à la première page. */
        reload: (extra = {}) => visit({ page: undefined, ...extra }),
        goToPage: (page) => visit({ page: page > 1 ? page : undefined }),
        setPerPage: (size) => visit({ page: undefined, per_page: size === pagination()?.perPageOptions?.[0] ? undefined : size }),
    };
}

/**
 * Liste déjà chargée, découpée en pages dans le navigateur (listes courtes et bornées :
 * historique d'un assuré, familles filtrées…).
 *
 * `resetOn` : filtres dont le changement ramène à la page 1. Un simple rechargement des
 * données (après une action) garde la page courante, ramenée à la dernière si elle n'existe plus.
 *
 * @param {() => any[]} items
 * @param {{ perPageOptions?: number[], anchor?: import('vue').Ref, resetOn?: Array|Function }} options
 */
export function useClientPagination(items, { perPageOptions = [10, 25, 50], anchor, resetOn } = {}) {
    const currentPage = ref(1);
    const perPage = ref(perPageOptions[0]);

    const total = computed(() => (items() ?? []).length);
    const lastPage = computed(() => Math.max(1, Math.ceil(total.value / perPage.value)));
    const paged = computed(() => (items() ?? []).slice((currentPage.value - 1) * perPage.value, currentPage.value * perPage.value));
    const pagination = computed(() => ({
        currentPage: currentPage.value,
        lastPage: lastPage.value,
        perPage: perPage.value,
        total: total.value,
        perPageOptions,
    }));

    // Filtre ou recherche modifiés : retour au début de la liste.
    if (resetOn) {
        watch(resetOn, () => {
            currentPage.value = 1;
        });
    }
    watch(lastPage, (last) => {
        if (currentPage.value > last) currentPage.value = last;
    });

    function goToPage(page) {
        currentPage.value = Math.min(Math.max(1, page), lastPage.value);
        nextTick(() => scrollToList(anchor));
    }

    function setPerPage(size) {
        perPage.value = size;
        currentPage.value = 1;
    }

    return { pagination, paged, goToPage, setPerPage };
}
