<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="light">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'CAMA') }}</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700;800&family=Inter:wght@400;600;700&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&display=swap" rel="stylesheet">

        {{-- Seules les routes de l'espace courant sont envoyées au navigateur (config/ziggy.php). --}}
        @routes(auth('admin')->check() ? 'admin' : (request()->is('admin', 'admin/*') ? 'admin-guest' : 'site'))
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        {{-- Couleurs choisies dans CMS > Identité visuelle (valeurs hexadécimales validées à l'enregistrement). --}}
        @php($themeCss = app(\App\Services\PlatformSettingsService::class)->themeCss())
        @if ($themeCss)
            <style id="cama-theme">{!! $themeCss !!}</style>
        @endif
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
