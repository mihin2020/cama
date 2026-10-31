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

    private function __construct(
        private string $ipKey,
        private string $accountKey,
    ) {}

    public static function for(string $scope, Request $request, string $email): self
    {
        $account = $scope.'|'.Str::transliterate(Str::lower($email));

        return new self($account.'|'.$request->ip(), $account);
    }

    public function ensureNotLocked(): void
    {
        $locked = match (true) {
            RateLimiter::tooManyAttempts($this->ipKey, self::MAX_ATTEMPTS) => $this->ipKey,
            RateLimiter::tooManyAttempts($this->accountKey, self::MAX_ATTEMPTS_PER_ACCOUNT) => $this->accountKey,
            default => null,
        };

        if ($locked !== null) {
            $minutes = (int) ceil(RateLimiter::availableIn($locked) / 60);

            throw ValidationException::withMessages([
                'email' => "Trop de tentatives de connexion. Réessayez dans {$minutes} minute(s).",
            ]);
        }
    }

    public function hit(): void
    {
        RateLimiter::hit($this->ipKey, self::LOCKOUT_SECONDS);
        RateLimiter::hit($this->accountKey, self::LOCKOUT_SECONDS);
    }

    public function clear(): void
    {
        RateLimiter::clear($this->ipKey);
        RateLimiter::clear($this->accountKey);
    }
}
