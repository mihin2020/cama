<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="light">
    <head>
        {{-- Absent sur les erreurs sans route (404) : aucune CSP n'est alors envoyée, le nonce vide est sans effet. --}}
        @php($cspNonce = app()->bound('csp_nonce') ? app('csp_nonce') : '')
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <meta name="csp-nonce" content="{{ $cspNonce }}">

        <title inertia>{{ config('app.name', 'CAMA') }}</title>

        {{-- Seules les routes de l'espace courant sont envoyées au navigateur (config/ziggy.php). --}}
        @routes(\App\Http\Middleware\HandleInertiaRequests::routeGroup(request()), $cspNonce)
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        {{-- Couleurs choisies dans CMS > Identité visuelle (valeurs hexadécimales validées à l'enregistrement). --}}
        @php($themeCss = app(\App\Services\PlatformSettingsService::class)->themeCss())
        @if ($themeCss)
            <style id="cama-theme" nonce="{{ $cspNonce }}">{!! $themeCss !!}</style>
        @endif
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
