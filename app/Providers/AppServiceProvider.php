<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
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
    }
}
