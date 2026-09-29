<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\CmsPartnerService;
use App\Services\PublicContactService;
use App\Services\PublicSiteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    public function submit(Request $request, PublicContactService $contact): RedirectResponse
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:160'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:60'],
            'subject' => ['nullable', 'string', 'max:160'],
            'message' => ['required', 'string', 'max:10000'],
            'consent' => ['accepted'],
            'source_page' => ['nullable', 'string', 'max:120'],
        ]);

        $contact->submit($data, $request);

        return back()->with('success', 'Votre message a bien été envoyé.');
    }

    public function index(PublicSiteService $site, CmsPartnerService $partners): Response
    {
        $locations = $partners->listForPublicMap();
        $partnersOnly = array_values(array_filter(
            $locations,
            fn (array $location) => in_array($location['type'], ['centre', 'partenaire'], true),
        ));

        return Inertia::render('Public/Contact', [
            'locations' => $locations,
            'partners' => $partnersOnly,
            'articles' => $site->publishedArticles(12),
            'pageSections' => $site->pageSections('contact'),
        ]);
    }
}
