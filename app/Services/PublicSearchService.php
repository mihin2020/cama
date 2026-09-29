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
                $builder->where('title', 'like', "%{$q}%")
                    ->orWhere('slug', 'like', "%{$q}%");
            })
            ->orderBy('title')
            ->limit(8)
            ->get()
            ->map(fn (CmsPage $page) => [
                'id' => $page->id,
                'title' => $page->title,
                'excerpt' => 'Page institutionnelle',
                'href' => $page->slug === 'accueil' ? '/' : '/'.$page->slug,
            ])
            ->all();

        $articles = CmsArticle::query()
            ->with('category')
            ->where('status', 'published')
            ->where(function ($builder) use ($q) {
                $builder->where('title', 'like', "%{$q}%")
                    ->orWhere('excerpt', 'like', "%{$q}%")
                    ->orWhere('body_html', 'like', "%{$q}%");
            })
            ->orderByDesc('published_at')
            ->limit(10)
            ->get()
            ->map(fn (CmsArticle $article) => [
                'id' => $article->id,
                'title' => $article->title,
                'excerpt' => $article->excerpt ?: ($article->category?->name ?? 'Actualité'),
                'href' => '/actualites/'.$article->slug,
            ])
            ->all();

        $resources = CmsResource::query()
            ->with('category')
            ->where('published', true)
            ->where(function ($builder) use ($q) {
                $builder->where('title', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%");
            })
            ->orderBy('sort_order')
            ->orderBy('title')
            ->limit(10)
            ->get()
            ->map(fn (CmsResource $resource) => [
                'id' => $resource->id,
                'title' => $resource->title,
                'excerpt' => $resource->description ?: ($resource->category?->name ?? 'Ressource'),
                'href' => '/ressources',
            ])
            ->all();

        return compact('pages', 'articles', 'resources');
    }
}
