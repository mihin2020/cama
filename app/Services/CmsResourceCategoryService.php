<?php

namespace App\Services;

use App\Models\AdminUser;
use App\Models\CmsResourceCategory;
use Illuminate\Validation\ValidationException;

class CmsResourceCategoryService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function listForAdmin(): array
    {
        return CmsResourceCategory::query()
            ->withCount('resources')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (CmsResourceCategory $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'sort_order' => $category->sort_order,
                'resource_count' => $category->resources_count,
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    public function options(): array
    {
        return CmsResourceCategory::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (CmsResourceCategory $category) => [
                'id' => $category->id,
                'name' => $category->name,
            ])
            ->all();
    }

    public function create(array $data, ?AdminUser $admin = null): CmsResourceCategory
    {
        $category = CmsResourceCategory::query()->create($data);

        app(CmsActivityLogService::class)->log(
            $admin,
            'folder_open',
            "Catégorie ressource « {$category->name} » créée",
        );

        return $category;
    }

    public function delete(CmsResourceCategory $category, ?AdminUser $admin = null): void
    {
        if ($category->resources()->exists()) {
            throw ValidationException::withMessages([
                'name' => 'Impossible de supprimer une catégorie qui contient encore des documents.',
            ]);
        }

        $name = $category->name;
        $category->delete();

        app(CmsActivityLogService::class)->log(
            $admin,
            'folder_open',
            "Catégorie ressource « {$name} » supprimée",
        );
    }
}
