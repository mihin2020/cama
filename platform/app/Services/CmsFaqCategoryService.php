<?php

namespace App\Services;

use App\Models\AdminUser;
use App\Models\CmsFaqCategory;
use Illuminate\Validation\ValidationException;

class CmsFaqCategoryService
{
    /**
     * @return array<int, array{id: int, name: string, sort_order: int, faq_count: int}>
     */
    public function listForAdmin(): array
    {
        return CmsFaqCategory::query()
            ->withCount('faqs')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (CmsFaqCategory $cat) => [
                'id' => $cat->id,
                'name' => $cat->name,
                'sort_order' => $cat->sort_order,
                'faq_count' => $cat->faqs_count,
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    public function options(): array
    {
        return CmsFaqCategory::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (CmsFaqCategory $cat) => [
                'id' => $cat->id,
                'name' => $cat->name,
            ])
            ->all();
    }

    public function create(array $data, ?AdminUser $admin = null): CmsFaqCategory
    {
        $category = CmsFaqCategory::query()->create($data);

        app(CmsActivityLogService::class)->log(
            $admin,
            'quiz',
            "Catégorie FAQ « {$category->name} » créée",
        );

        return $category;
    }

    public function update(CmsFaqCategory $category, array $data, ?AdminUser $admin = null): CmsFaqCategory
    {
        $category->update($data);

        app(CmsActivityLogService::class)->log(
            $admin,
            'quiz',
            "Catégorie FAQ « {$category->name} » mise à jour",
        );

        return $category->fresh();
    }

    public function delete(CmsFaqCategory $category, ?AdminUser $admin = null): void
    {
        if ($category->faqs()->exists()) {
            throw ValidationException::withMessages([
                'name' => 'Impossible de supprimer une catégorie qui contient encore des questions.',
            ]);
        }

        $name = $category->name;
        $category->delete();

        app(CmsActivityLogService::class)->log(
            $admin,
            'quiz',
            "Catégorie FAQ « {$name} » supprimée",
        );
    }
}
