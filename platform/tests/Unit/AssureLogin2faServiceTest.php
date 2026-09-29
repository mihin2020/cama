<?php

namespace Tests\Unit;

use App\Enums\AssureStatut;
use App\Models\Assure;
use App\Services\AssureLogin2faService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AssureLogin2faServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeAssureWithCode(string $email = 'test@example.bf'): Assure
    {
        $assure = Assure::query()->create([
            'nom' => 'Test',
            'prenom' => 'User',
            'matricule' => 'MAT-'.uniqid(),
            'numero_cim' => 'CIM-'.uniqid(),
            'numero_cama' => 'CAMA-'.uniqid(),
            'email' => $email,
            'password' => 'password',
            'statut' => AssureStatut::Actif,
            'journal' => [],
        ]);

        $assure->forceFill([
            'login_2fa_code' => '1234',
            'login_2fa_expires_at' => now()->addMinutes(10),
        ])->save();

        return $assure->fresh();
    }

    public function test_verify_accepts_valid_code_and_clears_fields(): void
    {
        $service = app(AssureLogin2faService::class);
        $assure = $this->makeAssureWithCode();

        $service->verify($assure, '1234');

        $assure->refresh();
        $this->assertNull($assure->login_2fa_code);
        $this->assertNull($assure->login_2fa_expires_at);
        $this->assertStringContainsString('2FA', $assure->journal[0]['libelle']);
    }

    public function test_verify_rejects_wrong_code(): void
    {
        $service = app(AssureLogin2faService::class);
        $assure = $this->makeAssureWithCode('wrong@example.bf');

        $this->expectException(ValidationException::class);
        $service->verify($assure, '9999');
    }

    public function test_mask_email_hides_local_part(): void
    {
        $service = app(AssureLogin2faService::class);

        $this->assertSame('j***@example.bf', $service->maskEmail('jean@example.bf'));
    }

    public function test_send_code_never_repeats_for_same_assure(): void
    {
        $service = app(AssureLogin2faService::class);

        $assure = Assure::query()->create([
            'nom' => 'Test',
            'prenom' => 'User',
            'matricule' => 'MAT-'.uniqid(),
            'numero_cim' => 'CIM-'.uniqid(),
            'numero_cama' => 'CAMA-'.uniqid(),
            'email' => 'unique@example.bf',
            'password' => 'password',
            'statut' => AssureStatut::Actif,
            'deux_fa_active' => true,
            'journal' => [],
        ]);

        $codes = [];
        for ($i = 0; $i < 5; $i++) {
            $service->sendCode($assure->fresh());
            $assure->refresh();
            $codes[] = $assure->login_2fa_code;
        }

        $this->assertCount(5, array_unique($codes));
    }
}
