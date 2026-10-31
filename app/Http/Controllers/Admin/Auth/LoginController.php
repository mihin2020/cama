<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Services\AdminLogin2faService;
use App\Support\LoginThrottle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Admin/Auth/Login', [
            'step' => 'credentials',
            'status' => session('status'),
            'rememberDays' => self::rememberDays(),
        ]);
    }

    public function store(Request $request, AdminLogin2faService $twoFactor): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $throttle = LoginThrottle::for('admin-login', $request, $credentials['email']);
        $throttle->ensureNotLocked();

        $guard = Auth::guard('admin');

        // validate() vérifie les identifiants sans ouvrir de session : rien n'est
        // accordé (ni session, ni cookie « Se souvenir de moi ») avant la 2FA.
        if (! $guard->validate($credentials)) {
            $throttle->hit();

            throw ValidationException::withMessages([
                'email' => 'Identifiants incorrects.',
            ]);
        }

        $throttle->clear();

        /** @var AdminUser $admin */
        $admin = $guard->getLastAttempted();

        if (! $admin->actif) {
            throw ValidationException::withMessages([
                'email' => 'Ce compte administrateur est désactivé.',
            ]);
        }

        if ($admin->deux_fa_active) {
            $request->session()->regenerate();
            $request->session()->put('admin_2fa_pending', $admin->id);
            $request->session()->put('admin_2fa_remember', $request->boolean('remember'));

            $devCode = $twoFactor->sendCode($admin);

            return redirect()->route('admin.login.2fa')
                ->with('success', "Un code de vérification a été envoyé à {$twoFactor->maskEmail($admin->email)}.")
                ->with('dev_2fa_code', $devCode);
        }

        $guard->login($admin, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->intended(route('admin.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('admin')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }

    public static function rememberDays(): int
    {
        return max(1, intdiv((int) config('auth.guards.admin.remember', 60 * 24 * 7), 60 * 24));
    }
}
