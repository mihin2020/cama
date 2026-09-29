<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\CmsFaq;
use App\Models\CmsPartner;
use App\Services\PublicSiteService;
use Inertia\Inertia;
use Inertia\Response;

class InstitutionController extends Controller
{
    public function about(PublicSiteService $site): Response
    {
        return Inertia::render('Public/Apropos', [
            'pageSections' => $site->pageSections('apropos'),
            'articles' => $site->publishedArticles(12),
        ]);
    }

    public function services(PublicSiteService $site): Response
    {
        $faqs = CmsFaq::query()
            ->with('category')
            ->where('published', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (CmsFaq $faq) => [
                'id' => $faq->id,
                'question' => $faq->question,
                'answer' => $faq->answer,
                'category' => $faq->category?->name ?? 'Général',
            ])
            ->all();

        $partners = CmsPartner::query()
            ->where('published', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (CmsPartner $partner) => [
                'id' => $partner->id,
                'name' => $partner->name,
                'type' => $partner->type,
                'city' => $partner->city,
                'description' => $partner->description ?? '',
                'imageSrc' => $partner->image_url
                    ? (str_starts_with($partner->image_url, 'http') ? $partner->image_url : '/'.ltrim($partner->image_url, '/'))
                    : '/images/CAMA_1.jfif',
                'mapsUrl' => $partner->maps_url ?: ($partner->latitude && $partner->longitude
                    ? 'https://www.google.com/maps/search/?api=1&query='.$partner->latitude.','.$partner->longitude
                    : null),
            ])
            ->all();

        return Inertia::render('Public/Services', [
            'faqs' => $faqs,
            'partners' => $partners,
            'articles' => $site->publishedArticles(12),
            'pageSections' => $site->servicesSections(),
        ]);
    }
}
