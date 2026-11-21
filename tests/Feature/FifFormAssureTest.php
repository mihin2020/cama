<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Enums\AssureStatut;
use App\Models\AdminUser;
use App\Models\Assure;
use App\Models\FifSection;
use App\Services\FifSchemaService;
use App\Services\PlatformSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/** Les sections et champs ajoutés par l'administration apparaissent dans les formulaires de l'assuré. */
class FifFormAssureTest extends TestCase
{
    use RefreshDatabase;

    private function fif(): FifSchemaService
    {
        return app(FifSchemaService::class);
    }

    private function section(string $cible, string $titre): FifSection
    {
        return $this->fif()->createSection($cible, ['titre' => $titre]);
    }

    private function registration(array $overrides = []): array
    {
        $org = app(PlatformSettingsService::class)->orgStructure();

        return array_merge([
            'nom' => 'Ouedraogo', 'prenom' => 'Awa', 'sexe' => 'Féminin', 'matricule' => 'MAT-001',
            'grade' => $org['grades'][0], 'categorie' => $org['categories'][0],
            'numero_informatique' => 'INF-1', 'numero_cim' => 'CIM-1', 'numero_cama' => 'CAMA-1',
            'armee' => $org['armees'][0], 'region' => 'Région inconnue',
            'telephone' => '+22670000000', 'email' => 'awa@example.bf',
            'password' => 'Motdepasse#2026', 'password_confirmation' => 'Motdepasse#2026', 'cgu' => true,
        ], $overrides);
    }

    private function activeAssure(): Assure
    {
        $assure = Assure::query()->create([
            'nom' => 'Assure', 'prenom' => 'Actif', 'matricule' => 'MAT-A1', 'numero_cim' => 'CIM-A1', 'numero_cama' => 'CAMA-A1',
            'email' => 'actif@example.bf', 'password' => 'mot-de-passe-solide', 'statut' => AssureStatut::Actif,
            'journal' => [], 'armee' => 'Armée de Terre', 'region' => '1re Région Militaire',
        ]);
        // Adresse e-mail vérifiée (non attribuable en masse).
        $assure->forceFill(['email_verified_at' => now()])->save();

        return $assure;
    }

    // ---------------------------------------------------------------- inscription (assuré)

