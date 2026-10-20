<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * En-têtes de sécurité HTTP ajoutés à chaque réponse web.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = [
            // Le navigateur ne « devine » pas le type d'un fichier (ex. un PDF interprété comme du HTML).
            'X-Content-Type-Options' => 'nosniff',
            // Le site ne peut pas être affiché dans un cadre sur un autre site (clickjacking).
            // SAMEORIGIN garde possible l'aperçu du page builder, qui est sur le même domaine.
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), payment=(), usb=(), geolocation=(self)',
            'Cross-Origin-Opener-Policy' => 'same-origin-allow-popups',
        ];

        // HTTPS obligatoire pendant un an, une fois le site servi en HTTPS en production.
        if ($request->isSecure() && app()->environment('production')) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        foreach ($headers as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }
}
