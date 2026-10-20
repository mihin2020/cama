<?php

use App\Http\Middleware\RedirectIfAdminAuthenticated;
use App\Http\Middleware\RedirectIfAssureAuthenticated;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Railway (et autres PaaS) placent l'app derrière un reverse proxy qui
        // termine le TLS. On fait confiance au proxy pour lire X-Forwarded-Proto
        // afin que Laravel génère des URLs en https et évite le contenu mixte.
        // Sur un hébergement mutualisé sans proxy, mettre TRUSTED_PROXIES=none : sinon un visiteur
        // peut falsifier son IP (X-Forwarded-For) et contourner les limites de tentatives.
        $proxies = (string) env('TRUSTED_PROXIES', '*');
        if (! in_array(strtolower($proxies), ['', 'none', 'false'], true)) {
            $middleware->trustProxies(at: $proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }

        $middleware->web(append: [
            \App\Http\Middleware\SecurityHeaders::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'guest.assure' => RedirectIfAssureAuthenticated::class,
            'guest.admin' => RedirectIfAdminAuthenticated::class,
            'admin.active' => \App\Http\Middleware\EnsureAdminActive::class,
            'admin.idle' => \App\Http\Middleware\EnforceAdminIdleTimeout::class,
            'admin.role' => \App\Http\Middleware\EnsureAdminRole::class,
            'admin.permission' => \App\Http\Middleware\EnsureAdminPermission::class,
            'assure.verified' => \App\Http\Middleware\EnsureAssureEmailVerified::class,
        ]);

        \Illuminate\Auth\Middleware\RedirectIfAuthenticated::redirectUsing(function (Request $request) {
            if ($request->is('admin') || $request->is('admin/*')) {
                return route('admin.dashboard');
            }

            if ($request->is('espace-assure') || $request->is('espace-assure/*')) {
                return route('assure.dashboard');
            }

            return route('home');
        });

        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('admin') || $request->is('admin/*')) {
                return route('admin.login');
            }

            if ($request->is('espace-assure') || $request->is('espace-assure/*')) {
                return route('assure.login');
            }

            return route('home');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
