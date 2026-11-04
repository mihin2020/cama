<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Enums\AssureStatut;
use App\Mail\AssureEmailChangedMail;
use App\Mail\AssureVerificationCodeMail;
use App\Models\AdminUser;
use App\Models\Assure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * L'adresse e-mail sert à récupérer un compte (« mot de passe oublié ») : la changer ne doit
 * pas permettre de s'en emparer (session volée, superviseur indélicat).
 */
class EmailChangeProtectionTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'mot-de-passe-solide';

    private int $seq = 0;

    private function makeAssure(): Assure
    {
        $assure = Assure::query()->create([
            'nom' => 'Assure', 'prenom' => 'Mail', 'matricule' => 'MAT-E1', 'numero_cim' => 'CIM-E1',
            'numero_cama' => 'CAMA-E1', 'email' => 'ancien@example.bf', 'password' => self::PASSWORD,
            'statut' => AssureStatut::Actif, 'journal' => [], 'telephone' => '70000000',
        ]);
        $assure->forceFill(['email_verified_at' => now()])->save();

        return $assure->fresh();
    }

    private function contactPayload(array $override = []): array
    {
        return array_merge([
            'email' => 'ancien@example.bf', 'telephone' => '70000000',
            'personne_a_prevenir' => '', 'tel_personne_a_prevenir' => '', 'numero_cama' => 'CAMA-E1',
        ], $override);
    }

    private function admin(AdminRole $role, array $attributes = []): AdminUser
    {
        $n = ++$this->seq;

        return AdminUser::query()->create(array_merge([
            'nom' => "Agent{$n}", 'prenom' => 'Test', 'email' => "agent{$n}@cama.bf", 'password' => self::PASSWORD,
            'role' => $role, 'actif' => true,
            // « Gérer les comptes » est réservé aux superviseurs et administrateurs.
            'permissions' => $role === AdminRole::Gestionnaire ? ['dossiers.view'] : ['users.manage', 'dossiers.view'],
            'last_login_at' => now(),
        ], $attributes))->fresh();
    }

    private function adminPayload(AdminUser $user, string $email): array
    {
        return [
            'nom' => $user->nom, 'prenom' => $user->prenom, 'email' => $email,
            'role' => $user->role->value, 'permissions' => $user->permissions,
        ];
    }

    // ---------------------------------------------------------------- assuré

    public function test_assure_cannot_change_email_without_current_password(): void
    {
        Mail::fake();
        $assure = $this->makeAssure();

        $this->actingAs($assure, 'assure')
            ->patch(route('assure.profil.update'), $this->contactPayload(['email' => 'pirate@example.bf']))
            ->assertSessionHasErrors('current_password');

        $this->actingAs($assure, 'assure')
            ->patch(route('assure.profil.update'), $this->contactPayload(['email' => 'pirate@example.bf', 'current_password' => 'mauvais']))
            ->assertSessionHasErrors('current_password');

        $this->assertSame('ancien@example.bf', $assure->fresh()->email);
        Mail::assertNothingSent();
    }

    public function test_assure_email_change_requires_reverification_and_warns_the_old_address(): void
    {
        Mail::fake();
        $assure = $this->makeAssure();
        Password::broker('assures')->createToken($assure); // lien « mot de passe oublié » déjà demandé

        $this->actingAs($assure, 'assure')
            ->patch(route('assure.profil.update'), $this->contactPayload(['email' => 'nouveau@example.bf', 'current_password' => self::PASSWORD]))
            ->assertRedirect(route('assure.verification.notice'));

        $fresh = $assure->fresh();
        $this->assertSame('nouveau@example.bf', $fresh->email);
        $this->assertNull($fresh->email_verified_at, 'La nouvelle adresse doit être revérifiée.');

        Mail::assertSent(AssureVerificationCodeMail::class, fn ($m) => $m->hasTo('nouveau@example.bf'));
        Mail::assertSent(AssureEmailChangedMail::class, fn ($m) => $m->hasTo('ancien@example.bf'));

        // Le jeton demandé avant le changement ne survit pas.
        $this->assertDatabaseMissing('assure_password_reset_tokens', ['email' => 'ancien@example.bf']);
    }

    public function test_assure_can_edit_other_contact_fields_without_password(): void
    {
        Mail::fake();
        $assure = $this->makeAssure();

        $this->actingAs($assure, 'assure')
            ->patch(route('assure.profil.update'), $this->contactPayload(['telephone' => '71111111']))
            ->assertSessionHasNoErrors();

        $this->assertSame('71111111', $assure->fresh()->telephone);
        $this->assertNotNull($assure->fresh()->email_verified_at);
        Mail::assertNothingSent();
    }

    // ----------------------------------------------------------------- admin

    public function test_supervisor_cannot_change_the_email_of_an_active_peer(): void
    {
        $superviseur = $this->admin(AdminRole::Superviseur);
        $pair = $this->admin(AdminRole::Superviseur);

        $this->actingAs($superviseur, 'admin')
            ->put(route('admin.utilisateurs.update', $pair), $this->adminPayload($pair, 'pirate@cama.bf'))
            ->assertSessionHasErrors('email');

        $this->assertNotSame('pirate@cama.bf', $pair->fresh()->email);
    }

    public function test_nobody_can_change_their_own_email_through_the_users_form(): void
    {
        $administrateur = $this->admin(AdminRole::Administrateur);

        $this->actingAs($administrateur, 'admin')
            ->put(route('admin.utilisateurs.update', $administrateur), $this->adminPayload($administrateur, 'moi-autre@cama.bf'))
            ->assertSessionHasErrors('email');

        $this->assertNotSame('moi-autre@cama.bf', $administrateur->fresh()->email);
    }

    public function test_administrator_can_change_the_email_of_another_account_and_old_reset_links_die(): void
    {
        $administrateur = $this->admin(AdminRole::Administrateur);
        $cible = $this->admin(AdminRole::Gestionnaire);
        Password::broker('admin_users')->createToken($cible);

        $this->actingAs($administrateur, 'admin')
            ->put(route('admin.utilisateurs.update', $cible), $this->adminPayload($cible, 'corrige@cama.bf'))
            ->assertSessionHasNoErrors();

        $this->assertSame('corrige@cama.bf', $cible->fresh()->email);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $cible->email]);
    }

    public function test_supervisor_can_still_fix_the_email_of_an_invited_account_that_never_logged_in(): void
    {
        $superviseur = $this->admin(AdminRole::Superviseur);
        $invite = $this->admin(AdminRole::Gestionnaire, ['last_login_at' => null, 'invited_at' => now()]);

        $this->actingAs($superviseur, 'admin')
            ->put(route('admin.utilisateurs.update', $invite), $this->adminPayload($invite, 'faute-corrigee@cama.bf'))
            ->assertSessionHasNoErrors();

        $this->assertSame('faute-corrigee@cama.bf', $invite->fresh()->email);
    }
}
