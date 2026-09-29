import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { roleCanSee } from '@/Composables/useShellSidebar';

export function useAdminPermissions() {
    const page = usePage();
    const permissions = computed(() => page.props.auth?.admin?.permissions ?? []);
    const role = computed(() => page.props.auth?.admin?.role ?? 'gestionnaire');

    function can(permission) {
        const perms = permissions.value;
        if (perms.includes(permission)) {
            return true;
        }
        if (permission.startsWith('cms.') && perms.includes('cms.manage')) {
            return true;
        }
        return false;
    }

    function canAny(list) {
        return list.some((permission) => can(permission));
    }

    function canSee(roles) {
        return roleCanSee(role.value, roles);
    }

    function canAccessCms() {
        return can('cms.manage') || canAny([
            'cms.dashboard',
            'cms.pages',
            'cms.media',
            'cms.articles',
            'cms.faq',
            'cms.banniere',
            'cms.chiffres',
            'cms.ressources',
            'cms.partenaires',
            'cms.menus',
            'cms.footer',
            'cms.contacts',
            'cms.newsletter',
        ]);
    }

    return {
        permissions,
        role,
        can,
        canAny,
        canSee,
        canAccessCms,
    };
}
