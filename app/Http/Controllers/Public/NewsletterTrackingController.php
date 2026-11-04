<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\CmsNewsletterCampaignService;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class NewsletterTrackingController extends Controller
{
    public function open(string $token, CmsNewsletterCampaignService $campaigns): Response
    {
        $campaigns->recordOpen($token);

        $pixel = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

        return response($pixel, HttpResponse::HTTP_OK, [
            'Content-Type' => 'image/gif',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    public function click(string $token, CmsNewsletterCampaignService $campaigns)
    {
        // Seuls les liens générés par la plateforme (signés) sont suivis : jamais d'adresse libre.
        $url = $campaigns->verifiedClickTarget($token, request()->query('u'), request()->query('s'));

        $target = $url !== null && $campaigns->recordClick($token, $url) ? $url : '/';

        return redirect()->away($target);
    }
}
