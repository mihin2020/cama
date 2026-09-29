<?php

namespace App\Http\Controllers\Admin\Cms;

use App\Http\Controllers\Controller;
use App\Services\PlatformSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FooterController extends Controller
{
    public function index(PlatformSettingsService $settings): Response
    {
        return Inertia::render('Admin/Cms/Footer/Index', [
            'footer' => $settings->footer(),
        ]);
    }

    public function update(Request $request, PlatformSettingsService $settings): RedirectResponse
    {
        $data = $request->validate([
            'tagline' => ['nullable', 'string', 'max:400'],
            'address' => ['nullable', 'string', 'max:300'],
            'phones' => ['present', 'array', 'max:10'],
            'phones.*' => ['nullable', 'string', 'max:40'],
            'emails' => ['present', 'array', 'max:10'],
            'emails.*' => ['nullable', 'email', 'max:150'],
            'hours' => ['nullable', 'string', 'max:150'],
            'play_store_url' => ['nullable', 'url', 'max:300'],
            'socials' => ['present', 'array', 'max:15'],
            'socials.*.network' => ['nullable', 'string', 'max:40'],
            'socials.*.url' => ['nullable', 'string', 'max:300'],
            'useful_links' => ['present', 'array', 'max:15'],
            'useful_links.*.label' => ['nullable', 'string', 'max:150'],
            'useful_links.*.url' => ['nullable', 'string', 'max:300'],
        ]);

        $settings->updateFooter($data);

        return back()->with('success', 'Pied de page publié sur le site public.');
    }
}
