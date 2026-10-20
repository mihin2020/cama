<?php

namespace Tests\Feature;

use App\Enums\AssureStatut;
use App\Models\Assure;
use App\Services\FifProfileService;
use App\Support\NumeroFif;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NumeroFifTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    private function assure(?string $armee, ?string $region): Assure
    {
        $n = ++$this->seq;

        return Assure::query()->create([
            'nom' => 'Assure', 'prenom' => "N{$n}", 'matricule' => "MAT-{$n}", 'numero_cim' => "CIM-{$n}",
            'numero_cama' => "CAMA-{$n}", 'email' => "a{$n}@example.bf", 'password' => 'password',
            'armee' => $armee, 'region' => $region,
            'statut' => AssureStatut::Actif, 'journal' => [],
        ]);
    }

    public function test_number_reads_year_army_region_and_registration_rank(): void
    {
        $yy = now()->format('y');

        $first = $this->assure('Armée de Terre', '1re Région Militaire — Kaya');
        $second = $this->assure('Armée de Terre', '1re Région Militaire — Kaya');
        $air = $this->assure('Armée de l\'Air', '2e Région Militaire — Bobo-Dioulasso');
        $third = $this->assure('Armée de Terre', '1re Région Militaire — Kaya');

        $this->assertSame("{$yy}AT1R001", $first->numero_fif);
        $this->assertSame("{$yy}AT1R002", $second->numero_fif);
        $this->assertSame("{$yy}AA2R001", $air->numero_fif, 'Chaque armée / région a son propre rang.');
        $this->assertSame("{$yy}AT1R003", $third->numero_fif);
    }

    public function test_codes_are_derived_from_organisation_labels(): void
    {
        $this->assertSame('AT', NumeroFif::armeeCode('Armée de Terre'));
        $this->assertSame('GN', NumeroFif::armeeCode('Gendarmerie Nationale'));
        $this->assertSame('SPM', NumeroFif::armeeCode('Sapeurs-Pompiers Militaires'));

        $this->assertSame('1R', NumeroFif::regionCode('1re Région Militaire (Ouagadougou)'));
        $this->assertSame('1RA', NumeroFif::regionCode('1re Région Aérienne'));
        $this->assertSame('6GIR', NumeroFif::regionCode('6e Groupement d\'Intervention Rapide (GIR)'));
        $this->assertSame('GCP', NumeroFif::regionCode('Groupement Commando Parachutiste (GCP) — Bobo-Dioulasso'));
        $this->assertSame('GA', NumeroFif::regionCode('Groupement d\'Artillerie — Kaya'));
        $this->assertSame('X', NumeroFif::regionCode(null));
    }

    public function test_number_is_stable_and_printed_on_the_sheet(): void
    {
        $assure = $this->assure('Armée de Terre', '1re Région Militaire — Kaya');
        $numero = $assure->numero_fif;

        // Changer d'unité ne renumérote pas la fiche.
        $assure->update(['region' => '2e Région Militaire — Bobo-Dioulasso']);

        $this->assertSame($numero, app(FifProfileService::class)->profile($assure->fresh())['numeroFif']);
    }

    public function test_legacy_assure_without_number_gets_the_next_rank_on_first_need(): void
    {
        $this->assure('Armée de Terre', '1re Région Militaire — Kaya');
        $legacy = $this->assure('Armée de Terre', '1re Région Militaire — Kaya');
        Assure::query()->whereKey($legacy->id)->update(['numero_fif' => null]);

        $numero = app(FifProfileService::class)->numero($legacy->fresh());

        $this->assertSame(now()->format('y').'AT1R002', $numero);
        $this->assertSame($numero, $legacy->fresh()->numero_fif);
    }
}
