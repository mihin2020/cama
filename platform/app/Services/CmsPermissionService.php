<?php

namespace App\Services;

use App\Enums\AdminRole;
use App\Models\AdminUser;

class CmsPermissionService
{
    public function __construct(
        private AdminPermissionService $adminPermissions,
    ) {}

    /**
     * @return array<string, bool>
     */
    public function forAdmin(?AdminUser $admin): array
    {
        if (! $admin) {
            return $this->emptyCapabilities();
        }

        $canManage = $this->adminPermissions->allows($admin, 'cms.manage');
        $canPages = $canManage || $this->adminPermissions->allows($admin, 'cms.pages');
        $isAdmin = $admin->role === AdminRole::Administrateur;

        return [
            'canPublishPages' => $canPages && ($isAdmin || $admin->role === AdminRole::Superviseur || $canManage),
            'canDeleteMedia' => ($canManage || $this->adminPermissions->allows($admin, 'cms.media')) && $isAdmin,
            'canRestoreVersions' => $canPages && $isAdmin,
            'canDuplicateVersions' => $canPages && ($isAdmin || $admin->role === AdminRole::Superviseur || $canManage),
            'canUseHtmlWidget' => $canPages && $isAdmin,
            'canManageMenus' => ($canManage || $this->adminPermissions->allows($admin, 'cms.menus'))
                && ($isAdmin || $admin->role === AdminRole::Superviseur || $canManage),
        ];
    }

    public function authorize(?AdminUser $admin, string $permission): void
    {
        if (! ($this->forAdmin($admin)[$permission] ?? false)) {
            abort(403, 'Action CMS non autorisée pour vos permissions.');
        }
    }

    /**
     * @return array<string, bool>
     */
    private function emptyCapabilities(): array
    {
        return [
            'canPublishPages' => false,
            'canDeleteMedia' => false,
            'canRestoreVersions' => false,
            'canDuplicateVersions' => false,
            'canUseHtmlWidget' => false,
            'canManageMenus' => false,
        ];
    }
}
