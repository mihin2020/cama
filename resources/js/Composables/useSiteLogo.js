import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

export const DEFAULT_SITE_LOGO = '/images/logo_cama.png';

// Logo du site, modifiable dans CMS > Identité & pied de page (prop partagée « branding »).
export function useSiteLogo() {
    const page = usePage();

    return computed(() => page.props.branding?.logoUrl || DEFAULT_SITE_LOGO);
}
