<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\CmsPage;
use App\Services\PublicSiteService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    public function show(string $slug, PublicSiteService $site): Response|RedirectResponse
    {
        if ($slug === 'accueil') {
            return redirect()->route('home');
        }

        $page = CmsPage::query()
            ->where('slug', $slug)
            ->where('status', 'published')
            ->firstOrFail();

        return Inertia::render('Public/CmsPage', [
            'cmsPage' => [
                'title' => $page->title,
                'slug' => $page->slug,
                'sections' => $page->sections_json ?? [],
                'updatedAt' => $page->updated_at?->format('d/m/Y'),
            ],
            'articles' => $site->publishedArticles(12),
        ]);
    }
}
