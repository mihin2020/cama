<?php

namespace App\Http\Controllers\Assure\Auth;

use App\Enums\AssureStatut;
use App\Http\Controllers\Controller;
use App\Services\AssureLogin2faService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
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

        if (! Auth::guard('assure')->attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'Adresse e-mail ou mot de passe incorrect.',
            ]);
        }

        /** @var \App\Models\Assure $assure */
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

    private function pendingAssure(Request $request): ?\App\Models\Assure
    {
        $id = $request->session()->get('assure_2fa_pending');

        return $id ? \App\Models\Assure::query()->find($id) : null;
    }
}
