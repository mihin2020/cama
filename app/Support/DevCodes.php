<?php

namespace App\Support;

/**
 * Codes à usage unique (2FA) affichés à l'écran pour faciliter le développement.
 *
 * Uniquement en local / tests ET avec le mailer « log » : une erreur de configuration en
 * production (MAIL_MAILER=log) ne doit jamais permettre de contourner la double authentification.
 */
class DevCodes
{
    public static function visible(): bool
    {
        return app()->environment(['local', 'testing']) && config('mail.default') === 'log';
    }
}
