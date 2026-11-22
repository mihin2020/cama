<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * En-têtes de sécurité HTTP ajoutés à chaque réponse web, dont la politique de sécurité du contenu (CSP).
 *
 * La CSP n'autorise que les scripts, styles, polices et images du site lui-même (plus les images https
 * saisies dans le CMS). Un script injecté dans une page (XSS) ne s'exécute pas : seuls les scripts portant
 * le « nonce » tiré au sort à chaque requête sont acceptés.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // Un nonce par requête, disponible pour les vues (csp_nonce()) et pour les balises ajoutées par Vite.
        $nonce = base64_encode(random_bytes(16));
        $request->attributes->set('csp_nonce', $nonce);
        app()->instance('csp_nonce', $nonce);
        Vite::useCspNonce($nonce);

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
            'Content-Security-Policy' => $this->contentSecurityPolicy($nonce),
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

    private function contentSecurityPolicy(string $nonce): string
    {
        $script = "'self' 'nonce-{$nonce}'";
        $style = "'self' 'nonce-{$nonce}'";
        $connect = "'self'";
        $font = "'self' data:";
        $img = "'self' data: blob: https:";

        // Développement avec le serveur Vite (fichier public/hot) : on autorise son adresse, rien de plus.
        $hot = public_path('hot');
        if (app()->environment('local') && is_file($hot)) {
            $dev = rtrim(trim((string) file_get_contents($hot)), '/');
            $ws = preg_replace('#^http#', 'ws', $dev);
            $script .= " {$dev}";
            $style .= " {$dev}";
            $font .= " {$dev}";
            $img .= " {$dev}";
            $connect .= " {$dev} {$ws}";
        }

        return implode('; ', [
            "default-src 'self'",
            "script-src {$script}",
            "style-src {$style}",
            // Attributs style="…" du contenu éditorial (CMS) ; les scripts restent strictement contrôlés.
            "style-src-attr 'unsafe-inline'",
            "img-src {$img}",
            "font-src {$font}",
            "connect-src {$connect}",
            "media-src 'self' blob: https:",
            "frame-src 'self' blob:",
            "worker-src 'self' blob:",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
        ]);
    }
}
