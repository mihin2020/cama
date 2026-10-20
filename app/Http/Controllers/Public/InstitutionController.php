<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\CmsFaq;
use App\Models\CmsPartner;
use App\Services\CmsPartnerService;
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

    public function services(PublicSiteService $site, CmsPartnerService $partnerService): Response
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

        // Prestataires de soins uniquement : le siège et les antennes CAMA sont des bureaux, présentés sur la page Contact.
        $partners = CmsPartner::query()
            ->where('published', true)
            ->whereIn('type', CmsPartnerService::PROVIDER_TYPES)
            ->orderBy('city')
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'city', 'address', 'image_url', 'latitude', 'longitude', 'maps_url'])
            ->map(fn (CmsPartner $partner) => [
                'id' => $partner->id,
                'name' => $partner->name,
                'type' => $partner->type,
                'city' => $partner->city,
                'address' => $partner->address !== $partner->city ? $partner->address : null,
                'imageSrc' => $partner->image_url
                    ? (str_starts_with($partner->image_url, 'http') ? $partner->image_url : '/'.ltrim($partner->image_url, '/'))
                    : null,
                // Itinéraire pour toutes les fiches, localisées ou non.
                'mapsUrl' => $partnerService->publicMapsUrl($partner),
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
