<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Admin\Auth\LoginController;
use App\Models\AdminUser;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ferme la session back-office après config('auth.admin_idle_timeout') minutes
 * sans requête ni interaction (le front envoie un « ping » tant que l'admin
 * clique ou tape). Option désactivée par défaut, que chaque admin active dans
 * son profil. Un appareil mémorisé par « Se souvenir de moi » en est exempté :
 * sinon le cookie reconnecterait aussitôt l'admin et la règle serait sans effet.
 */
class EnforceAdminIdleTimeout
{
    public const SESSION_KEY = 'admin_session';

    public function handle(Request $request, Closure $next): Response
    {
        $admin = $request->user('admin');

        if (! $admin) {
            return $next($request);
        }

        $session = $request->session();
        $state = $session->get(self::SESSION_KEY, []);
        $now = time();
        $last = $state['last_activity'] ?? null;

        if (self::applies($admin, $state) && $last && $now - $last > self::timeoutSeconds()) {
            Log::info('Session back-office fermée pour inactivité', ['admin_id' => $admin->id]);

            // logoutCurrentDevice : ne révoque pas les cookies des autres appareils.
            Auth::guard('admin')->logoutCurrentDevice();
            $session->invalidate();
            $session->regenerateToken();

            $message = 'Session fermée après '.config('auth.admin_idle_timeout').' minutes d\'inactivité. Reconnectez-vous.';

            if ($request->expectsJson() && ! $request->header('X-Inertia')) {
                return response()->json(['expired' => true, 'message' => $message], 401);
            }

            return redirect()->route('admin.login')->with('warning', $message);
        }

        $state['started_at'] ??= $now;
        $state['last_activity'] = $now;
        $session->put(self::SESSION_KEY, $state);

        return $next($request);
    }

    /**
     * Appelé à chaque connexion (formulaire, 2FA ou cookie « Se souvenir de moi »).
     */
    public static function start(Request $request, bool $remembered): void
    {
        $request->session()->put(self::SESSION_KEY, [
            'started_at' => time(),
            'last_activity' => time(),
            'remembered' => $remembered,
        ]);
    }

    /**
     * État transmis au front (compte à rebours affiché dans le back-office).
     */
    public static function clientState(Request $request): array
    {
        $state = $request->session()->get(self::SESSION_KEY, []);

        return [
            'enabled' => (bool) $request->user('admin')?->deconnexion_auto_active,
            'idleTimeout' => self::timeoutSeconds(),
            'remembered' => (bool) ($state['remembered'] ?? false),
            'rememberDays' => LoginController::rememberDays(),
            'startedAt' => $state['started_at'] ?? time(),
            'lastActivity' => $state['last_activity'] ?? time(),
            'serverTime' => time(),
        ];
    }

    private static function applies(AdminUser $admin, array $state): bool
    {
        return $admin->deconnexion_auto_active && ! ($state['remembered'] ?? false);
    }

    private static function timeoutSeconds(): int
    {
        return max(1, (int) config('auth.admin_idle_timeout', 15)) * 60;
    }
}
