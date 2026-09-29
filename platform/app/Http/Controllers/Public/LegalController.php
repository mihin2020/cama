<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\PublicSiteService;
use Inertia\Inertia;
use Inertia\Response;

class LegalController extends Controller
{
    public function legal(PublicSiteService $site): Response
    {
        return Inertia::render('Public/Legal', [
            'pageSections' => $site->pageSections('mention_legales'),
            'articles' => $site->publishedArticles(12),
        ]);
    }

    public function accessibility(PublicSiteService $site): Response
    {
        return Inertia::render('Public/Accessibility', [
            'pageSections' => $site->pageSections('accessibilite'),
            'articles' => $site->publishedArticles(12),
        ]);
    }
}
