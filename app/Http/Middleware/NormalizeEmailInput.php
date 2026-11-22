<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Les adresses e-mail sont toujours en minuscules, sans espaces autour : MySQL compare sans tenir compte de la
 * casse, PostgreSQL non — « Prenom.Nom@… » et « prenom.nom@… » doivent désigner le même compte sur les deux.
 */
class NormalizeEmailInput
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethodSafe()) {
            $normalized = [];

            foreach (['email', 'new_email'] as $key) {
                if (is_string($request->input($key))) {
                    $normalized[$key] = mb_strtolower(trim($request->input($key)));
                }
            }

            if ($normalized !== []) {
                $request->merge($normalized);
            }
        }

        return $next($request);
    }
}
