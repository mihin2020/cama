<?php

namespace App\Services;

use App\Models\AdminUser;
use App\Models\CmsSlide;
use Illuminate\Http\UploadedFile;

class CmsSlideService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function listForAdmin(): array
    {
        return CmsSlide::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (CmsSlide $slide) => $this->format($slide))
            ->all();
    }

    public function format(CmsSlide $slide): array
    {
        return [
            'id' => $slide->id,
            'title' => $slide->title,
            'subtitle' => $slide->subtitle,
            'image_url' => $slide->image_url,
            'image_src' => CmsArticleService::resolveImageUrl($slide->image_url),
            'link_url' => $slide->link_url,
            'link_label' => $slide->link_label,
            'sort_order' => $slide->sort_order,
            'active' => $slide->active,
        ];
    }

    public function create(array $data, ?UploadedFile $imageFile, ?AdminUser $admin = null): CmsSlide
    {
        if ($imageFile) {
            $data['image_url'] = app(CmsImageUploadService::class)->store($imageFile);
        }

        $slide = CmsSlide::query()->create($data);

        app(CmsActivityLogService::class)->log(
            $admin,
            'view_carousel',
            'Nouveau slide ajouté à la bannière',
        );

        return $slide;
    }

    public function update(CmsSlide $slide, array $data, ?UploadedFile $imageFile, ?AdminUser $admin = null): CmsSlide
    {
        if ($imageFile) {
            app(CmsImageUploadService::class)->deleteIfUploaded($slide->image_url);
            $data['image_url'] = app(CmsImageUploadService::class)->store($imageFile);
        }

        $slide->update($data);

        app(CmsActivityLogService::class)->log(
            $admin,
            'view_carousel',
            'Slide bannière mis à jour',
        );

        return $slide->fresh();
    }

    public function delete(CmsSlide $slide, ?AdminUser $admin = null): void
    {
        app(CmsImageUploadService::class)->deleteIfUploaded($slide->image_url);
        $slide->delete();

        app(CmsActivityLogService::class)->log(
            $admin,
            'view_carousel',
            'Slide bannière supprimé',
        );
    }
}
