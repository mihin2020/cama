<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Limite des tentatives de connexion, commune au back-office et à l'espace assuré.
 * Deux compteurs : par e-mail et IP (blocage rapide d'un poste), et par e-mail seul, plus
 * large, pour qu'une IP falsifiée (X-Forwarded-For) ne permette pas d'essayer sans fin.
 */
class LoginThrottle
{
    private const MAX_ATTEMPTS = 5;

    private const MAX_ATTEMPTS_PER_ACCOUNT = 20;

    private const LOCKOUT_SECONDS = 300;

    /**
     * @param  array<string, int>  $limits  clé du compteur => essais autorisés
     */
    private function __construct(private array $limits) {}

    public static function for(string $scope, Request $request, string $email): self
    {
        $account = $scope.'|'.Str::transliterate(Str::lower($email));

        return new self([
            $account.'|'.$request->ip() => self::MAX_ATTEMPTS,
            $account => self::MAX_ATTEMPTS_PER_ACCOUNT,
        ]);
    }

    public function ensureNotLocked(): void
    {
        foreach ($this->limits as $key => $max) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                $minutes = (int) ceil(RateLimiter::availableIn($key) / 60);

                throw ValidationException::withMessages([
                    'email' => "Trop de tentatives de connexion. Réessayez dans {$minutes} minute(s).",
                ]);
            }
        }
    }

    public function hit(): void
    {
        foreach (array_keys($this->limits) as $key) {
            RateLimiter::hit($key, self::LOCKOUT_SECONDS);
        }
    }

    public function clear(): void
    {
        foreach (array_keys($this->limits) as $key) {
            RateLimiter::clear($key);
        }
    }
}
