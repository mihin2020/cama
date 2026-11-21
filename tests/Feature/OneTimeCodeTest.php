<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Enums\AssureStatut;
use App\Models\AdminUser;
use App\Models\Assure;
use App\Services\AssureEmailVerificationService;
use App\Services\OneTimeCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OneTimeCodeTest extends TestCase
{
    use RefreshDatabase;

    private function assure(array $attributes = []): Assure
    {
        return Assure::query()->create(array_merge([
            'nom' => 'Test', 'prenom' => 'Code', 'matricule' => 'MAT-'.uniqid(), 'numero_cim' => 'CIM-1',
            'numero_cama' => 'CAMA-1', 'email' => 'code@example.bf', 'password' => 'mot-de-passe-solide',
            'statut' => AssureStatut::Actif, 'journal' => [],
        ], $attributes))->fresh();
    }

    public function test_codes_have_six_characters_mixing_digits_and_letters(): void
    {
        $service = app(OneTimeCodeService::class);

        foreach (range(1, 300) as $i) {
            $code = $service->generate('test');

            $this->assertMatchesRegularExpression('/^[A-Z0-9]{6}$/', $code);
            $this->assertMatchesRegularExpression('/[A-Z]/', $code, 'Au moins une lettre.');
            $this->assertMatchesRegularExpression('/[0-9]/', $code, 'Au moins un chiffre.');
        }
    }

    public function test_a_code_is_never_issued_twice(): void
    {
        $service = app(OneTimeCodeService::class);

        $codes = array_map(fn () => $service->generate('test'), range(1, 2000));

        $this->assertCount(2000, array_unique($codes));
        $this->assertSame(2000, DB::table('one_time_codes')->count());
        // Aucun code n'est conservé en clair, seulement son empreinte.
        $this->assertSame(0, DB::table('one_time_codes')->whereIn('fingerprint', $codes)->count());
    }

    public function test_the_database_refuses_a_code_already_issued(): void
    {
        $service = app(OneTimeCodeService::class);
        $code = $service->generate('test');

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        DB::table('one_time_codes')->insert(['fingerprint' => $service->fingerprint($code), 'context' => 'test', 'created_at' => now()]);
    }

    public function test_input_is_case_insensitive_and_ignores_spaces(): void
    {
        $service = app(OneTimeCodeService::class);
        $fingerprint = $service->fingerprint('K7P2QX');

        $this->assertTrue($service->matches($fingerprint, 'k7p 2qx'));
        $this->assertTrue($service->matches($fingerprint, 'K7P-2QX'));
        $this->assertFalse($service->matches($fingerprint, 'K7P2QY'));
    }

    public function test_zero_and_look_alike_characters_are_valid_in_a_code(): void
    {
        $service = app(OneTimeCodeService::class);

        // 0, O, 1, I et L font partie des codes : aucun caractère n'est écarté.
        foreach (['0O1IL5', 'A0B1C2', '000AAA', 'ZZZ999'] as $code) {
            OneTimeCodeService::assertWellFormed($code);
            $this->assertTrue($service->matches($service->fingerprint($code), strtolower($code)));
        }

        $seen = implode('', array_map(fn () => $service->generate('test'), range(1, 400)));
        foreach (str_split('0O1IL') as $char) {
            $this->assertStringContainsString($char, $seen, "Le caractère {$char} doit pouvoir apparaître.");
        }
    }

    public function test_malformed_input_is_refused_without_using_an_attempt(): void
    {
        $assure = $this->assure();
        $assure->forceFill([
            'login_2fa_code' => app(OneTimeCodeService::class)->fingerprint('K7P2QX'),
            'login_2fa_expires_at' => now()->addMinutes(10),
        ])->save();

        try {
            app(\App\Services\AssureLogin2faService::class)->verify($assure->fresh(), '1234');
            $this->fail('Un code mal formé doit être refusé.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('6 caractères', $e->errors()['code'][0]);
        }

        $this->assertSame(0, $assure->fresh()->login_2fa_attempts);
    }

    public function test_email_verification_code_is_hashed_and_limited_to_five_attempts(): void
    {
        Mail::fake();
        $assure = $this->assure(['email' => 'verif@example.bf']);
        $service = app(AssureEmailVerificationService::class);

        $service->sendCode($assure);
        $stored = $assure->fresh()->email_verification_code;
        $this->assertSame(64, strlen($stored), 'Le code de vérification est stocké sous forme d\'empreinte.');

        for ($i = 1; $i <= AssureEmailVerificationService::MAX_ATTEMPTS; $i++) {
            try {
                $service->verify($assure->fresh(), 'AAAA22');
                $this->fail('Un code incorrect doit être refusé.');
            } catch (ValidationException $e) {
                $expected = $i < AssureEmailVerificationService::MAX_ATTEMPTS ? 'restante' : 'annulé';
                $this->assertStringContainsString($expected, $e->errors()['code'][0]);
            }
        }

        $this->assertNull($assure->fresh()->email_verification_code);
        $this->assertNull($assure->fresh()->email_verified_at);
    }

    public function test_email_verification_succeeds_with_the_mailed_code(): void
    {
        $assure = $this->assure(['email' => 'ok@example.bf']);
        $mailed = null;
        Mail::fake();
        // Envoi immédiat (sinon différé après la réponse HTTP, absente ici).
        config(['mail.default' => 'log']);

        app(AssureEmailVerificationService::class)->sendCode($assure);
        Mail::assertSent(\App\Mail\AssureVerificationCodeMail::class, function ($mail) use (&$mailed) {
            $mailed = $mail->code;

            return str_contains($mail->render(), $mail->code);
        });

        app(AssureEmailVerificationService::class)->verify($assure->fresh(), strtolower($mailed));
        $this->assertNotNull($assure->fresh()->email_verified_at);
    }

    public function test_all_passwords_are_stored_with_bcrypt(): void
    {
        $admin = AdminUser::query()->create([
            'nom' => 'B', 'prenom' => 'Crypt', 'email' => 'bcrypt@cama.bf', 'password' => 'motdepasse-solide',
            'role' => AdminRole::Administrateur, 'actif' => true,
        ]);
        $assure = $this->assure(['email' => 'bcrypt@example.bf']);

        $this->assertSame('bcrypt', config('hashing.driver'));
        $this->assertStringStartsWith('$2y$', $admin->fresh()->password);
        $this->assertStringStartsWith('$2y$', $assure->fresh()->password);
    }
}
