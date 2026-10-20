<?php

namespace App\Services;

use App\Mail\AdminLogin2faCodeMail;
use App\Models\AdminUser;
use App\Support\DevCodes;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class AdminLogin2faService
{
    public const CODE_LENGTH = 6;

    public const EXPIRATION_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    /**
     * @return string|null Code affiché uniquement si le mailer est « log » (dev sans SMTP)
     */
    public function sendCode(AdminUser $admin): ?string
    {
        $code = str_pad((string) random_int(0, 10 ** self::CODE_LENGTH - 1), self::CODE_LENGTH, '0', STR_PAD_LEFT);

        $admin->forceFill([
            'login_2fa_code' => $this->fingerprint($code),
            'login_2fa_expires_at' => now()->addMinutes(self::EXPIRATION_MINUTES),
            'login_2fa_attempts' => 0,
        ])->save();

        $this->dispatchLoginCodeMail($admin, $code);

        if (DevCodes::visible()) {
            Log::info("CAMA 2FA admin (mailer log) — code pour {$admin->email} : {$code}");

            return $code;
        }

        return null;
    }

    public function verify(AdminUser $admin, string $code): void
    {
        if (! $admin->login_2fa_code || ! $admin->login_2fa_expires_at) {
            throw ValidationException::withMessages([
                'code' => 'Aucun code en attente. Cliquez sur « Renvoyer le code » ou reconnectez-vous.',
            ]);
        }

        if (now()->isAfter($admin->login_2fa_expires_at)) {
            $this->clearCode($admin);

            throw ValidationException::withMessages([
                'code' => 'Ce code a expiré. Cliquez sur « Renvoyer le code » ou reconnectez-vous.',
            ]);
        }

        $digits = preg_replace('/\D/', '', $code) ?? '';

        if (! hash_equals($admin->login_2fa_code, $this->fingerprint($digits))) {
            $attempts = $admin->login_2fa_attempts + 1;
            $remaining = self::MAX_ATTEMPTS - $attempts;

            if ($remaining <= 0) {
                $this->clearCode($admin);

                throw ValidationException::withMessages([
                    'code' => 'Trop de codes incorrects. Ce code est annulé : demandez-en un nouveau.',
                ]);
            }

            $admin->forceFill(['login_2fa_attempts' => $attempts])->save();

            throw ValidationException::withMessages([
                'code' => "Code incorrect. {$remaining} tentative(s) restante(s).",
            ]);
        }

        $this->clearCode($admin);
    }

    public function clearCode(AdminUser $admin): void
    {
        $admin->forceFill([
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

        return substr($local, 0, 1).str_repeat('*', max(1, strlen($local) - 1))."@{$domain}";
    }

    /**
     * Le code n'est jamais stocké en clair : une fuite de la base ne permet pas de le rejouer.
     */
    private function fingerprint(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }

    private function dispatchLoginCodeMail(AdminUser $admin, string $code): void
    {
        $adminId = $admin->id;
        $email = $admin->email;

        $send = function () use ($adminId, $email, $code): void {
            $fresh = AdminUser::query()->find($adminId);
            if (! $fresh) {
                return;
            }

            try {
                Mail::to($email)->send(new AdminLogin2faCodeMail($fresh, $code));
                Log::info('Code 2FA admin envoyé par e-mail', ['admin_id' => $adminId]);
            } catch (\Throwable $e) {
                Log::error('Envoi du code 2FA admin impossible', [
                    'admin_id' => $adminId,
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
