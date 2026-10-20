<?php

namespace App\Services;

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

        // Uniquement les permissions cochées pour le compte (« Accès complet au studio » les couvre,
        // sauf le HTML libre : cf. AdminPermissionService::allows).
        $allows = fn (string $permission) => $this->adminPermissions->allows($admin, $permission);
        $canPages = $allows('cms.pages');
        $canPublish = $canPages && $allows('cms.publish');

        return [
            'canPublishPages' => $canPublish,
            'canDeleteMedia' => $allows('cms.media'),
            'canRestoreVersions' => $canPublish,
            'canDuplicateVersions' => $canPublish,
            'canUseHtmlWidget' => $canPages && $allows('cms.html'),
            'canManageMenus' => $allows('cms.menus'),
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
