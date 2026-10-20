<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminProfilService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class ProfilController extends Controller
{
    private const CURRENT_PASSWORD_MESSAGES = [
        'current_password.required' => 'Saisissez votre mot de passe actuel.',
        'current_password.current_password' => 'Mot de passe actuel incorrect.',
    ];

    public function show(AdminProfilService $service): Response
    {
        $admin = auth('admin')->user();

        return Inertia::render('Admin/Profil/Show', [
            'profile' => $service->formatProfile($admin),
        ]);
    }

    public function updatePassword(Request $request, AdminProfilService $service): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(12)],
        ]);

        $service->updatePassword(
            auth('admin')->user(),
            $data['current_password'],
            $data['password'],
        );

        return back()->with('success', 'Mot de passe mis à jour. Vos autres appareils ont été déconnectés.');
    }

    public function toggle2fa(Request $request, AdminProfilService $service): RedirectResponse
    {
        // Mot de passe exigé : une session volée ne doit pas pouvoir couper la 2FA.
        $data = $request->validate([
            'active' => ['required', 'boolean'],
            'current_password' => ['required', 'current_password:admin'],
        ], self::CURRENT_PASSWORD_MESSAGES);

        $service->setTwoFactor(auth('admin')->user(), (bool) $data['active']);

        return back()->with('success', $data['active']
            ? 'Vérification en deux étapes activée : un code vous sera demandé à chaque connexion.'
            : 'Vérification en deux étapes désactivée.');
    }

    public function toggleIdleLogout(Request $request, AdminProfilService $service): RedirectResponse
    {
        // Activer renforce la sécurité : un clic suffit. Désactiver l'affaiblit : mot de passe exigé.
        $data = $request->validate([
            'active' => ['required', 'boolean'],
            'current_password' => [Rule::requiredIf(! $request->boolean('active')), 'nullable', 'current_password:admin'],
        ], self::CURRENT_PASSWORD_MESSAGES);

        $service->setIdleLogout(auth('admin')->user(), (bool) $data['active']);

        return back()->with('success', $data['active']
            ? 'Déconnexion automatique activée après '.config('auth.admin_idle_timeout').' minutes d\'inactivité.'
            : 'Déconnexion automatique désactivée.');
    }

    public function logoutOtherDevices(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password:admin'],
        ], self::CURRENT_PASSWORD_MESSAGES);

        $admin = auth('admin')->user();
        $admin->forceFill(['remember_token' => Str::random(60)])->save();

        // Ré-empreinte du mot de passe : auth.session ferme les autres sessions,
        // et le cookie de cet appareil (s'il existe) est réémis avec le nouveau jeton.
        Auth::guard('admin')->logoutOtherDevices($data['current_password']);

        return back()->with('success', 'Tous vos autres appareils ont été déconnectés.');
    }
}
