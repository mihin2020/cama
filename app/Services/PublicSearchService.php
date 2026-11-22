<?php

namespace App\Services;

use App\Models\CmsArticle;
use App\Models\CmsPage;
use App\Models\CmsResource;

class PublicSearchService
{
    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function search(string $term): array
    {
        $q = trim($term);
        if ($q === '') {
            return ['pages' => [], 'articles' => [], 'resources' => []];
        }

        $pages = CmsPage::query()
            ->where('status', 'published')
            ->where(function ($builder) use ($q) {
                $builder->whereLike('title', \App\Support\SqlLike::contains($q))
                    ->orWhereLike('slug', \App\Support\SqlLike::contains($q));
            })
            ->orderBy('title')
            ->limit(8)
            ->get()
            ->map(fn (CmsPage $page) => [
                'id' => $page->id,
                'uuid' => $page->uuid,
                'title' => $page->title,
                'excerpt' => 'Page institutionnelle',
                'href' => $page->slug === 'accueil' ? '/' : '/'.$page->slug,
            ])
            ->all();

        $articles = CmsArticle::query()
            ->with('category')
            ->where('status', 'published')
            ->where(function ($builder) use ($q) {
                $builder->whereLike('title', \App\Support\SqlLike::contains($q))
                    ->orWhereLike('excerpt', \App\Support\SqlLike::contains($q))
                    ->orWhereLike('body_html', \App\Support\SqlLike::contains($q));
            })
            ->orderByDesc('published_at')
            ->limit(10)
            ->get()
            ->map(fn (CmsArticle $article) => [
                'id' => $article->id,
                'uuid' => $article->uuid,
                'title' => $article->title,
                'excerpt' => $article->excerpt ?: ($article->category?->name ?? 'Actualité'),
                'href' => '/actualites/'.$article->slug,
            ])
            ->all();

        $resources = CmsResource::query()
            ->with('category')
            ->where('published', true)
            ->where(function ($builder) use ($q) {
                $builder->whereLike('title', \App\Support\SqlLike::contains($q))
                    ->orWhereLike('description', \App\Support\SqlLike::contains($q));
            })
            ->orderBy('sort_order')
            ->orderBy('title')
            ->limit(10)
            ->get()
            ->map(fn (CmsResource $resource) => [
                'id' => $resource->id,
                'uuid' => $resource->uuid,
                'title' => $resource->title,
                'excerpt' => $resource->description ?: ($resource->category?->name ?? 'Ressource'),
                'href' => '/ressources',
            ])
            ->all();

        return compact('pages', 'articles', 'resources');
    }
}
