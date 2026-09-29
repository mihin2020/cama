<?php

namespace App\Services;

use App\Mail\AssureVerificationCodeMail;
use App\Models\Assure;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class AssureEmailVerificationService
{
    private const EXPIRATION_MINUTES = 15;

    /** Génère un code à 4 chiffres, l'enregistre et planifie l'envoi par e-mail. */
    public function sendCode(Assure $assure): void
    {
        $code = (string) random_int(1000, 9999);

        $assure->forceFill([
            'email_verification_code' => $code,
            'email_verification_expires_at' => now()->addMinutes(self::EXPIRATION_MINUTES),
        ])->save();

        $this->dispatchVerificationMail($assure, $code);

        if (app()->environment('local') && config('app.debug')) {
            Log::info("CAMA vérification e-mail — code pour {$assure->email} : {$code}");
        }
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
            throw ValidationException::withMessages([
                'code' => 'Ce code a expiré. Cliquez sur « Renvoyer le code » pour en recevoir un nouveau.',
            ]);
        }

        if (! hash_equals($assure->email_verification_code, trim($code))) {
            throw ValidationException::withMessages([
                'code' => 'Code incorrect. Vérifiez le code reçu par e-mail.',
            ]);
        }

        $journal = $assure->journal ?? [];
        $journal[] = ['date' => now()->format('d/m/Y H:i'), 'libelle' => 'Adresse e-mail vérifiée par l\'assuré'];

        $assure->forceFill([
            'email_verified_at' => now(),
            'email_verification_code' => null,
            'email_verification_expires_at' => null,
            'journal' => $journal,
        ])->save();
    }

    private function dispatchVerificationMail(Assure $assure, string $code): void
    {
        $assureId = $assure->id;
        $email = $assure->email;

        $send = function () use ($assureId, $email, $code): void {
            $fresh = Assure::query()->find($assureId);
            if (! $fresh) {
                return;
            }

            try {
                Mail::to($email)->send(new AssureVerificationCodeMail($fresh, $code));
            } catch (\Throwable $e) {
                Log::error('Envoi du code de vérification assuré impossible', [
                    'assure_id' => $assureId,
                    'error' => $e->getMessage(),
                ]);
            }
        };

        if (config('mail.default') === 'log') {
            $send();

            return;
        }

        dispatch($send)->afterResponse();
    }
}
