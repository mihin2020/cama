<?php

namespace App\Http\Controllers\Assure\Auth;

use App\Enums\AssureStatut;
use App\Http\Controllers\Controller;
use App\Models\Assure;
use App\Services\AssureLogin2faService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    /** Essais autorisés par adresse e-mail et par IP avant blocage temporaire. */
    private const MAX_ATTEMPTS = 5;

    private const LOCKOUT_SECONDS = 300;

    public function create(Request $request): Response
    {
        if ($request->session()->has('assure_2fa_pending')) {
            return $this->twoFactorChallenge($request, app(AssureLogin2faService::class));
        }

        return Inertia::render('Assure/Auth/Login', [
            'step' => 'credentials',
            'status' => session('status'),
        ]);
    }

    public function store(Request $request, AssureLogin2faService $twoFactor): Response|RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = 'assure-login|'.Str::transliterate(Str::lower($credentials['email'])).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            $minutes = (int) ceil(RateLimiter::availableIn($throttleKey) / 60);

            throw ValidationException::withMessages([
                'email' => "Trop de tentatives de connexion. Réessayez dans {$minutes} minute(s).",
            ]);
        }

        if (! Auth::guard('assure')->attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey, self::LOCKOUT_SECONDS);

            throw ValidationException::withMessages([
                'email' => 'Adresse e-mail ou mot de passe incorrect.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        /** @var Assure $assure */
        $assure = Auth::guard('assure')->user();

        if ($assure->statut === AssureStatut::Refuse) {
            Auth::guard('assure')->logout();
            throw ValidationException::withMessages([
                'email' => 'Votre demande d\'inscription a été refusée. Contactez la CAMA.',
            ]);
        }

        if ($assure->statut === AssureStatut::Desactive) {
            Auth::guard('assure')->logout();
            throw ValidationException::withMessages([
                'email' => 'Ce compte a été désactivé. Veuillez contacter la CAMA.',
            ]);
        }

        if ($assure->deux_fa_active) {
            $request->session()->put('assure_2fa_pending', $assure->id);
            $request->session()->put('assure_2fa_remember', $request->boolean('remember'));
            Auth::guard('assure')->logout();

            $devCode = $twoFactor->sendCode($assure->fresh());

            return $this->twoFactorChallenge($request, $twoFactor, $devCode);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('assure.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('assure')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('assure.login');
    }

    public function twoFactorChallenge(Request $request, AssureLogin2faService $twoFactor, ?string $devCode = null, ?string $successMessage = null): Response|RedirectResponse
    {
        $assure = $this->pendingAssure($request);

        if (! $assure) {
            return redirect()->route('assure.login');
        }

        if ($devCode !== null) {
            $request->session()->flash('dev_2fa_code', $devCode);
        }

        $request->session()->flash(
            'success',
            $successMessage ?? "Un code de vérification a été envoyé à {$twoFactor->maskEmail($assure->email)}."
        );

        return Inertia::render('Assure/Auth/Login', [
            'step' => '2fa',
            'maskedEmail' => $twoFactor->maskEmail($assure->email),
        ]);
    }

    private function pendingAssure(Request $request): ?Assure
    {
        $id = $request->session()->get('assure_2fa_pending');

        return $id ? Assure::query()->find($id) : null;
    }
}
