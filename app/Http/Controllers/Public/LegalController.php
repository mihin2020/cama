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
            // Contenu piloté par le module « Mentions légales » du studio de contenu.
            'pageSections' => $site->legalSections(),
            'articles' => $site->publishedArticles(12),
        ]);
    }
}
