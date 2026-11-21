<?php

namespace App\Services;

use App\Support\MailAfterResponse;
use App\Mail\AssureLogin2faCodeMail;
use App\Models\Assure;
use App\Support\DevCodes;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class AssureLogin2faService
{
    public const EXPIRATION_MINUTES = 10;

    /** Codes incorrects tolérés avant d'annuler le code en cours. */
    public const MAX_ATTEMPTS = 5;

    public function __construct(private OneTimeCodeService $codes) {}

    /**
     * @return string|null Code affiché uniquement si le mailer est « log » (dev sans SMTP)
     */
    public function sendCode(Assure $assure): ?string
    {
        // 6 caractères (chiffres et lettres), jamais émis auparavant, pour aucun compte.
        $code = $this->codes->generate('assure_2fa');

        $assure->forceFill([
            'login_2fa_code' => $this->codes->fingerprint($code),
            'login_2fa_expires_at' => now()->addMinutes(self::EXPIRATION_MINUTES),
            'login_2fa_attempts' => 0,
        ])->save();

        $this->dispatchLoginCodeMail($assure, $code);

        if (DevCodes::visible()) {
            Log::info("CAMA 2FA (mailer log) — code pour {$assure->email} : {$code}");

            return $code;
        }

        return null;
    }

    public function verify(Assure $assure, string $code): void
    {
        if (! $assure->login_2fa_code || ! $assure->login_2fa_expires_at) {
            throw ValidationException::withMessages([
                'code' => 'Aucun code en attente. Reconnectez-vous pour en recevoir un nouveau.',
            ]);
        }

        if (now()->isAfter($assure->login_2fa_expires_at)) {
            $this->clearCode($assure);

            throw ValidationException::withMessages([
                'code' => 'Ce code a expiré. Cliquez sur « Renvoyer le code » ou reconnectez-vous.',
            ]);
        }

        OneTimeCodeService::assertWellFormed($code);

        if (! $this->codes->matches($assure->login_2fa_code, $code)) {
            $attempts = $assure->login_2fa_attempts + 1;
            $remaining = self::MAX_ATTEMPTS - $attempts;

            if ($remaining <= 0) {
                // Le code en cours est annulé : il faut en demander un nouveau.
                $this->clearCode($assure);

                throw ValidationException::withMessages([
                    'code' => 'Trop de codes incorrects. Ce code est annulé : cliquez sur « Renvoyer le code » pour en recevoir un nouveau.',
                ]);
            }

            $assure->forceFill(['login_2fa_attempts' => $attempts])->save();

            throw ValidationException::withMessages([
                'code' => "Code incorrect. {$remaining} tentative(s) restante(s).",
            ]);
        }

        $journal = $assure->journal ?? [];
        $journal[] = ['date' => now()->format('d/m/Y H:i'), 'libelle' => 'Connexion sécurisée (2FA validée)'];

        $assure->forceFill([
            'login_2fa_code' => null,
            'login_2fa_expires_at' => null,
            'login_2fa_attempts' => 0,
            'journal' => $journal,
        ])->save();
    }

    public function clearCode(Assure $assure): void
    {
        $assure->forceFill([
            'login_2fa_code' => null,
            'login_2fa_expires_at' => null,
            'login_2fa_attempts' => 0,
        ])->save();
    }

    public function maskEmail(string $email): string
    {
        if (! str_contains($email, '@')) {
            return $email;
        }

        [$local, $domain] = explode('@', $email, 2);
        $visible = substr($local, 0, 1);
        $masked = $visible.str_repeat('*', max(1, strlen($local) - 1));

        return "{$masked}@{$domain}";
    }

    private function dispatchLoginCodeMail(Assure $assure, string $code): void
    {
        $email = $assure->email;
        $mail = new AssureLogin2faCodeMail($assure, $code);

        // Envoyé après la réponse : la page du code s'affiche sans attendre le serveur SMTP.
        MailAfterResponse::send("code 2FA assuré ({$email})", fn () => Mail::to($email)->send($mail));
    }
}
