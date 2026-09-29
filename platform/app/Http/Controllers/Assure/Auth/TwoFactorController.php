<?php

namespace App\Http\Controllers\Assure\Auth;

use App\Http\Controllers\Controller;
use App\Models\Assure;
use App\Services\AssureLogin2faService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Response;

class TwoFactorController extends Controller
{
    public function create(Request $request, AssureLogin2faService $service, LoginController $login): Response|RedirectResponse
    {
        return $login->twoFactorChallenge($request, $service);
    }

    public function store(Request $request, AssureLogin2faService $service): RedirectResponse
    {
        $assure = $this->pendingAssure($request);

        if (! $assure) {
            return redirect()->route('assure.login');
        }

        $data = $request->validate([
            'code' => ['required', 'string', 'size:4'],
        ], [
            'code.size' => 'Le code comporte 4 chiffres.',
        ]);

        $service->verify($assure, $data['code']);

        $remember = (bool) $request->session()->pull('assure_2fa_remember', false);
        $request->session()->forget('assure_2fa_pending');

        Auth::guard('assure')->login($assure, $remember);
        $request->session()->regenerate();

        return redirect()->intended(route('assure.dashboard'));
    }

    public function resend(Request $request, AssureLogin2faService $service, LoginController $login): Response|RedirectResponse
    {
        $assure = $this->pendingAssure($request);

        if (! $assure) {
            return redirect()->route('assure.login');
        }

        $devCode = $service->sendCode($assure);

        return $login->twoFactorChallenge(
            $request,
            $service,
            $devCode,
            "Un nouveau code a été envoyé à {$service->maskEmail($assure->email)}."
        );
    }

    public function destroy(Request $request, AssureLogin2faService $service): RedirectResponse
    {
        if ($assure = $this->pendingAssure($request)) {
            $service->clearCode($assure);
        }

        $request->session()->forget(['assure_2fa_pending', 'assure_2fa_remember']);

        return redirect()->route('assure.login');
    }

    private function pendingAssure(Request $request): ?Assure
    {
        $id = $request->session()->get('assure_2fa_pending');

        return $id ? Assure::query()->find($id) : null;
    }
}
