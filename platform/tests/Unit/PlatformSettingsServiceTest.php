<?php

namespace Tests\Unit;

use App\Models\PlatformSetting;
use App\Services\PlatformSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformSettingsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_defaults_align_with_prototype(): void
    {
        $settings = app(PlatformSettingsService::class);

        $this->assertSame(26, $settings->ageMaxEnfant());
        $this->assertFalse($settings->fifSigneeRequise());
        $this->assertTrue($settings->certificatScolarite()['actif']);
        $this->assertSame('Certificat de scolarité', $settings->certificatScolarite()['label']);
        $this->assertTrue($settings->membrePhoto()['conjoint']['actif']);
        $this->assertFalse($settings->membrePhoto()['conjoint']['required']);

        $labels = collect($settings->enfantFiliations())->pluck('label')->all();
        $this->assertContains('Enfant adopté', $labels);
        $this->assertArrayHasKey('Enfant adopté', $settings->piecesFamilleMatrix());
    }

    public function test_update_membres_and_fif(): void
    {
        $settings = app(PlatformSettingsService::class);

        $settings->updateMembres(28, true, 'Certificat scolaire');
        $settings->updateFifSigneeRequise(true);
        $settings->updateMembrePhoto([
            'conjoint' => ['actif' => true, 'required' => true],
            'enfant' => ['actif' => false, 'required' => false],
        ]);

        $this->assertSame(28, $settings->ageMaxEnfant());
        $this->assertTrue($settings->fifSigneeRequise());
        $this->assertSame('Certificat scolaire', $settings->certificatScolarite()['label']);
        $this->assertTrue($settings->membrePhoto()['conjoint']['required']);
        $this->assertFalse($settings->membrePhoto()['enfant']['actif']);
    }

    public function test_update_enfant_filiations_persists_pieces(): void
    {
        $settings = app(PlatformSettingsService::class);

        $settings->updateEnfantFiliations([
            [
                'key' => 'enfant_adopte',
                'label' => 'Enfant adopté',
                'actif' => true,
                'pieces' => [
                    ['key' => 'acte_naissance', 'label' => 'Acte de naissance', 'required' => true],
                    ['key' => 'certificat_tutelle', 'label' => 'Certificat de tutelle', 'required' => true],
                ],
            ],
        ]);

        $adopte = collect($settings->enfantFiliations())->firstWhere('key', 'enfant_adopte');
        $this->assertTrue($adopte['actif']);
        $this->assertCount(2, $adopte['pieces']);
        $this->assertTrue(PlatformSetting::query()->where('key', 'enfant_filiations')->exists());
    }
}
