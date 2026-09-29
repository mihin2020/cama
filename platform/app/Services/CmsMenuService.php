<?php

namespace App\Services;

use App\Models\AdminUser;
use App\Models\CmsMenuItem;
use App\Models\CmsPage;
use Illuminate\Database\Eloquent\Builder;

class CmsMenuService
{
    public const TYPE_PAGE = 'page';
    public const TYPE_CUSTOM = 'custom';

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listForAdmin(): array
    {
        return $this->queryItems()
            ->get()
            ->map(fn (CmsMenuItem $item) => $this->format($item))
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function availablePages(): array
    {
        return CmsPage::query()
            ->orderByRaw("case when status = 'published' then 0 else 1 end")
            ->orderBy('title')
            ->get()
            ->map(fn (CmsPage $page) => [
                'id' => $page->id,
                'title' => $page->title,
                'slug' => $page->slug,
                'status' => $page->status,
                'statusLabel' => $page->status === 'published' ? 'Publié' : 'brouillon',
            ])
            ->all();
    }

    /**
     * @param  array<int>  $pageIds
     */
    public function addPages(array $pageIds, ?AdminUser $admin = null): void
    {
        $nextOrder = $this->nextOrder();

        $order = array_flip(array_map('intval', $pageIds));

        CmsPage::query()
            ->whereIn('id', $pageIds)
            ->get()
            ->sortBy(fn (CmsPage $page) => $order[$page->id] ?? PHP_INT_MAX)
            ->each(function (CmsPage $page) use (&$nextOrder) {
                CmsMenuItem::query()->create([
                    'type' => self::TYPE_PAGE,
                    'page_id' => $page->id,
                    'label' => $page->title,
                    'url' => null,
                    'depth' => 0,
                    'sort_order' => $nextOrder++,
                ]);
            });

        app(CmsActivityLogService::class)->log(
            $admin,
            'menu',
            'Pages ajoutées au menu du site',
        );
    }

    public function addCustomLink(string $label, string $url, ?AdminUser $admin = null): CmsMenuItem
    {
        $item = CmsMenuItem::query()->create([
            'type' => self::TYPE_CUSTOM,
            'page_id' => null,
            'label' => $label,
            'url' => $url ?: '#',
            'depth' => 0,
            'sort_order' => $this->nextOrder(),
        ]);

        app(CmsActivityLogService::class)->log(
            $admin,
            'menu',
            'Lien « '.$item->label.' » ajouté au menu',
        );

        return $item;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public function saveStructure(array $items, ?AdminUser $admin = null): void
    {
        $existing = CmsMenuItem::query()
            ->whereIn('id', collect($items)->pluck('id')->filter()->all())
            ->get()
            ->keyBy('id');

        foreach ($items as $index => $item) {
            $menuItem = $existing->get((int) ($item['id'] ?? 0));
            if (! $menuItem) {
                continue;
            }

            $depth = (int) ($item['depth'] ?? 0);
            if ($index === 0 || $depth < 0) {
                $depth = 0;
            }

            $menuItem->update([
                'label' => trim((string) ($item['label'] ?? $menuItem->label)) ?: $menuItem->label,
                'depth' => min($depth, 1),
                'sort_order' => $index + 1,
            ]);
        }

        app(CmsActivityLogService::class)->log(
            $admin,
            'menu',
            'Menu du site réorganisé',
        );
    }

    public function delete(CmsMenuItem $item, ?AdminUser $admin = null): void
    {
        $label = $item->label;
        $item->delete();

        $this->reindex();

        app(CmsActivityLogService::class)->log(
            $admin,
            'menu',
            'Élément « '.$label.' » retiré du menu',
        );
    }

    public function format(CmsMenuItem $item): array
    {
        return [
            'id' => $item->id,
            'type' => $item->type,
            'pageId' => $item->page_id,
            'label' => $item->label,
            'url' => $item->url,
            'depth' => $item->depth,
            'ordre' => $item->sort_order,
            'pageTitle' => $item->page?->title,
            'pageStatus' => $item->page?->status,
            'href' => $this->href($item),
        ];
    }

    public function href(CmsMenuItem $item): string
    {
        if ($item->type === self::TYPE_CUSTOM) {
            return $item->url ?: '#';
        }

        if (! $item->page) {
            return '#';
        }

        return $item->page->slug === 'accueil' ? '/' : '/'.$item->page->slug;
    }

    private function queryItems(): Builder
    {
        return CmsMenuItem::query()
            ->with('page')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    private function nextOrder(): int
    {
        return (CmsMenuItem::query()->max('sort_order') ?? 0) + 1;
    }

    private function reindex(): void
    {
        $this->queryItems()
            ->get()
            ->each(fn (CmsMenuItem $item, int $index) => $item->update(['sort_order' => $index + 1]));
    }
}