    public function test_registration_form_receives_the_custom_sections(): void
    {
        $section = $this->section('assure', 'Informations médicales');
        $this->fif()->createField($section, ['libelle' => 'Allergies connues', 'type' => 'text']);

        $this->get(route('registration.create'))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('fifSchema.0.titre', 'Informations médicales')
            ->where('fifSchema.0.fields.0.code', 'allergies_connues'));
    }

    public function test_registration_checks_and_stores_the_custom_fields(): void
    {
        $section = $this->section('assure', 'Compléments');
        $this->fif()->createField($section, ['libelle' => 'Numéro de permis', 'obligatoire' => true]);
        $this->fif()->createField($section, ['libelle' => 'Statut', 'type' => 'select', 'options' => ['Marié', 'Célibataire']]);
        $hidden = $this->fif()->createField($section, ['libelle' => 'Champ caché']);
        $this->fif()->updateField($hidden, ['libelle' => 'Champ caché', 'visible' => false]);

        // Champ obligatoire vide, choix hors liste : refusé.
        $this->post(route('registration.store'), $this->registration(['fif' => ['numero_de_permis' => '', 'statut' => 'Veuf']]))
            ->assertSessionHasErrors(['fif.numero_de_permis', 'fif.statut']);
        $this->assertSame(0, Assure::query()->count());

        $this->post(route('registration.store'), $this->registration(['fif' => [
            'numero_de_permis' => 'BF-12345', 'statut' => 'Marié', 'champ_cache' => 'doit être ignoré',
        ]]))->assertSessionHasNoErrors();

        $assure = Assure::query()->firstOrFail();
        $this->assertSame(['numero_de_permis' => 'BF-12345', 'statut' => 'Marié'], $assure->fif_donnees);
    }

    public function test_registration_without_custom_fields_is_unchanged(): void
    {
        $this->post(route('registration.store'), $this->registration())->assertSessionHasNoErrors();

        $this->assertSame([], Assure::query()->firstOrFail()->fif_donnees);
    }

    public function test_admin_sees_the_assure_custom_values_on_the_registration_detail(): void
    {
        $section = $this->section('assure', 'Compléments');
        $this->fif()->createField($section, ['libelle' => 'Numéro de permis']);
        $this->post(route('registration.store'), $this->registration(['fif' => ['numero_de_permis' => 'BF-777']]))->assertSessionHasNoErrors();
        auth('assure')->logout();

        $admin = AdminUser::query()->create([
            'nom' => 'Admin', 'prenom' => 'Test', 'email' => 'adm@cama.bf', 'password' => 'motdepasse-solide',
            'role' => AdminRole::Administrateur, 'actif' => true,
        ]);

        $this->flushSession();
        $this->actingAs($admin, 'admin')->get(route('admin.inscriptions'))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('registrations.0.fifSections.0.titre', 'Compléments')
            ->where('registrations.0.fifSections.0.rows.0.value', 'BF-777'));
    }

    // ---------------------------------------------------------------- assistant famille (conjoint, enfant)

    public function test_wizard_receives_the_custom_sections_of_each_member_type(): void
    {
        $this->fif()->createField($this->section('conjoint', 'Emploi'), ['libelle' => 'Employeur']);
        $this->fif()->createField($this->section('enfant', 'Scolarité'), ['libelle' => 'École']);

        $this->actingAs($this->activeAssure(), 'assure')->get(route('assure.ajouter-membre'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('fifSchema.conjoint.0.titre', 'Emploi')
                ->where('fifSchema.enfant.0.titre', 'Scolarité')
                ->where('fifSchema.enfant.0.fields.0.code', 'ecole'));
    }

    public function test_a_draft_keeps_the_custom_values_and_does_not_require_them(): void
    {
        $section = $this->section('enfant', 'Scolarité');
        $this->fif()->createField($section, ['libelle' => 'École', 'obligatoire' => true]);
        $this->fif()->createField($section, ['libelle' => 'Classe']);
        $assure = $this->activeAssure();

        // Brouillon : l'école (obligatoire) peut manquer ; la classe saisie est conservée.
        $this->actingAs($assure, 'assure')->post(route('assure.dossiers.draft'), ['enfants' => [[
            'nom' => 'Kaboré', 'prenom' => 'Issa', 'sexe' => 'Masculin', 'date_naissance' => '2018-03-04',
            'lien' => 'Enfant biologique', 'fif' => ['classe' => 'CE2', 'ecole' => ''],
        ]]])->assertSessionHasNoErrors();

        $dossier = $assure->dossiers()->firstOrFail();
        $this->assertSame(['ecole' => null, 'classe' => 'CE2'], $dossier->fif_donnees);

        // L'assistant rouvre le brouillon avec les valeurs saisies.
        $this->actingAs($assure, 'assure')->get(route('assure.ajouter-membre'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('initialRows.enfants.0.fif.classe', 'CE2'));
    }

    public function test_submitting_requires_the_mandatory_custom_fields_of_filled_members(): void
    {
        $section = $this->section('conjoint', 'Emploi');
        $this->fif()->createField($section, ['libelle' => 'Employeur', 'obligatoire' => true]);
        $assure = $this->activeAssure();

        $this->actingAs($assure, 'assure')->post(route('assure.dossiers.submit'), ['conjoints' => [[
            'nom' => 'Sawadogo', 'prenom' => 'Awa', 'sexe' => 'Féminin', 'date_naissance' => '1990-01-02',
            'lien' => 'Conjoint(e)', 'fif' => ['employeur' => ''],
        ]]])->assertSessionHasErrors(['conjoints.0.fif' => 'Champ(s) obligatoire(s) manquant(s) : Employeur.']);

        $this->assertSame(0, $assure->dossiers()->count());
    }

    public function test_an_empty_member_row_never_blocks_on_custom_fields(): void
    {
        $this->fif()->createField($this->section('enfant', 'Scolarité'), ['libelle' => 'École', 'obligatoire' => true]);
        $assure = $this->activeAssure();

        // Ligne ajoutée puis laissée vide : ignorée, aucune erreur sur ses champs personnalisés.
        $this->actingAs($assure, 'assure')->post(route('assure.dossiers.draft'), ['enfants' => [[
            'nom' => '', 'prenom' => '', 'fif' => ['ecole' => ''],
        ]]])->assertSessionHasNoErrors();
        $this->assertSame(0, $assure->dossiers()->count());
    }

    public function test_admin_sees_the_member_custom_values_with_labels(): void
    {
        $section = $this->section('enfant', 'Scolarité');
        $this->fif()->createField($section, ['libelle' => 'Classe']);
        $assure = $this->activeAssure();
        $this->actingAs($assure, 'assure')->post(route('assure.dossiers.draft'), ['enfants' => [[
            'nom' => 'Kaboré', 'prenom' => 'Issa', 'sexe' => 'Masculin', 'date_naissance' => '2018-03-04',
            'lien' => 'Enfant biologique', 'fif' => ['classe' => 'CE2'],
        ]]])->assertSessionHasNoErrors();

        $membre = app(\App\Services\DossierService::class)->formatMembre($assure->dossiers()->firstOrFail());

        $this->assertSame(['classe' => 'CE2'], $membre['fifDonnees']);
        $this->assertSame('Scolarité', $membre['fifSections'][0]['titre']);
        $this->assertSame(['Classe'], array_column($membre['fifSections'][0]['rows'], 'label'));
        $this->assertSame(['CE2'], array_column($membre['fifSections'][0]['rows'], 'value'));
    }
}
