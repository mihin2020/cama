<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Inertia\Inertia;
use Inertia\Response;

class InvitationController extends Controller
{
    public function show(Request $request, string $token): Response|RedirectResponse
    {
        $email = $request->query('email');

        if (! is_string($email) || $email === '') {
            return redirect()->route('admin.login')->with('status', 'Lien d\'invitation invalide.');
        }

        return Inertia::render('Admin/Auth/Invitation', [
            'token' => $token,
            'email' => $email,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(12)],
        ]);

        $status = Password::broker('admin_users')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (AdminUser $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()
                ->route('admin.login')
                ->with('success', 'Mot de passe défini. Vous pouvez vous connecter au back-office.');
        }

        $message = match ($status) {
            Password::INVALID_TOKEN => 'Ce lien d\'invitation est invalide ou a expiré.',
            Password::INVALID_USER => 'Aucun compte ne correspond à cette adresse e-mail.',
            default => 'Impossible de définir le mot de passe. Demandez une nouvelle invitation.',
        };

        return back()->withErrors(['email' => $message]);
    }
}
