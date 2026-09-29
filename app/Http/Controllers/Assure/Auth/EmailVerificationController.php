<?php

namespace App\Http\Controllers\Assure\Auth;

use App\Http\Controllers\Controller;
use App\Services\AssureEmailVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmailVerificationController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        $assure = $request->user('assure');

        if ($assure->hasVerifiedEmail()) {
            return redirect()->route('assure.dashboard');
        }

        return Inertia::render('Assure/Auth/VerifyEmail', [
            'email' => $assure->email,
            'codePending' => (bool) $assure->email_verification_code,
        ]);
    }

    public function verify(Request $request, AssureEmailVerificationService $service): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'size:4'],
        ], [
            'code.size' => 'Le code comporte 4 chiffres.',
        ]);

        $service->verify($request->user('assure'), $data['code']);

        return redirect()->route('assure.dashboard')
            ->with('success', 'Adresse e-mail vérifiée. Bienvenue dans votre espace assuré.');
    }

    public function resend(Request $request, AssureEmailVerificationService $service): RedirectResponse
    {
        $assure = $request->user('assure');

        if ($assure->hasVerifiedEmail()) {
            return redirect()->route('assure.dashboard');
        }

        $service->sendCode($assure);

        return back()->with('success', "Un nouveau code a été envoyé à {$assure->email}.");
    }
}
