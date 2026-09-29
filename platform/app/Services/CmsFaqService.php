<?php

namespace App\Services;

use App\Models\AdminUser;
use App\Models\CmsFaq;
use Illuminate\Database\Eloquent\Collection;

class CmsFaqService
{
    /**
     * @return Collection<int, CmsFaq>
     */
    public function all(): Collection
    {
        return CmsFaq::query()
            ->with('category')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listForAdmin(?int $categoryId = null): array
    {
        $query = CmsFaq::query()
            ->with('category')
            ->orderBy('sort_order')
            ->orderBy('id');

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        return $query->get()->map(fn (CmsFaq $faq) => $this->format($faq))->all();
    }

    public function format(CmsFaq $faq): array
    {
        return [
            'id' => $faq->id,
            'question' => $faq->question,
            'answer' => $faq->answer,
            'category_id' => $faq->category_id,
            'category_name' => $faq->category?->name ?? '—',
            'sort_order' => $faq->sort_order,
            'published' => $faq->published,
        ];
    }

    public function create(array $data, ?AdminUser $admin = null): CmsFaq
    {
        $faq = CmsFaq::query()->create($data);

        app(CmsActivityLogService::class)->log(
            $admin,
            'quiz',
            'Nouvelle question ajoutée à la FAQ',
        );

        return $faq;
    }

    public function update(CmsFaq $faq, array $data, ?AdminUser $admin = null): CmsFaq
    {
        $faq->update($data);

        app(CmsActivityLogService::class)->log(
            $admin,
            'quiz',
            'Question FAQ mise à jour',
        );

        return $faq->fresh();
    }

    public function delete(CmsFaq $faq, ?AdminUser $admin = null): void
    {
        $faq->delete();

        app(CmsActivityLogService::class)->log(
            $admin,
            'quiz',
            'Question FAQ supprimée',
        );
    }
}
