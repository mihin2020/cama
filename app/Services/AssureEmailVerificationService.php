<?php

namespace App\Services;

use App\Support\MailAfterResponse;
use App\Mail\AssureVerificationCodeMail;
use App\Models\Assure;
use App\Support\DevCodes;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class AssureEmailVerificationService
{
    public const EXPIRATION_MINUTES = 15;

    /** Codes incorrects tolérés avant d'annuler le code en cours. */
    public const MAX_ATTEMPTS = 5;

    public function __construct(private OneTimeCodeService $codes) {}

    /**
     * Génère un code de 6 caractères (chiffres et lettres, jamais émis auparavant), en enregistre
     * l'empreinte (jamais le code en clair) et planifie l'envoi par e-mail.
     */
    public function sendCode(Assure $assure): void
    {
        $code = $this->codes->generate('email_verification');

        $assure->forceFill([
            'email_verification_code' => $this->codes->fingerprint($code),
            'email_verification_expires_at' => now()->addMinutes(self::EXPIRATION_MINUTES),
            'email_verification_attempts' => 0,
        ])->save();

        $this->dispatchVerificationMail($assure, $code);

        if (DevCodes::visible()) {
            Log::info("CAMA vérification e-mail (mailer log) — code pour {$assure->email} : {$code}");
        }
    }

    private function clearCode(Assure $assure): void
    {
        $assure->forceFill([
            'email_verification_code' => null,
            'email_verification_expires_at' => null,
            'email_verification_attempts' => 0,
        ])->save();
    }

    public function verify(Assure $assure, string $code): void
    {
        if ($assure->hasVerifiedEmail()) {
            return;
        }

        if (! $assure->email_verification_code || ! $assure->email_verification_expires_at) {
            throw ValidationException::withMessages([
                'code' => 'Aucun code en attente. Cliquez sur « Renvoyer le code ».',
            ]);
        }

        if (now()->isAfter($assure->email_verification_expires_at)) {
            $this->clearCode($assure);

            throw ValidationException::withMessages([
                'code' => 'Ce code a expiré. Cliquez sur « Renvoyer le code » pour en recevoir un nouveau.',
            ]);
        }

        OneTimeCodeService::assertWellFormed($code);

        if (! $this->codes->matches($assure->email_verification_code, $code)) {
            $attempts = $assure->email_verification_attempts + 1;
            $remaining = self::MAX_ATTEMPTS - $attempts;

            if ($remaining <= 0) {
                $this->clearCode($assure);

                throw ValidationException::withMessages([
                    'code' => 'Trop de codes incorrects. Ce code est annulé : cliquez sur « Renvoyer le code » pour en recevoir un nouveau.',
                ]);
            }

            $assure->forceFill(['email_verification_attempts' => $attempts])->save();

            throw ValidationException::withMessages([
                'code' => "Code incorrect. {$remaining} tentative(s) restante(s).",
            ]);
        }

        $journal = $assure->journal ?? [];
        $journal[] = ['date' => now()->format('d/m/Y H:i'), 'libelle' => 'Adresse e-mail vérifiée par l\'assuré'];

        $assure->forceFill([
            'email_verified_at' => now(),
            'email_verification_code' => null,
            'email_verification_expires_at' => null,
            'email_verification_attempts' => 0,
            'journal' => $journal,
        ])->save();
    }

    private function dispatchVerificationMail(Assure $assure, string $code): void
    {
        $email = $assure->email;
        $mail = new AssureVerificationCodeMail($assure, $code);

        // Envoyé après la réponse : la page s'affiche sans attendre le serveur SMTP.
        MailAfterResponse::send("code de vérification d'e-mail ({$email})", fn () => Mail::to($email)->send($mail));
    }
}
