<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Journal des événements de sécurité (OWASP A09) : échecs de connexion et de double authentification,
 * accès refusés, trop de requêtes. Jamais de mot de passe ni de code : seulement qui, d'où et quoi.
 */
final class SecurityLog
{
    /** @param  array<string, mixed>  $context */
    public static function warning(string $event, ?Request $request = null, array $context = []): void
    {
        $request ??= request();

        Log::warning("Sécurité — {$event}", array_merge([
            'ip' => $request?->ip(),
            'url' => $request?->path(),
            'methode' => $request?->method(),
        ], $context));
    }
}
