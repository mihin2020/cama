<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetMail;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * « Mot de passe oublié » commun au back-office et à l'espace assuré : chaque espace
 * fournit son broker (jetons séparés), ses pages et ses routes.
 */
abstract class PasswordResetController extends Controller
{
    /** Nom du broker de config/auth.php. */
    abstract protected function broker(): string;

    /** Préfixe des routes nommées : « admin » ou « assure ». */
    abstract protected function routePrefix(): string;

    /** Dossier des pages Inertia : « Admin/Auth » ou « Assure/Auth ». */
    abstract protected function pageDirectory(): string;

    /** Complément de phrase de l'e-mail : « le back-office » ou « l'espace assuré ». */
    abstract protected function spaceLabel(): string;

    /**
     * Conditions supplémentaires sur le compte (ex. actif).
     *
     * @return array<string, mixed>
     */
    protected function accountConditions(): array
    {
        return [];
    }

    public function create(): Response
    {
        return Inertia::render($this->pageDirectory().'/ForgotPassword');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);

        try {
            Password::broker($this->broker())->sendResetLink(
                ['email' => $data['email'], ...$this->accountConditions()],
                fn ($user, string $token) => $this->sendMail($user, $token),
            );
        } catch (\Throwable $e) {
            Log::warning('Échec de la demande de réinitialisation de mot de passe', ['error' => $e->getMessage()]);
        }

        // Même réponse que le compte existe ou non : pas d'énumération des adresses.
        return back()->with('success', 'Si un compte correspond à cette adresse, un e-mail contenant un lien de réinitialisation vient de lui être envoyé. Pensez à vérifier vos indésirables.');
    }

    public function edit(Request $request, string $token): Response|RedirectResponse
    {
        $email = $request->query('email');

        if (! is_string($email) || $email === '') {
            return redirect()->route($this->routePrefix().'.password.request')
                ->with('error', 'Lien de réinitialisation invalide.');
        }

        return Inertia::render($this->pageDirectory().'/ResetPassword', [
            'token' => $token,
            'email' => $email,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(12)],
        ]);

        $status = Password::broker($this->broker())->reset(
            [...$request->only('email', 'password', 'password_confirmation', 'token'), ...$this->accountConditions()],
            function ($user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    // Invalide les cookies « Se souvenir de moi » déjà émis.
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()
                ->route($this->routePrefix().'.login')
                ->with('success', 'Mot de passe modifié. Vous pouvez vous connecter.');
        }

        return back()->withErrors(['email' => match ($status) {
            Password::INVALID_TOKEN => 'Ce lien est invalide ou a expiré. Faites une nouvelle demande.',
            Password::INVALID_USER => 'Aucun compte ne correspond à cette adresse e-mail.',
            default => 'Impossible de modifier le mot de passe. Faites une nouvelle demande.',
        }]);
    }

    private function sendMail(CanResetPassword $user, string $token): string
    {
        $email = $user->getEmailForPasswordReset();

        Mail::to($email)->send(new PasswordResetMail(
            fullName: trim(($user->prenom ?? '').' '.($user->nom ?? '')),
            resetUrl: route($this->routePrefix().'.password.reset', ['token' => $token, 'email' => $email]),
            space: $this->spaceLabel(),
            expiresInMinutes: (int) config("auth.passwords.{$this->broker()}.expire"),
        ));

        return Password::RESET_LINK_SENT;
    }
}
