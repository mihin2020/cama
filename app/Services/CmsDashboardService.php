<?php

namespace App\Services;

use App\Models\CmsArticle;
use App\Models\CmsFaq;
use App\Models\CmsPage;
use App\Models\CmsSlide;

class CmsDashboardService
{
    /**
     * @return array{articles: int, pages: int, slides: int, faq: int}
     */
    public function stats(): array
    {
        return [
            'articles' => CmsArticle::query()->where('status', 'published')->count(),
            'pages' => CmsPage::query()->count(),
            'slides' => CmsSlide::query()->where('active', true)->count(),
            'faq' => CmsFaq::query()->where('published', true)->count(),
        ];
    }
}
