<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Enums\AssureStatut;
use App\Models\AdminUser;
use App\Models\Assure;
use App\Models\Dossier;
use App\Models\PlatformSetting;
use App\Services\AdminDossierService;
use App\Services\DossierAffectationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DossierAffectationTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    private function gestionnaire(string $nom, bool $actif = true, AdminRole $role = AdminRole::Gestionnaire): AdminUser
    {
        return AdminUser::query()->create([
            'nom' => $nom,
            'prenom' => 'Test',
            'email' => strtolower($nom).'@cama.bf',
            'password' => 'motdepasse-solide',
            'role' => $role,
            'actif' => $actif,
        ]);
    }

    private function assure(): Assure
    {
        $this->seq++;

        return Assure::query()->create([
            'nom' => 'Assure', 'prenom' => "N{$this->seq}",
            'matricule' => "MAT-{$this->seq}", 'numero_cim' => "CIM-{$this->seq}", 'numero_cama' => "CAMA-{$this->seq}",
            'email' => "assure{$this->seq}@example.bf", 'password' => 'password',
            'statut' => AssureStatut::Actif, 'journal' => [],
        ]);
    }

    private function dossier(Assure $assure, string $statut = 'Soumis', ?string $gestionnaire = null): Dossier
    {
        $this->seq++;

        return Dossier::query()->create([
            'assure_id' => $assure->id, 'ref' => "REF-{$this->seq}", 'nom' => 'Membre', 'prenom' => "M{$this->seq}",
            'lien' => 'Enfant', 'statut' => $statut, 'gestionnaire' => $gestionnaire, 'journal' => [],
        ]);
    }

    private function mode(string $mode): void
    {
        PlatformSetting::query()->updateOrCreate(['key' => 'affectation_mode'], ['value' => $mode]);
    }

    private function service(): DossierAffectationService
    {
        return app(DossierAffectationService::class);
    }

    public function test_only_active_gestionnaires_receive_dossiers(): void
    {
        $actif = $this->gestionnaire('Actif');
        $inactif = $this->gestionnaire('Inactif', actif: false);
        $this->gestionnaire('Superviseur', role: AdminRole::Superviseur);
        // Nom présent sur d'anciens dossiers sans compte associé.
        $this->dossier($this->assure(), 'En instruction', 'Sgt. Fantome');

        $this->assertSame([$actif->display_name], $this->service()->assignablePool());

        $this->mode('round_robin');
        foreach (range(1, 4) as $i) {
            $this->assertSame($actif->display_name, $this->service()->autoAssign($this->dossier($this->assure())));
        }

        // Le suivi affiche encore les noms qui détiennent des dossiers en cours, pour les réaffecter.
        $this->assertContains('Sgt. Fantome', $this->service()->pool());
        $this->assertNotContains($inactif->display_name, $this->service()->pool());
    }

    public function test_round_robin_rotates_between_active_gestionnaires(): void
    {
        $a = $this->gestionnaire('Alpha');
        $b = $this->gestionnaire('Bravo');
        $this->mode('round_robin');

        $assigned = collect(range(1, 6))->map(fn () => $this->service()->autoAssign($this->dossier($this->assure())));

        $this->assertSame(3, $assigned->filter(fn ($g) => $g === $a->display_name)->count());
        $this->assertSame(3, $assigned->filter(fn ($g) => $g === $b->display_name)->count());
    }

    public function test_least_loaded_picks_lowest_charge_and_rotates_on_ties(): void
    {
        $a = $this->gestionnaire('Alpha');
        $b = $this->gestionnaire('Bravo');
        $this->dossier($this->assure(), 'En instruction', $a->display_name);
        $this->dossier($this->assure(), 'En instruction', $a->display_name);
        $this->mode('charge_min');

        // Bravo (0) est servi jusqu'à rattraper Alpha (2), puis ils alternent.
        $assigned = collect(range(1, 4))->map(fn () => $this->service()->autoAssign($this->dossier($this->assure())))->all();

        $this->assertSame([$b->display_name, $b->display_name], array_slice($assigned, 0, 2));
        $this->assertNotSame($assigned[2], $assigned[3]);
    }

    public function test_family_follows_its_gestionnaire_only_while_active(): void
    {
        $a = $this->gestionnaire('Alpha');
        $b = $this->gestionnaire('Bravo');
        $this->mode('round_robin');
        $assure = $this->assure();
        $this->dossier($assure, 'En instruction', $b->display_name);

        $this->assertSame($b->display_name, $this->service()->autoAssign($this->dossier($assure)));

        $b->update(['actif' => false]);
        $this->assertSame($a->display_name, $this->service()->autoAssign($this->dossier($assure)));
    }

    public function test_already_assigned_dossier_is_not_reassigned(): void
    {
        $a = $this->gestionnaire('Alpha');
        $this->gestionnaire('Bravo');
        $this->mode('round_robin');
        $dossier = $this->dossier($this->assure(), 'Soumis', $a->display_name);

        $this->assertNull($this->service()->autoAssign($dossier));
        $this->assertSame($a->display_name, $dossier->fresh()->gestionnaire);
    }

    public function test_manual_assignment_rejects_inactive_gestionnaire(): void
    {
        $inactif = $this->gestionnaire('Inactif', actif: false);

        $this->expectException(ValidationException::class);
        app(AdminDossierService::class)->assignFamily($this->assure()->id, $inactif->display_name);
    }

    public function test_closed_dossier_cannot_be_refused_or_completed(): void
    {
        $admin = $this->gestionnaire('Superviseur', role: AdminRole::Superviseur);
        $service = app(AdminDossierService::class);

        foreach (['Validé', 'Refusé', 'Retiré', 'Brouillon'] as $statut) {
            $dossier = $this->dossier($this->assure(), $statut);

            foreach ([fn () => $service->refuse($dossier, 'Motif', $admin), fn () => $service->requestComplement($dossier, ['CNIB'], null, $admin)] as $action) {
                try {
                    $action();
                    $this->fail("Décision acceptée sur un dossier « {$statut} ».");
                } catch (ValidationException) {
                }
            }

            $this->assertSame($statut, $dossier->fresh()->statut);
        }
    }

    public function test_dossier_awaiting_supervision_is_reserved_to_supervisor(): void
    {
        $gestionnaire = $this->gestionnaire('Gest');
        $superviseur = $this->gestionnaire('Sup', role: AdminRole::Superviseur);
        $service = app(AdminDossierService::class);
        $dossier = $this->dossier($this->assure(), 'En attente supervision', $gestionnaire->display_name);

        try {
            $service->refuse($dossier, 'Motif', $gestionnaire);
            $this->fail('Un gestionnaire a refusé un dossier en attente de supervision.');
        } catch (ValidationException) {
        }
        $this->assertSame('En attente supervision', $dossier->fresh()->statut);

        $service->refuse($dossier, 'Motif', $superviseur);
        $this->assertSame('Refusé', $dossier->fresh()->statut);

        // La notification part une fois la décision enregistrée, et une seule fois.
        $this->assertSame(1, \App\Models\AssureNotification::query()
            ->where('assure_id', $dossier->assure_id)->where('type', 'validation_refus')->count());
    }
}
