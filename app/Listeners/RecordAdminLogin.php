<?php

namespace App\Listeners;

use App\Http\Middleware\EnforceAdminIdleTimeout;
use App\Models\AdminUser;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Trace toutes les connexions au back-office, y compris les reconnexions
 * silencieuses par cookie « Se souvenir de moi » (qui ne passent pas par le formulaire).
 */
class RecordAdminLogin
{
    public function handle(Login $event): void
    {
        if ($event->guard !== 'admin' || ! $event->user instanceof AdminUser) {
            return;
        }

        $viaCookie = Auth::guard('admin')->viaRemember();

        $event->user->forceFill(['last_login_at' => now()])->saveQuietly();

        if (request()->hasSession()) {
            EnforceAdminIdleTimeout::start(request(), (bool) $event->remember);
        }

        Log::info('Connexion au back-office', [
            'admin_id' => $event->user->id,
            'email' => $event->user->email,
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'mode' => $viaCookie ? 'cookie « Se souvenir de moi »' : 'identifiants',
        ]);
    }
}
