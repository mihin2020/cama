<?php

namespace App\Services;

use App\Models\AdminUser;
use App\Models\CmsFaq;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

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
    public function paginateForAdmin(?int $categoryId, int $perPage): LengthAwarePaginator
    {
        return CmsFaq::query()
            ->with('category')
            ->when($categoryId, fn ($query) => $query->where('category_id', $categoryId))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (CmsFaq $faq) => $this->format($faq));
    }

    public function format(CmsFaq $faq): array
    {
        return [
            'id' => $faq->id,
            'uuid' => $faq->uuid,
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
        $faq = CmsFaq::query()->create([...$data, 'sort_order' => (int) CmsFaq::query()->max('sort_order') + 1]);

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

    /**
     * Échange la question avec sa voisine dans la liste affichée (toutes, ou une seule catégorie quand la liste
     * est filtrée), puis renumérote 1, 2, 3… : l'ordre de la page Services n'a ni doublon ni trou.
     */
    public function move(CmsFaq $faq, string $direction, ?int $categoryId = null, ?AdminUser $admin = null): void
    {
        $moved = DB::transaction(function () use ($faq, $direction, $categoryId) {
            $all = CmsFaq::query()->orderBy('sort_order')->orderBy('id')->lockForUpdate()->get();
            $scope = $categoryId ? $all->where('category_id', $categoryId)->values() : $all;

            $index = $scope->search(fn (CmsFaq $item) => $item->id === $faq->id);
            $neighbor = $index === false ? null : $scope->get($direction === 'up' ? $index - 1 : $index + 1);
            if (! $neighbor) {
                return false;
            }

            $ids = $all->pluck('id')->all();
            $from = array_search($faq->id, $ids, true);
            $to = array_search($neighbor->id, $ids, true);
            [$ids[$from], $ids[$to]] = [$ids[$to], $ids[$from]];

            $byId = $all->keyBy('id');
            foreach ($ids as $position => $id) {
                if ((int) $byId[$id]->sort_order !== $position + 1) {
                    $byId[$id]->update(['sort_order' => $position + 1]);
                }
            }

            return true;
        });

        if ($moved) {
            app(CmsActivityLogService::class)->log($admin, 'quiz', 'Ordre de la FAQ modifié');
        }
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
