<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Enums\AssureStatut;
use App\Mail\PasswordResetMail;
use App\Models\AdminUser;
use App\Models\Assure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private const NEW_PASSWORD = 'nouveau-mot-de-passe-solide';

    private function makeAdmin(array $attributes = []): AdminUser
    {
        return AdminUser::query()->create(array_merge([
            'nom' => 'Test', 'prenom' => 'Admin', 'email' => 'reset-admin@cama.bf',
            'password' => 'ancien-mot-de-passe', 'role' => AdminRole::Administrateur, 'actif' => true,
        ], $attributes))->fresh();
    }

    private function makeAssure(array $attributes = []): Assure
    {
        return Assure::query()->create(array_merge([
            'nom' => 'Assure', 'prenom' => 'Reset', 'matricule' => 'MAT-R1', 'numero_cim' => 'CIM-R1',
            'numero_cama' => 'CAMA-R1', 'email' => 'reset-assure@example.bf', 'password' => 'ancien-mot-de-passe',
            'statut' => AssureStatut::Actif, 'journal' => [],
        ], $attributes))->fresh();
    }

    public function test_login_pages_link_to_the_forgot_password_pages(): void
    {
        $this->get(route('admin.password.request'))->assertOk();
        $this->get(route('assure.password.request'))->assertOk();
    }

    public function test_admin_receives_a_reset_link_and_can_choose_a_new_password(): void
    {
        Mail::fake();
        $admin = $this->makeAdmin();

        $this->post(route('admin.password.email'), ['email' => $admin->email])->assertSessionHas('success');

        $url = null;
        Mail::assertSent(PasswordResetMail::class, function (PasswordResetMail $mail) use (&$url, $admin) {
            $url = $mail->resetUrl;

            return $mail->hasTo($admin->email) && str_contains($url, '/admin/reinitialisation/');
        });

        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $token = basename(parse_url($url, PHP_URL_PATH));

        $this->get($url)->assertOk();

        $this->post(route('admin.password.update'), [
            'token' => $token, 'email' => $admin->email,
            'password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD,
        ])->assertRedirect(route('admin.login'));

        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $admin->fresh()->password));

        // Le lien ne sert qu'une fois.
        $this->post(route('admin.password.update'), [
            'token' => $token, 'email' => $admin->email,
            'password' => 'encore-un-autre-mot-de-passe', 'password_confirmation' => 'encore-un-autre-mot-de-passe',
        ])->assertSessionHasErrors('email');
    }

    public function test_assure_receives_a_reset_link_and_can_choose_a_new_password(): void
    {
        Mail::fake();
        $assure = $this->makeAssure();

        $this->post(route('assure.password.email'), ['email' => $assure->email])->assertSessionHas('success');

        $url = null;
        Mail::assertSent(PasswordResetMail::class, function (PasswordResetMail $mail) use (&$url, $assure) {
            $url = $mail->resetUrl;

            return $mail->hasTo($assure->email) && str_contains($url, '/espace-assure/reinitialisation/');
        });

        $this->post(route('assure.password.update'), [
            'token' => basename(parse_url($url, PHP_URL_PATH)), 'email' => $assure->email,
            'password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD,
        ])->assertRedirect(route('assure.login'));

        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $assure->fresh()->password));
    }

    public function test_unknown_email_gets_the_same_answer_and_no_mail(): void
    {
        Mail::fake();

        $this->post(route('admin.password.email'), ['email' => 'inconnu@cama.bf'])->assertSessionHas('success');
        $this->post(route('assure.password.email'), ['email' => 'inconnu@example.bf'])->assertSessionHas('success');

        Mail::assertNothingSent();
    }

    public function test_deactivated_admin_gets_no_link_and_cannot_reset(): void
    {
        Mail::fake();
        $admin = $this->makeAdmin();
        $token = Password::broker('admin_users')->createToken($admin);
        $admin->update(['actif' => false]);

        $this->post(route('admin.password.email'), ['email' => $admin->email]);
        Mail::assertNothingSent();

        $this->post(route('admin.password.update'), [
            'token' => $token, 'email' => $admin->email,
            'password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD,
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('ancien-mot-de-passe', $admin->fresh()->password));
    }

    public function test_invalid_token_and_short_password_are_rejected(): void
    {
        $assure = $this->makeAssure();

        $this->post(route('assure.password.update'), [
            'token' => 'jeton-bidon', 'email' => $assure->email,
            'password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD,
        ])->assertSessionHasErrors('email');

        $token = Password::broker('assures')->createToken($assure);
        $this->post(route('assure.password.update'), [
            'token' => $token, 'email' => $assure->email,
            'password' => 'court', 'password_confirmation' => 'court',
        ])->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('ancien-mot-de-passe', $assure->fresh()->password));
    }

    public function test_assure_and_admin_with_the_same_email_do_not_overwrite_each_others_tokens(): void
    {
        $admin = $this->makeAdmin(['email' => 'partage@example.bf']);
        $assure = $this->makeAssure(['email' => 'partage@example.bf']);

        $adminToken = Password::broker('admin_users')->createToken($admin);
        Password::broker('assures')->createToken($assure);

        $this->post(route('admin.password.update'), [
            'token' => $adminToken, 'email' => 'partage@example.bf',
            'password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD,
        ])->assertRedirect(route('admin.login'));
    }
}
