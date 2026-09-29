<?php

namespace App\Services;

use App\Models\AdminUser;
use App\Models\CmsArticle;
use App\Models\CmsMedia;
use App\Models\CmsPage;
use App\Models\CmsPartner;
use App\Models\CmsResource;
use App\Models\CmsSlide;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class CmsMediaService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function list(?string $query = null, ?string $type = null, ?string $folder = null, ?string $category = null, ?string $extension = null): array
    {
        return CmsMedia::query()
            ->with('uploader')
            ->when($query, function ($builder) use ($query) {
                $builder->where(function ($inner) use ($query) {
                    $inner->where('name', 'like', "%{$query}%")
                        ->orWhere('original_name', 'like', "%{$query}%")
                        ->orWhere('title', 'like', "%{$query}%")
                        ->orWhere('alt_text', 'like', "%{$query}%")
                        ->orWhere('description', 'like', "%{$query}%");
                });
            })
            ->when($type === 'images', fn ($builder) => $builder->where('mime_type', 'like', 'image/%'))
            ->when($type === 'documents', fn ($builder) => $builder->where('mime_type', 'not like', 'image/%'))
            ->when($folder, fn ($builder) => $builder->where('folder', $folder))
            ->when($category, fn ($builder) => $builder->where('category', $category))
            ->when($extension, fn ($builder) => $builder->where('extension', strtolower($extension)))
            ->latest()
            ->get()
            ->map(fn (CmsMedia $media) => $this->format($media))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function store(UploadedFile $file, ?AdminUser $admin = null, array $data = []): CmsMedia
    {
        $directory = public_path('media/cms');

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $extension = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin');
        $filename = Str::uuid().'.'.$extension;
        $size = $file->getSize() ?: 0;
        $mime = $file->getMimeType();
        $original = $file->getClientOriginalName();
        $dimensions = $this->dimensions($file);

        $file->move($directory, $filename);

        $media = CmsMedia::query()->create([
            'disk_path' => 'media/cms/'.$filename,
            'url' => '/media/cms/'.$filename,
            'name' => pathinfo($original, PATHINFO_FILENAME) ?: $filename,
            'original_name' => $original,
            'title' => $data['title'] ?? pathinfo($original, PATHINFO_FILENAME) ?: $filename,
            'alt_text' => $data['alt_text'] ?? null,
            'folder' => $data['folder'] ?? 'general',
            'category' => $data['category'] ?? null,
            'tags' => $this->tagsFromInput($data['tags'] ?? null),
            'description' => $data['description'] ?? null,
            'mime_type' => $mime,
            'extension' => $extension,
            'size' => $size,
            'width' => $dimensions['width'],
            'height' => $dimensions['height'],
            'uploaded_by' => $admin?->id,
        ]);

        app(CmsActivityLogService::class)->log($admin, 'perm_media', 'Média « '.$media->original_name.' » ajouté');

        return $media;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(CmsMedia $media, array $data, ?AdminUser $admin = null): CmsMedia
    {
        $media->update([
            'title' => $data['title'] ?? $media->title,
            'alt_text' => $data['alt_text'] ?? null,
            'folder' => $data['folder'] ?? 'general',
            'category' => $data['category'] ?? null,
            'tags' => $this->tagsFromInput($data['tags'] ?? null),
            'description' => $data['description'] ?? null,
        ]);

        app(CmsActivityLogService::class)->log($admin, 'edit', 'Métadonnées du média « '.$media->original_name.' » mises à jour');

        return $media->fresh();
    }

    public function delete(CmsMedia $media, ?AdminUser $admin = null): void
    {
        $path = public_path($media->disk_path);
        if (is_file($path)) {
            unlink($path);
        }

        $name = $media->original_name;
        $media->delete();

        app(CmsActivityLogService::class)->log($admin, 'delete', 'Média « '.$name.' » supprimé');
    }

    /**
     * @return array{folders: array<int, string>, categories: array<int, string>, extensions: array<int, string>}
     */
    public function options(): array
    {
        return [
            'folders' => CmsMedia::query()->whereNotNull('folder')->distinct()->orderBy('folder')->pluck('folder')->filter()->values()->all(),
            'categories' => CmsMedia::query()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category')->filter()->values()->all(),
            'extensions' => CmsMedia::query()->whereNotNull('extension')->distinct()->orderBy('extension')->pluck('extension')->filter()->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function format(CmsMedia $media): array
    {
        $usages = $this->usages($media);

        return [
            'id' => $media->id,
            'name' => $media->name,
            'originalName' => $media->original_name,
            'title' => $media->title ?? $media->name,
            'altText' => $media->alt_text ?? '',
            'folder' => $media->folder ?? 'general',
            'category' => $media->category ?? '',
            'tags' => $media->tags ?? [],
            'tagsText' => implode(', ', $media->tags ?? []),
            'description' => $media->description ?? '',
            'url' => $media->url,
            'mimeType' => $media->mime_type,
            'extension' => strtoupper((string) $media->extension),
            'size' => CmsDocumentUploadService::humanSize((int) $media->size),
            'width' => $media->width,
            'height' => $media->height,
            'isImage' => str_starts_with((string) $media->mime_type, 'image/'),
            'uploadedBy' => $media->uploader?->display_name ?? 'CAMA',
            'createdAt' => $media->created_at?->format('d/m/Y H:i') ?? '',
            'usages' => $usages,
            'usageCount' => count($usages),
        ];
    }

    /**
     * @return array<int, array{module: string, label: string, url: string|null}>
     */
    public function usages(CmsMedia $media): array
    {
        $needles = array_values(array_unique([
            $media->url,
            ltrim($media->url, '/'),
            $media->disk_path,
        ]));

        $contains = fn (?string $value): bool => $value !== null && collect($needles)->contains(fn (string $needle) => $needle !== '' && str_contains($value, $needle));
        $usages = [];

        CmsPage::query()->select(['id', 'title', 'slug', 'sections_json'])->get()->each(function (CmsPage $page) use (&$usages, $contains) {
            if ($contains(json_encode($page->sections_json ?? [], JSON_UNESCAPED_SLASHES))) {
                $usages[] = ['module' => 'Page', 'label' => $page->title, 'url' => route('admin.cms.page_builder', ['page' => $page->id])];
            }
        });

        CmsArticle::query()->select(['id', 'title', 'image_url'])->get()->each(function (CmsArticle $article) use (&$usages, $contains) {
            if ($contains($article->image_url)) {
                $usages[] = ['module' => 'Actualité', 'label' => $article->title, 'url' => route('admin.cms.actualites')];
            }
        });

        CmsSlide::query()->select(['id', 'title', 'image_url'])->get()->each(function (CmsSlide $slide) use (&$usages, $contains) {
            if ($contains($slide->image_url)) {
                $usages[] = ['module' => 'Bannière', 'label' => $slide->title ?? 'Slide '.$slide->id, 'url' => route('admin.cms.banniere')];
            }
        });

        CmsPartner::query()->select(['id', 'name', 'image_url'])->get()->each(function (CmsPartner $partner) use (&$usages, $contains) {
            if ($contains($partner->image_url)) {
                $usages[] = ['module' => 'Partenaire', 'label' => $partner->name, 'url' => route('admin.cms.partenaires')];
            }
        });

        CmsResource::query()->select(['id', 'title', 'file_url'])->get()->each(function (CmsResource $resource) use (&$usages, $contains) {
            if ($contains($resource->file_url)) {
                $usages[] = ['module' => 'Ressource', 'label' => $resource->title, 'url' => route('admin.cms.ressources')];
            }
        });

        return $usages;
    }

    /**
     * @return array{width: int|null, height: int|null}
     */
    private function dimensions(UploadedFile $file): array
    {
        if (! str_starts_with((string) $file->getMimeType(), 'image/')) {
            return ['width' => null, 'height' => null];
        }

        $size = @getimagesize($file->getRealPath());

        return [
            'width' => $size[0] ?? null,
            'height' => $size[1] ?? null,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function tagsFromInput(mixed $tags): array
    {
        if (is_array($tags)) {
            return array_values(array_filter(array_map(fn ($tag) => trim((string) $tag), $tags)));
        }

        return array_values(array_filter(array_map('trim', explode(',', (string) $tags))));
    }
}
