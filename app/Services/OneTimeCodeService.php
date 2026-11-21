<?php

namespace App\Services;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Codes à usage unique (2FA administrateur et assuré, vérification de l'e-mail).
 *
 *  - 6 caractères mêlant chiffres (0-9) et lettres majuscules (A-Z), au moins un de chaque ;
 *  - 36 symboles, soit environ 2,1 milliards de codes possibles ;
 *  - jamais répété : l'empreinte de chaque code émis est conservée dans one_time_codes avec un
 *    index unique ; un code déjà émis (pour n'importe quel compte) est écarté ;
 *  - jamais stocké en clair : seule l'empreinte HMAC-SHA256 (clé de l'application) est gardée.
 *    Une empreinte déterministe est nécessaire pour vérifier l'unicité ; les mots de passe, eux,
 *    sont hachés avec bcrypt.
 */
class OneTimeCodeService
{
    public const LENGTH = 6;

    public const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

    private const LETTERS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

    private const DIGITS = '0123456789';

    private const MAX_TRIES = 50;

    /** Génère un code jamais émis auparavant et l'enregistre comme utilisé. */
    public function generate(string $context): string
    {
        for ($try = 0; $try < self::MAX_TRIES; $try++) {
            $code = $this->randomCode();

            try {
                DB::table('one_time_codes')->insert([
                    'fingerprint' => $this->fingerprint($code),
                    'context' => $context,
                    'created_at' => now(),
                ]);

                return $code;
            } catch (UniqueConstraintViolationException) {
                // Code déjà émis un jour : on en tire un autre.
            }
        }

        throw new RuntimeException('Impossible de générer un code à usage unique inédit.');
    }

    /** Empreinte du code (saisie normalisée) : c'est elle qui est stockée, jamais le code. */
    public function fingerprint(string $code): string
    {
        return hash_hmac('sha256', self::normalize($code), (string) config('app.key'));
    }

    /** La saisie correspond-elle à l'empreinte stockée ? (comparaison à temps constant) */
    public function matches(?string $storedFingerprint, string $input): bool
    {
        return $storedFingerprint !== null && $storedFingerprint !== ''
            && hash_equals($storedFingerprint, $this->fingerprint($input));
    }

    /** Saisie de l'utilisateur : majuscules, sans espaces ni tirets. */
    public static function normalize(string $input): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $input) ?? '');
    }

    /** Refuse une saisie qui n'a pas le format d'un code (sans consommer d'essai). */
    public static function assertWellFormed(string $input, string $field = 'code'): void
    {
        $code = self::normalize($input);

        if (strlen($code) !== self::LENGTH || strspn($code, self::ALPHABET) !== self::LENGTH) {
            throw ValidationException::withMessages([
                $field => 'Le code comporte '.self::LENGTH.' caractères, tel qu\'indiqué dans l\'e-mail.',
            ]);
        }
    }

    private function randomCode(): string
    {
        $alphabetMax = strlen(self::ALPHABET) - 1;

        do {
            $code = '';
            for ($i = 0; $i < self::LENGTH; $i++) {
                $code .= self::ALPHABET[random_int(0, $alphabetMax)];
            }
            // Au moins une lettre et un chiffre (« mélangé »).
        } while (strpbrk($code, self::LETTERS) === false || strpbrk($code, self::DIGITS) === false);

        return $code;
    }
}
