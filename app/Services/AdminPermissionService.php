<?php

namespace App\Services;

use App\Models\AdminUser;
use App\Support\AdminPermissions;

class AdminPermissionService
{
    public function resolvedPermissions(AdminUser $admin): array
    {
        return $admin->effective_permissions;
    }

    /**
     * Toutes les permissions dont le compte dispose réellement, raccourcis développés
     * (« Accès complet au studio » → chaque permission du studio). Transmis tel quel à l'interface :
     * une seule source de vérité.
     *
     * @return list<string>
     */
    public function expanded(AdminUser $admin): array
    {
        return array_values(array_filter(AdminPermissions::allKeys(), fn (string $key) => $this->allows($admin, $key)));
    }

    public function allows(?AdminUser $admin, string $permission): bool
    {
        if (! $admin) {
            return false;
        }

        $permissions = $this->resolvedPermissions($admin);

        if (in_array($permission, $permissions, true)) {
            return true;
        }

        if (AdminPermissions::coveredByCmsManage($permission) && in_array('cms.manage', $permissions, true)) {
            return true;
        }

        return false;
    }

    public function authorize(?AdminUser $admin, string $permission): void
    {
        if (! $this->allows($admin, $permission)) {
            abort(403, 'Action non autorisée pour vos permissions.');
        }
    }

    public function authorizeRoute(?AdminUser $admin, ?string $routeName): void
    {
        $permission = AdminPermissions::permissionForRoute($routeName);

        if ($permission === null) {
            return;
        }

        $this->authorize($admin, $permission);
    }
}
