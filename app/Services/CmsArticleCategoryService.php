<?php

namespace App\Services;

use App\Models\AdminUser;
use App\Models\CmsArticleCategory;
use Illuminate\Validation\ValidationException;

class CmsArticleCategoryService
{
    /**
     * @return array<int, array{id: int, name: string, sort_order: int, article_count: int}>
     */
    public function listForAdmin(): array
    {
        return CmsArticleCategory::query()
            ->withCount('articles')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (CmsArticleCategory $cat) => [
                'id' => $cat->id,
                'name' => $cat->name,
                'sort_order' => $cat->sort_order,
                'article_count' => $cat->articles_count,
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    public function options(): array
    {
        return CmsArticleCategory::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (CmsArticleCategory $cat) => [
                'id' => $cat->id,
                'name' => $cat->name,
            ])
            ->all();
    }

    public function create(array $data, ?AdminUser $admin = null): CmsArticleCategory
    {
        $category = CmsArticleCategory::query()->create($data);

        app(CmsActivityLogService::class)->log(
            $admin,
            'newspaper',
            "Catégorie actualités « {$category->name} » créée",
        );

        return $category;
    }

    public function update(CmsArticleCategory $category, array $data, ?AdminUser $admin = null): CmsArticleCategory
    {
        $category->update($data);

        app(CmsActivityLogService::class)->log(
            $admin,
            'newspaper',
            "Catégorie actualités « {$category->name} » mise à jour",
        );

        return $category->fresh();
    }

    public function delete(CmsArticleCategory $category, ?AdminUser $admin = null): void
    {
        if ($category->articles()->exists()) {
            throw ValidationException::withMessages([
                'name' => 'Impossible de supprimer une catégorie qui contient encore des articles.',
            ]);
        }

        $name = $category->name;
        $category->delete();

        app(CmsActivityLogService::class)->log(
            $admin,
            'newspaper',
            "Catégorie actualités « {$name} » supprimée",
        );
    }
}
