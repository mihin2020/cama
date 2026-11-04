<?php

namespace Tests\Feature;

use App\Enums\AssureStatut;
use App\Models\Assure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * Changer ou réinitialiser son mot de passe doit couper les sessions déjà ouvertes ailleurs
 * (un intrus qui s'est emparé d'une session ne doit pas rester connecté).
 */
class AssureSessionInvalidationTest extends TestCase
{
    use RefreshDatabase;

    private const SESSION_KEY = 'password_hash_assure';

    private function makeAssure(): Assure
    {
        $assure = Assure::query()->create([
            'nom' => 'Assure', 'prenom' => 'Session', 'matricule' => 'MAT-S1', 'numero_cim' => 'CIM-S1',
            'numero_cama' => 'CAMA-S1', 'email' => 'session@example.bf', 'password' => 'ancien-mot-de-passe',
            'statut' => AssureStatut::Actif, 'journal' => [],
        ]);
        $assure->forceFill(['email_verified_at' => now()])->save();

        return $assure->fresh();
    }

    public function test_session_opened_with_the_current_password_stays_valid(): void
    {
        $assure = $this->makeAssure();

        $this->actingAs($assure, 'assure')
            ->withSession([self::SESSION_KEY => $assure->getAuthPassword()])
            ->get(route('assure.profil'))
            ->assertOk();
    }

    public function test_session_opened_before_a_password_change_is_cut(): void
    {
        $assure = $this->makeAssure();
        $oldHash = $assure->getAuthPassword();

        // Mot de passe changé ailleurs (réinitialisation, autre appareil…).
        $assure->forceFill(['password' => 'nouveau-mot-de-passe-solide'])->save();

        $this->actingAs($assure->fresh(), 'assure')
            ->withSession([self::SESSION_KEY => $oldHash])
            ->get(route('assure.profil'))
            ->assertRedirect(route('assure.login'));
    }

    public function test_password_reset_cuts_sessions_opened_before(): void
    {
        $assure = $this->makeAssure();
        $oldHash = $assure->getAuthPassword();
        $token = Password::broker('assures')->createToken($assure);

        $this->post(route('assure.password.update'), [
            'token' => $token, 'email' => $assure->email,
            'password' => 'nouveau-mot-de-passe-solide', 'password_confirmation' => 'nouveau-mot-de-passe-solide',
        ])->assertRedirect(route('assure.login'));

        $this->actingAs($assure->fresh(), 'assure')
            ->withSession([self::SESSION_KEY => $oldHash])
            ->get(route('assure.profil'))
            ->assertRedirect(route('assure.login'));
    }

    public function test_changing_the_password_from_the_profile_keeps_this_session_and_updates_it(): void
    {
        $assure = $this->makeAssure();

        $this->actingAs($assure, 'assure')
            ->withSession([self::SESSION_KEY => $assure->getAuthPassword()])
            ->patch(route('assure.profil.password'), [
                'current_password' => 'ancien-mot-de-passe',
                'password' => 'nouveau-mot-de-passe-solide', 'password_confirmation' => 'nouveau-mot-de-passe-solide',
            ])
            ->assertSessionHasNoErrors();

        $fresh = $assure->fresh();
        $this->assertTrue(Hash::check('nouveau-mot-de-passe-solide', $fresh->password));
        // La session courante est resynchronisée : la requête suivante, avec cette même session, passe.
        $currentSession = session()->all();
        $this->app['auth']->forgetGuards();

        $this->actingAs($fresh, 'assure')
            ->withSession($currentSession)
            ->get(route('assure.profil'))
            ->assertOk();
    }
}
