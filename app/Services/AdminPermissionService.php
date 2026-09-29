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

    public function allows(?AdminUser $admin, string $permission): bool
    {
        if (! $admin) {
            return false;
        }

        $permissions = $this->resolvedPermissions($admin);

        if (in_array($permission, $permissions, true)) {
            return true;
        }

        if (str_starts_with($permission, 'cms.') && in_array('cms.manage', $permissions, true)) {
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
