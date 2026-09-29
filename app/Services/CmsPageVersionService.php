<?php

namespace App\Services;

use App\Models\AdminUser;
use App\Models\CmsPage;
use App\Models\CmsPageVersion;
use Illuminate\Support\Str;

class CmsPageVersionService
{
    private const MAX_VERSIONS_PER_PAGE = 30;

    public function snapshot(CmsPage $page, string $event = 'save', ?AdminUser $admin = null, ?string $comment = null): CmsPageVersion
    {
        $next = ((int) CmsPageVersion::query()
            ->where('cms_page_id', $page->id)
            ->max('version_number')) + 1;

        return CmsPageVersion::query()->create([
            'cms_page_id' => $page->id,
            'version_number' => $next,
            'event' => $event,
            'comment' => $comment,
            'title' => $page->title,
            'slug' => $page->slug,
            'status' => $page->status,
            'sections_json' => $page->sections_json ?? [],
            'created_by' => $admin?->id,
        ]);

        $this->prune($page);

        return $version;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function list(CmsPage $page, int $limit = 12): array
    {
        return $page->versions()
            ->with('creator')
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (CmsPageVersion $version) => $this->format($version))
            ->all();
    }

    public function restore(CmsPage $page, CmsPageVersion $version, ?AdminUser $admin = null): CmsPage
    {
        if ($version->cms_page_id !== $page->id) {
            abort(404);
        }

        $this->snapshot($page, 'before_restore', $admin);

        $page->update([
            'title' => $version->title,
            'slug' => $version->slug,
            'status' => $version->status,
            'sections_json' => $version->sections_json ?? [],
            'published_at' => $version->status === 'published' ? ($page->published_at ?? now()) : null,
        ]);

        $this->snapshot($page->fresh(), 'restore', $admin, 'Restauration depuis la version '.$version->version_number);
        app(CmsActivityLogService::class)->log($admin, 'history', 'Page « '.$page->title.' » restaurée depuis la version '.$version->version_number);

        return $page->fresh();
    }

    public function duplicate(CmsPage $page, CmsPageVersion $version, ?AdminUser $admin = null): CmsPage
    {
        if ($version->cms_page_id !== $page->id) {
            abort(404);
        }

        $title = $version->title.' - copie v'.$version->version_number;
        $slug = Str::slug($title);
        $baseSlug = $slug;
        $index = 2;

        while (CmsPage::query()->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$index;
            $index += 1;
        }

        $copy = CmsPage::query()->create([
            'title' => $title,
            'slug' => $slug,
            'status' => 'draft',
            'sections_json' => $version->sections_json ?? [],
            'author_id' => $admin?->id,
            'published_at' => null,
        ]);

        $this->snapshot($copy, 'duplicate', $admin, 'Copie depuis '.$page->title.' v'.$version->version_number);
        app(CmsActivityLogService::class)->log($admin, 'content_copy', 'Version '.$version->version_number.' dupliquée en page « '.$copy->title.' »');

        return $copy;
    }

    /**
     * @return array<string, mixed>
     */
    public function format(CmsPageVersion $version): array
    {
        return [
            'id' => $version->id,
            'versionNumber' => $version->version_number,
            'event' => $version->event,
            'eventLabel' => $this->eventLabel($version->event),
            'comment' => $version->comment ?? '',
            'title' => $version->title,
            'status' => $version->status,
            'sections' => $version->sections_json ?? [],
            'sectionCount' => is_array($version->sections_json) ? count($version->sections_json) : 0,
            'widgetCount' => $this->widgetCount($version->sections_json ?? []),
            'createdBy' => $version->creator?->display_name ?? 'CAMA',
            'createdAt' => $version->created_at?->format('d/m/Y H:i') ?? '',
        ];
    }

    private function eventLabel(string $event): string
    {
        return match ($event) {
            'publish' => 'Publication',
            'restore' => 'Restauration',
            'before_restore' => 'Avant restauration',
            'duplicate' => 'Duplication',
            default => 'Enregistrement',
        };
    }

    private function prune(CmsPage $page): void
    {
        $idsToKeep = CmsPageVersion::query()
            ->where('cms_page_id', $page->id)
            ->latest()
            ->limit(self::MAX_VERSIONS_PER_PAGE)
            ->pluck('id');

        CmsPageVersion::query()
            ->where('cms_page_id', $page->id)
            ->whereNotIn('id', $idsToKeep)
            ->delete();
    }

    /**
     * @param  array<int, mixed>  $sections
     */
    private function widgetCount(array $sections): int
    {
        return collect($sections)
            ->sum(fn ($section) => collect($section['columns'] ?? [])
                ->sum(fn ($column) => count($column['widgets'] ?? [])));
    }
}
