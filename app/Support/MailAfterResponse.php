<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Envoi d'e-mails sans faire attendre l'utilisateur.
 *
 * Le serveur SMTP de l'hébergeur met plusieurs secondes à accepter une connexion (accueil
 * retardé, TLS). L'e-mail est donc envoyé APRÈS que la page a été renvoyée au navigateur :
 * avec PHP-FPM (hébergement mutualisé ou serveur), la réponse part tout de suite et l'envoi se
 * fait ensuite, dans le même processus. Aucun VPS, aucune file d'attente ni tâche planifiée n'est
 * nécessaire.
 *
 * Remarque : le serveur de développement « php artisan serve » ne sait pas fermer la réponse
 * avant la fin du script ; en local, la page attend donc encore l'envoi (sauf avec le mailer
 * « log »). En production sous PHP-FPM, l'attente disparaît.
 */
final class MailAfterResponse
{
    /**
     * @param  string  $description  ce qui est envoyé (pour le journal en cas d'échec)
     * @param  Closure(): void  $send  l'envoi lui-même
     * @param  (Closure(Throwable): void)|null  $onFailure  appelé si l'envoi échoue
     */
    public static function send(string $description, Closure $send, ?Closure $onFailure = null): void
    {
        $deliver = function () use ($description, $send, $onFailure): void {
            // Plusieurs envois (newsletter) peuvent dépasser la limite d'exécution par défaut.
            if (function_exists('set_time_limit')) {
                @set_time_limit(300);
            }

            try {
                $send();
            } catch (Throwable $e) {
                Log::error("Envoi d'e-mail impossible : {$description}", ['error' => $e->getMessage()]);

                if ($onFailure) {
                    try {
                        $onFailure($e);
                    } catch (Throwable) {
                        // L'échec est déjà journalisé.
                    }
                }
            }
        };

        // Mailer « log » (développement) : rien d'externe à attendre, envoi immédiat.
        if (config('mail.default') === 'log') {
            $deliver();

            return;
        }

        dispatch($deliver)->afterResponse();
    }
}
