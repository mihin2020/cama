<?php

namespace Tests\Unit;

use App\Enums\AssureStatut;
use App\Models\Assure;
use App\Models\Dossier;
use App\Models\PlatformSetting;
use App\Services\DossierService;
use App\Services\PlatformSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DossierFifWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function makeAssure(): Assure
    {
        return Assure::query()->create([
            'nom' => 'Test',
            'prenom' => 'User',
            'matricule' => 'MAT-'.uniqid(),
            'numero_cim' => 'CIM-'.uniqid(),
            'numero_cama' => 'CAMA-'.uniqid(),
            'email' => 'dossier-'.uniqid().'@example.bf',
            'password' => 'password',
            'statut' => AssureStatut::Actif,
            'journal' => [],
        ]);
    }

    public function test_has_signed_lot_fif_returns_true_when_fif_has_path(): void
    {
        $service = app(DossierService::class);
        $dossier = new Dossier([
            'pieces' => [
                ['type' => 'FIF signée', 'path' => 'dossiers/1/lot/fif.pdf', 'lot' => true],
                ['type' => 'Acte de mariage', 'path' => 'dossiers/1/membres/acte.pdf'],
            ],
        ]);

        $this->assertTrue($service->hasSignedLotFif($dossier));
    }

    public function test_has_signed_lot_fif_returns_false_when_fif_missing_or_without_path(): void
    {
        $service = app(DossierService::class);

        $withoutFif = new Dossier([
            'pieces' => [
                ['type' => 'Acte de mariage', 'path' => 'dossiers/1/membres/acte.pdf'],
            ],
        ]);

        $fifWithoutPath = new Dossier([
            'pieces' => [
                ['type' => 'FIF signée', 'lot' => true],
            ],
        ]);

        $this->assertFalse($service->hasSignedLotFif($withoutFif));
        $this->assertFalse($service->hasSignedLotFif($fifWithoutPath));
    }

    public function test_submit_famille_requires_fif_when_setting_enabled(): void
    {
        PlatformSetting::query()->updateOrCreate(
            ['key' => 'fif_signee_requise'],
            ['value' => true],
        );

        $service = app(DossierService::class);
        $assure = $this->makeAssure();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('La FIF signée');

        $service->submitFamille($assure, [
            ['nom' => 'Dupont', 'prenom' => 'Marie'],
        ], [], null);
    }

    public function test_submit_famille_without_fif_when_setting_disabled(): void
    {
        PlatformSetting::query()->updateOrCreate(
            ['key' => 'fif_signee_requise'],
            ['value' => false],
        );

        $service = app(DossierService::class);
        $assure = $this->makeAssure();

        $count = $service->submitFamille($assure, [
            ['nom' => 'Dupont', 'prenom' => 'Marie', 'sexe' => 'Féminin', 'date_naissance' => '1990-01-01'],
        ], [], null);

        $this->assertSame(1, $count);
        $dossier = $assure->dossiers()->first();
        $this->assertNotNull($dossier);
        $this->assertSame('initial', $dossier->lot_type);
        $this->assertNotNull($dossier->lot_id);
        $this->assertSame('Soumis', $dossier->statut);
    }

    public function test_submit_famille_marks_complementaire_when_validated_member_exists(): void
    {
        PlatformSetting::query()->updateOrCreate(
            ['key' => 'fif_signee_requise'],
            ['value' => false],
        );

        $assure = $this->makeAssure();
        Dossier::query()->create([
            'assure_id' => $assure->id,
            'ref' => 'CAMA-2026-11111',
            'nom' => 'Ancien',
            'prenom' => 'Membre',
            'lien' => 'Conjoint(e)',
            'statut' => 'Validé',
            'gestionnaire' => 'Non affecté',
            'journal' => [],
            'pieces' => [],
            'lot_type' => 'initial',
        ]);

        $count = app(DossierService::class)->submitFamille($assure, [], [
            ['nom' => 'Enfant', 'prenom' => 'Nouveau', 'lien' => 'Enfant adopté', 'sexe' => 'Masculin', 'date_naissance' => '2015-05-05'],
        ], null);

        $this->assertSame(1, $count);
        $nouveau = $assure->dossiers()->where('statut', 'Soumis')->first();
        $this->assertSame('complementaire', $nouveau->lot_type);
        $this->assertSame('Enfant adopté', $nouveau->lien);
    }
}
