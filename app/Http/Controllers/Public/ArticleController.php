<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\CmsArticle;
use App\Services\CmsArticleService;
use App\Services\PublicSiteService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ArticleController extends Controller
{
    public function index(Request $request, PublicSiteService $site): Response
    {
        $articles = CmsArticle::query()
            ->with('category')
            ->where('status', 'published')
            ->orderByDesc('featured')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (CmsArticle $article) => [
                'id' => $article->id,
                'slug' => $article->slug,
                'title' => $article->title,
                'category' => $article->category?->name ?? 'Actualité',
                'date' => $article->published_at?->format('d/m/Y') ?? '',
                'author' => $article->author_name,
                'imageSrc' => CmsArticleService::resolveImageUrl($article->image_url) ?: '/images/CAMA_8.jfif',
                'excerpt' => $article->excerpt,
                'href' => route('public.articles.show', $article->slug),
                'featured' => $article->featured,
            ])
            ->all();

        return Inertia::render('Public/Actualites', [
            'articles' => $articles,
            'categories' => collect($articles)->pluck('category')->filter()->unique()->values()->all(),
            'initialSearch' => trim((string) $request->query('q', '')),
            'pageSections' => $site->pageSections('actualites'),
        ]);
    }

    public function show(string $slug): Response
    {
        $article = CmsArticle::query()
            ->with('category')
            ->where('slug', $slug)
            ->where('status', 'published')
            ->firstOrFail();

        return Inertia::render('Public/ArticleShow', [
            'article' => [
                'title' => $article->title,
                'category' => $article->category?->name ?? 'Actualité',
                'author' => $article->author_name,
                'publishedAt' => $article->published_at?->format('d/m/Y'),
                'imageSrc' => CmsArticleService::resolveImageUrl($article->image_url),
                'excerpt' => $article->excerpt,
                'bodyHtml' => $article->body_html,
            ],
        ]);
    }
}
