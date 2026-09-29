<?php

namespace App\Services;

use App\Models\AdminUser;
use App\Models\CmsInfoBanner;
use App\Support\CmsRichTextSanitizer;

class CmsInfoBannerService
{
    /**
     * @return array<string, mixed>
     */
    public function getForAdmin(): array
    {
        $banner = $this->instance();

        return [
            'active' => $banner->active,
            'type' => $banner->type,
            'message' => $this->messageForAdmin($banner),
        ];
    }

    public function update(array $data, ?AdminUser $admin = null): CmsInfoBanner
    {
        $banner = $this->instance();

        $data['message'] = CmsRichTextSanitizer::sanitize($data['message'] ?? '');
        $data['link_url'] = null;
        $data['link_label'] = null;

        $banner->update($data);

        app(CmsActivityLogService::class)->log(
            $admin,
            'campaign',
            'Bandeau d\'information site public publié',
        );

        return $banner->fresh();
    }

    private function instance(): CmsInfoBanner
    {
        return CmsInfoBanner::query()->firstOrCreate([], [
            'active' => true,
            'type' => 'info',
            'message' => 'Campagne d\'enrôlement 2026 : créez votre espace assuré et enrôlez vos ayants droit en ligne. <a href="inscription-assure.html">Commencer l\'enrôlement</a>',
            'link_url' => null,
            'link_label' => null,
        ]);
    }

    private function messageForAdmin(CmsInfoBanner $banner): string
    {
        $message = trim($banner->message ?? '');

        if ($banner->link_url && ! str_contains(strtolower($message), '<a ')) {
            $label = $banner->link_label ?: 'En savoir plus';
            $message = trim($message.' <a href="'.e($banner->link_url, false).'">'.e($label, false).'</a>');
        }

        return $message;
    }
}
