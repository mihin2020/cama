<?php

namespace App\Http\Controllers\Admin\Cms;

use App\Http\Controllers\Controller;
use App\Services\CmsActivityLogService;
use App\Services\PlatformSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Module « Mentions légales » : textes de la page /mention_legales (RGPD, droits, cookies…).
 */
class LegalNoticeController extends Controller
{
    public function index(PlatformSettingsService $settings): Response
    {
        $footer = $settings->footer();

        return Inertia::render('Admin/Cms/Legal/Index', [
            'notice' => $settings->legalNotice(),
            // Coordonnées affichées en bas de la page : reprises du module « Pied de page ».
            'coordinates' => [
                'address' => $footer['address'] ?? '',
                'phones' => $footer['phones'] ?? [],
                'emails' => $footer['emails'] ?? [],
            ],
        ]);
    }

    public function update(Request $request, PlatformSettingsService $settings): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'intro' => ['nullable', 'string', 'max:2000'],
            'articles' => ['required', 'array', 'min:1', 'max:30'],
            'articles.*.title' => ['required', 'string', 'max:200'],
            'articles.*.body' => ['required', 'string', 'max:20000'],
            'articles.*.icon' => ['nullable', 'string', 'max:60'],
            'articles.*.anchor' => ['nullable', 'string', 'max:80'],
        ], [
            'title.required' => 'Le titre de la page est obligatoire.',
            'articles.min' => 'Gardez au moins une rubrique.',
            'articles.max' => '30 rubriques au maximum.',
            'articles.*.title.required' => 'Chaque rubrique doit avoir un titre.',
            'articles.*.body.required' => 'Chaque rubrique doit avoir un texte.',
        ]);

        $settings->updateLegalNotice($data);
        app(CmsActivityLogService::class)->log($request->user('admin'), 'gavel', 'Mentions légales mises à jour');

        return back()->with('success', 'Mentions légales publiées.');
    }

    public function reset(Request $request, PlatformSettingsService $settings): RedirectResponse
    {
        $settings->resetLegalNotice();
        app(CmsActivityLogService::class)->log($request->user('admin'), 'gavel', 'Mentions légales : texte d\'origine rétabli');

        return back()->with('success', 'Texte d\'origine rétabli.');
    }
}
