<?php

namespace App\Services;

use App\Models\AdminUser;
use App\Models\CmsResource;
use Illuminate\Http\UploadedFile;

class CmsResourceService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function listForAdmin(?int $categoryId = null): array
    {
        $query = CmsResource::query()
            ->with('category')
            ->orderBy('sort_order')
            ->orderBy('id');

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        return $query->get()->map(fn (CmsResource $resource) => $this->format($resource))->all();
    }

    public function format(CmsResource $resource): array
    {
        return [
            'id' => $resource->id,
            'titre' => $resource->title,
            'description' => $resource->description ?? '',
            'category_id' => $resource->category_id,
            'categorie' => $resource->category?->name ?? '—',
            'format' => $resource->format,
            'taille' => $resource->file_size ?? '',
            'url' => $resource->file_url,
            'url_src' => CmsDocumentUploadService::resolveFileUrl($resource->file_url),
            'ordre' => $resource->sort_order,
            'publie' => $resource->published,
        ];
    }

    public function create(array $data, ?UploadedFile $file, ?AdminUser $admin = null): CmsResource
    {
        if ($file) {
            $data = $this->applyUploadedFile($data, $file);
        }

        if (empty($data['file_url'])) {
            $data['file_url'] = '#';
        }

        $resource = CmsResource::query()->create($data);

        app(CmsActivityLogService::class)->log(
            $admin,
            'folder_open',
            'Document « '.$resource->title.' » ajouté',
        );

        return $resource;
    }

    public function update(CmsResource $resource, array $data, ?UploadedFile $file, ?AdminUser $admin = null): CmsResource
    {
        if ($file) {
            app(CmsDocumentUploadService::class)->deleteIfUploaded($resource->file_url);
            $data = $this->applyUploadedFile($data, $file);
        }

        $resource->update($data);

        app(CmsActivityLogService::class)->log(
            $admin,
            'folder_open',
            'Document « '.$resource->title.' » mis à jour',
        );

        return $resource->fresh();
    }

    public function delete(CmsResource $resource, ?AdminUser $admin = null): void
    {
        $title = $resource->title;
        app(CmsDocumentUploadService::class)->deleteIfUploaded($resource->file_url);
        $resource->delete();

        app(CmsActivityLogService::class)->log(
            $admin,
            'folder_open',
            'Document « '.$title.' » supprimé',
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function applyUploadedFile(array $data, UploadedFile $file): array
    {
        $originalName = $file->getClientOriginalName();
        $size = $file->getSize();

        $data['file_url'] = app(CmsDocumentUploadService::class)->store($file);
        $data['format'] = CmsDocumentUploadService::formatFromFilename($originalName);
        $data['file_size'] = CmsDocumentUploadService::humanSize($size);

        return $data;
    }
}
