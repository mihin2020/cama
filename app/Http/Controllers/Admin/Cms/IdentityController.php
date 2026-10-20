<?php

namespace App\Http\Controllers\Admin\Cms;

use App\Http\Controllers\Controller;
use App\Services\PlatformSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CMS > Identité visuelle : logo et couleurs du site.
 */
class IdentityController extends Controller
{
    private const LOGO_DIRECTORY = 'media/branding';

    private const HEX_RULE = 'regex:/^#[0-9a-fA-F]{6}$/';

    public function index(PlatformSettingsService $settings): Response
    {
        $logo = $settings->siteLogo();
        $theme = $settings->theme();

        return Inertia::render('Admin/Cms/Identity/Index', [
            'logo' => [
                'url' => $logo,
                'isDefault' => $logo === PlatformSettingsService::DEFAULT_LOGO,
            ],
            'theme' => [
                'base' => $theme['base'],
                'custom' => $theme['custom'],
            ],
        ]);
    }

    public function updateLogo(Request $request, PlatformSettingsService $settings): RedirectResponse
    {
        // Pas de SVG : il peut embarquer du script, servi depuis la même origine que le site.
        $request->validate([
            'logo' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048', 'dimensions:min_width=32,min_height=32'],
        ], [
            'logo.required' => 'Choisissez une image.',
            'logo.image' => 'Le fichier doit être une image.',
            'logo.mimes' => 'Formats acceptés : PNG, JPG ou WebP.',
            'logo.max' => 'Le logo ne doit pas dépasser 2 Mo.',
            'logo.dimensions' => 'Le logo doit faire au moins 32 × 32 pixels.',
        ]);

        $file = $request->file('logo');
        $directory = public_path(self::LOGO_DIRECTORY);
        File::ensureDirectoryExists($directory);
        $name = 'logo-'.now()->format('YmdHis').'-'.Str::lower(Str::random(6)).'.'.$file->extension();
        $file->move($directory, $name);

        $previous = $settings->siteLogo();
        $settings->updateSiteLogo('/'.self::LOGO_DIRECTORY.'/'.$name);
        $this->deleteUploadedLogo($previous);

        return back()->with('success', 'Logo mis à jour sur tout le site.');
    }

    public function resetLogo(PlatformSettingsService $settings): RedirectResponse
    {
        $previous = $settings->siteLogo();
        $settings->updateSiteLogo(null);
        $this->deleteUploadedLogo($previous);

        return back()->with('success', 'Logo d’origine rétabli.');
    }

    public function updateTheme(Request $request, PlatformSettingsService $settings): RedirectResponse
    {
        // Les 8 couleurs choisies, et la palette complète calculée par l'écran (siteTheme.js).
        $data = $request->validate([
            'base' => ['required', 'array'],
            'base.*' => ['required', 'string', self::HEX_RULE],
            'tokens' => ['required', 'array'],
            'tokens.*' => ['required', 'string', self::HEX_RULE],
        ], [
            '*.*.regex' => 'Chaque couleur doit être au format #rrggbb.',
        ]);

        $unknown = array_diff(array_keys($data['tokens']), array_keys(PlatformSettingsService::THEME_DEFAULTS));
        $missingBase = array_diff(PlatformSettingsService::THEME_BASE_KEYS, array_keys($data['base']));
        abort_if($unknown !== [] || $missingBase !== [], 422, 'Palette de couleurs incomplète ou invalide.');

        $settings->updateTheme($data['base'], $data['tokens']);

        return back()->with('success', 'Couleurs publiées sur tout le site.');
    }

    public function resetTheme(PlatformSettingsService $settings): RedirectResponse
    {
        $settings->resetTheme();

        return back()->with('success', 'Couleurs d’origine rétablies.');
    }

    private function deleteUploadedLogo(string $path): void
    {
        // On ne supprime que les fichiers envoyés via ce module, jamais le logo d'origine.
        if (str_starts_with($path, '/'.self::LOGO_DIRECTORY.'/')) {
            File::delete(public_path(ltrim($path, '/')));
        }
    }
}
