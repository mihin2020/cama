<?php

namespace App\Services;

use App\Models\AdminUser;
use App\Models\CmsArticle;
use Illuminate\Support\Str;

class CmsArticleService
{
    public const STATUSES = [
        'draft' => 'Brouillon',
        'scheduled' => 'Programmé',
        'published' => 'Publié',
        'archived' => 'Archivé',
    ];

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listForAdmin(?string $statusFilter = null, ?int $categoryId = null): array
    {
        $query = CmsArticle::query()
            ->with('category')
            ->orderByDesc('published_at')
            ->orderByDesc('id');

        if ($statusFilter && $statusFilter !== 'Tous') {
            $key = array_search($statusFilter, self::STATUSES, true);
            if ($key !== false) {
                $query->where('status', $key);
            }
        }

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        return $query->get()->map(fn (CmsArticle $article) => $this->format($article))->all();
    }

    public function format(CmsArticle $article): array
    {
        return [
            'id' => $article->id,
            'slug' => $article->slug,
            'title' => $article->title,
            'category_id' => $article->category_id,
            'category' => $article->category?->name ?? '—',
            'status' => $article->status,
            'status_label' => self::STATUSES[$article->status] ?? $article->status,
            'author_name' => $article->author_name,
            'published_at' => $article->published_at?->format('d/m/Y') ?? '—',
            'published_at_iso' => $article->published_at?->format('Y-m-d'),
            'image_url' => $article->image_url,
            'image_src' => self::resolveImageUrl($article->image_url),
            'excerpt' => $article->excerpt,
            'body_html' => $article->body_html,
            'featured' => $article->featured,
        ];
    }

    public static function resolveImageUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return '/'.ltrim($path, '/');
    }

    /**
     * Publie les articles programmés dont la date est atteinte.
     */
    public function publishDueArticles(): int
    {
        $due = CmsArticle::query()
            ->where('status', 'scheduled')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->get();

        foreach ($due as $article) {
            $article->update(['status' => 'published']);

            app(CmsActivityLogService::class)->log(
                null,
                'newspaper',
                "Article « {$article->title} » publié automatiquement (programmation)",
                'Système CAMA',
            );
        }

        return $due->count();
    }

    public function create(array $data, ?AdminUser $admin = null): CmsArticle
    {
        if (empty($data['slug'])) {
            $data['slug'] = $this->uniqueSlug($data['title']);
        }

        if (empty($data['author_name']) && $admin) {
            $data['author_name'] = $admin->display_name;
        }

        $article = CmsArticle::query()->create($data);

        app(CmsActivityLogService::class)->log(
            $admin,
            'newspaper',
            "Article « {$article->title} » enregistré",
        );

        return $article;
    }

    public function update(CmsArticle $article, array $data, ?AdminUser $admin = null): CmsArticle
    {
        if (! empty($data['title']) && empty($data['slug'])) {
            $data['slug'] = $this->uniqueSlug($data['title'], $article->id);
        }

        $article->update($data);

        app(CmsActivityLogService::class)->log(
            $admin,
            'newspaper',
            "Article « {$article->title} » mis à jour",
        );

        return $article->fresh();
    }

    public function delete(CmsArticle $article, ?AdminUser $admin = null): void
    {
        $title = $article->title;
        $article->delete();

        app(CmsActivityLogService::class)->log(
            $admin,
            'newspaper',
            "Article « {$title} » supprimé",
        );
    }

    private function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'article';
        $slug = $base;
        $i = 2;

        while (
            CmsArticle::query()
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
