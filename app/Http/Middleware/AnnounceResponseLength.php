<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Annonce la taille de la réponse (Content-Length).
 *
 * Les e-mails sont envoyés APRÈS la réponse (voir App\Support\MailAfterResponse), car le serveur SMTP
 * est lent. Avec PHP-FPM / LiteSpeed, la connexion est fermée avant l'envoi. Sur les autres
 * configurations (serveur de développement, mod_php, CGI), le navigateur ne sait pas que la page est
 * complète tant que le script tourne : il attend la fin de l'envoi. Avec la taille annoncée, il
 * affiche la page dès les derniers octets reçus, quel que soit le serveur.
 *
 * Désactivé si PHP compresse lui-même la sortie (la taille annoncée serait fausse).
 */
class AnnounceResponseLength
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response instanceof BinaryFileResponse
            || $response instanceof StreamedResponse
            || $response->headers->has('Content-Length')
            || $response->headers->has('Transfer-Encoding')
            || filter_var(ini_get('zlib.output_compression'), FILTER_VALIDATE_BOOLEAN)
            || $request->isMethod('HEAD')
        ) {
            return $response;
        }

        $content = $response->getContent();
        if (is_string($content)) {
            $response->headers->set('Content-Length', (string) strlen($content));
        }

        return $response;
    }
}
