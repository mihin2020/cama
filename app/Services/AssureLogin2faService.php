<?php

namespace App\Services;

use App\Mail\AssureLogin2faCodeMail;
use App\Models\Assure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class AssureLogin2faService
{
    private const EXPIRATION_MINUTES = 15;

    private const MAX_USED_CODES = 500;

    /**
     * @return string|null Code affiché uniquement si le mailer est « log » (dev sans SMTP)
     */
    public function sendCode(Assure $assure): ?string
    {
        $used = $this->usedCodes($assure);

        if ($assure->login_2fa_code) {
            $used->push($assure->login_2fa_code);
        }

        $code = $this->generateUniqueCode($used);
        $used->push($code);

        $assure->forceFill([
            'login_2fa_code' => $code,
            'login_2fa_expires_at' => now()->addMinutes(self::EXPIRATION_MINUTES),
            'login_2fa_used_codes' => $used->unique()->take(self::MAX_USED_CODES)->values()->all(),
        ])->save();

        $this->dispatchLoginCodeMail($assure, $code);

        if (config('mail.default') === 'log') {
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
            throw ValidationException::withMessages([
                'code' => 'Ce code a expiré. Cliquez sur « Renvoyer le code » ou reconnectez-vous.',
            ]);
        }

        $normalized = $this->normalizeCode($code);

        if (! hash_equals($assure->login_2fa_code, $normalized)) {
            throw ValidationException::withMessages([
                'code' => 'Code incorrect. Vérifiez le code reçu par e-mail.',
            ]);
        }

        $used = $this->usedCodes($assure);
        $used->push($assure->login_2fa_code);

        $journal = $assure->journal ?? [];
        $journal[] = ['date' => now()->format('d/m/Y H:i'), 'libelle' => 'Connexion sécurisée (2FA validée)'];

        $assure->forceFill([
            'login_2fa_code' => null,
            'login_2fa_expires_at' => null,
            'login_2fa_used_codes' => $used->unique()->take(self::MAX_USED_CODES)->values()->all(),
            'journal' => $journal,
        ])->save();
    }

    public function clearCode(Assure $assure): void
    {
        $assure->forceFill([
            'login_2fa_code' => null,
            'login_2fa_expires_at' => null,
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

    private function usedCodes(Assure $assure): Collection
    {
        return collect($assure->login_2fa_used_codes ?? [])->map(fn ($c) => $this->normalizeCode((string) $c));
    }

    private function generateUniqueCode(Collection $used): string
    {
        for ($attempt = 0; $attempt < 200; $attempt++) {
            $code = $this->normalizeCode((string) random_int(0, 9999));
            if (! $used->contains($code)) {
                return $code;
            }
        }

        throw ValidationException::withMessages([
            'code' => 'Impossible de générer un nouveau code. Réessayez dans quelques instants.',
        ]);
    }

    private function normalizeCode(string $code): string
    {
        $digits = preg_replace('/\D/', '', $code) ?? '';

        return str_pad(substr($digits, 0, 4), 4, '0', STR_PAD_LEFT);
    }

    private function dispatchLoginCodeMail(Assure $assure, string $code): void
    {
        $assureId = $assure->id;
        $email = $assure->email;

        $send = function () use ($assureId, $email, $code): void {
            $fresh = Assure::query()->find($assureId);
            if (! $fresh) {
                return;
            }

            try {
                Mail::to($email)->send(new AssureLogin2faCodeMail($fresh, $code));
                Log::info('Code 2FA assuré envoyé par e-mail', [
                    'assure_id' => $assureId,
                    'email' => $email,
                ]);
            } catch (\Throwable $e) {
                Log::error('Envoi du code 2FA assuré impossible', [
                    'assure_id' => $assureId,
                    'email' => $email,
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
