<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Formulaires publics — envois autorisés par tranche de 10 minutes : [par adresse e-mail, par adresse IP].
     * On compte surtout par e-mail : toute une unité peut s'inscrire depuis le même réseau
     * (même IP), tandis qu'un robot qui envoie en masse bute sur la limite par IP.
     */
    private const PUBLIC_FORM_LIMITS = [
        'registration.store' => [3, 30],
        'public.contact.submit' => [5, 20],
        'public.newsletter.subscribe' => [3, 20],
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // En production (derrière le proxy HTTPS de Railway), on force la
        // génération d'URLs en https pour éviter le contenu mixte bloqué
        // par le navigateur (assets JS/CSS chargés en http -> page blanche).
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('public-forms', function (Request $request) {
            $route = $request->route()?->getName() ?? 'form';
            [$perEmail, $perIp] = self::PUBLIC_FORM_LIMITS[$route] ?? [3, 20];
            $email = Str::lower(trim((string) $request->input('email')));

            $limits = [Limit::perMinutes(10, $perIp)->by("{$route}|ip|{$request->ip()}")];
            // E-mail facultatif (contact) : sans adresse, seule la limite par IP s'applique.
            if ($email !== '') {
                $limits[] = Limit::perMinutes(10, $perEmail)->by("{$route}|email|".hash('sha256', $email));
            }

            return array_map(fn (Limit $limit) => $limit->response(fn (Request $request) => $request->expectsJson()
                ? response()->json(['message' => 'Trop de demandes. Réessayez dans quelques minutes.'], 429)
                : back()->withInput($request->except('password', 'password_confirmation'))
                    ->withErrors(['email' => 'Trop de demandes. Réessayez dans quelques minutes.'])), $limits);
        });
    }
}
