<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\AdminUser;
use App\Models\FifField;
use App\Models\FifSection;
use App\Services\FifSchemaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class FifFormBuilderTest extends TestCase
{
    use RefreshDatabase;

    private function admin(?array $permissions = null, AdminRole $role = AdminRole::Administrateur, string $email = 'fif@cama.bf'): AdminUser
    {
        return AdminUser::query()->create([
            'nom' => 'Fif', 'prenom' => 'Admin', 'email' => $email, 'password' => 'motdepasse-solide',
            'role' => $role, 'actif' => true, 'permissions' => $permissions,
        ]);
    }

    private function service(): FifSchemaService
    {
        return app(FifSchemaService::class);
    }

    private function section(string $cible = 'enfant', string $titre = 'Informations médicales'): FifSection
    {
        return $this->service()->createSection($cible, ['titre' => $titre]);
    }

    private function field(FifSection $section, array $data = []): FifField
    {
        return $this->service()->createField($section, array_merge(['libelle' => 'Allergies connues', 'type' => 'text'], $data));
    }

    public function test_sections_and_fields_are_created_per_target_and_returned_in_order(): void
    {
        $enfant = $this->section('enfant');
        $this->field($enfant, ['libelle' => 'Allergies']);
        $this->field($enfant, ['libelle' => 'Groupe de vaccination']);
        $this->section('conjoint', 'Emploi');

        $this->assertSame(['Allergies', 'Groupe de vaccination'], array_column($this->service()->fields('enfant'), 'libelle'));
        // Une section sans champ visible n'est pas proposée.
        $this->assertSame([], $this->service()->schema('conjoint'));
        $this->assertSame([], $this->service()->schema('assure'));
        $this->assertSame('Emploi', $this->service()->adminSchema('conjoint')[0]['titre']);
    }

    public function test_hidden_fields_and_sections_are_not_proposed_but_stay_in_the_admin_view(): void
    {
        $section = $this->section();
        $kept = $this->field($section, ['libelle' => 'Visible']);
        $hidden = $this->field($section, ['libelle' => 'Masqué']);
        $this->service()->updateField($hidden, ['libelle' => 'Masqué', 'visible' => false]);

        $this->assertSame(['Visible'], array_column($this->service()->fields('enfant'), 'libelle'));
        $this->assertCount(2, $this->service()->adminSchema('enfant')[0]['fields']);

        $this->service()->updateSection($section, ['titre' => $section->titre, 'visible' => false]);
        $this->assertSame([], $this->service()->schema('enfant'));
        $this->assertCount(1, $this->service()->adminSchema('enfant'));
        $this->assertTrue($kept->fresh()->visible);
    }

    public function test_deleting_is_soft_so_entered_data_is_never_lost(): void
    {
        $section = $this->section();
        $field = $this->field($section);

        $this->service()->deleteField($field);
        $this->assertSame([], $this->service()->fields('enfant'));
        $this->assertNotNull(FifField::withTrashed()->find($field->id));

        $this->service()->deleteSection($section);
        $this->assertSame([], $this->service()->adminSchema('enfant'));
        $this->assertNotNull(FifSection::withTrashed()->find($section->id));
    }

    public function test_a_field_code_is_stable_unique_and_never_reused(): void
    {
        $section = $this->section();
        $first = $this->field($section, ['libelle' => 'Numéro de permis']);
        $second = $this->field($section, ['libelle' => 'Numéro de permis']);

        $this->assertSame('numero_de_permis', $first->code);
        $this->assertSame('numero_de_permis_2', $second->code);

        // Renommer ne change pas le code : les valeurs déjà saisies restent rattachées.
        $this->service()->updateField($first, ['libelle' => 'Permis de conduire']);
        $this->assertSame('numero_de_permis', $first->fresh()->code);

        // Un code supprimé n'est pas réattribué.
        $this->service()->deleteField($second);
        $third = $this->field($section, ['libelle' => 'Numéro de permis']);
        $this->assertSame('numero_de_permis_3', $third->code);
    }

    public function test_fields_and_sections_can_be_reordered(): void
    {
        $a = $this->section('enfant', 'A');
        $b = $this->section('enfant', 'B');
        $this->field($a, ['libelle' => 'F1']);
        $f2 = $this->field($a, ['libelle' => 'F2']);

        $this->service()->moveSection($b, 'up');
        $this->assertSame(['B', 'A'], array_column($this->service()->adminSchema('enfant'), 'titre'));

        $this->service()->moveField($f2, 'up');
        $this->assertSame(['F2', 'F1'], array_column($this->service()->adminSchema('enfant')[1]['fields'], 'libelle'));

        // Aux extrémités, rien ne bouge.
        $this->service()->moveSection($b, 'up');
        $this->assertSame(['B', 'A'], array_column($this->service()->adminSchema('enfant'), 'titre'));
    }

    public function test_choice_fields_need_at_least_two_options(): void
    {
        $section = $this->section();

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->field($section, ['libelle' => 'Groupe', 'type' => 'select', 'options' => ['A']]);
    }

    public function test_validation_rules_follow_the_field_definitions(): void
    {
        $section = $this->section('conjoint', 'Complément');
        $this->field($section, ['libelle' => 'Profession', 'obligatoire' => true]);
        $this->field($section, ['libelle' => 'Taille', 'type' => 'number']);
        $this->field($section, ['libelle' => 'Statut', 'type' => 'select', 'options' => ['Marié', 'Divorcé']]);
        $this->field($section, ['libelle' => 'Je certifie', 'type' => 'checkbox', 'obligatoire' => true]);
        $this->field($section, ['libelle' => 'Né le', 'type' => 'date']);
        $this->field($section, ['libelle' => 'Contact', 'type' => 'email']);

        $rules = $this->service()->rules('conjoint', 'fif');
        $passes = fn (array $values) => Validator::make(['fif' => $values], $rules)->passes();

        $ok = ['profession' => 'Médecin', 'taille' => '175', 'statut' => 'Marié', 'je_certifie' => true, 'ne_le' => '1990-05-12', 'contact' => 'a@b.bf'];
        $this->assertTrue($passes($ok));
        $this->assertFalse($passes([...$ok, 'profession' => '']), 'Champ obligatoire vide');
        $this->assertFalse($passes([...$ok, 'taille' => 'grand']), 'Nombre invalide');
        $this->assertFalse($passes([...$ok, 'statut' => 'Veuf']), 'Choix hors liste');
        $this->assertFalse($passes([...$ok, 'je_certifie' => false]), 'Case obligatoire non cochée');
        $this->assertFalse($passes([...$ok, 'contact' => 'pas-un-email']));
        $this->assertTrue($passes([...$ok, 'taille' => null, 'ne_le' => null, 'contact' => null]), 'Champs facultatifs vides');
    }

    public function test_clean_keeps_only_visible_fields_in_their_type(): void
    {
        $section = $this->section();
        $this->field($section, ['libelle' => 'Taille', 'type' => 'number']);
        $this->field($section, ['libelle' => 'Appareillé', 'type' => 'checkbox']);
        $secret = $this->field($section, ['libelle' => 'Caché']);
        $this->service()->updateField($secret, ['libelle' => 'Caché', 'visible' => false]);

        $clean = $this->service()->clean('enfant', ['taille' => '12.5', 'appareille' => '1', 'cache' => 'ne pas garder', 'inconnu' => 'x']);

        $this->assertSame(['taille' => 12.5, 'appareille' => true], $clean);
    }

    public function test_display_formats_values_for_the_admin_screens_and_the_pdf(): void
    {
        $section = $this->section('assure', 'Complément');
        $this->field($section, ['libelle' => 'Né le', 'type' => 'date']);
        $this->field($section, ['libelle' => 'Permis', 'type' => 'checkbox']);
        $this->field($section, ['libelle' => 'Remarque', 'imprimer_pdf' => false]);

        $display = $this->service()->display('assure', ['ne_le' => '1990-05-12', 'permis' => true, 'remarque' => 'RAS']);

        $this->assertSame('Complément', $display[0]['titre']);
        $this->assertSame(['12/05/1990', 'Oui', 'RAS'], array_column($display[0]['rows'], 'value'));
        $this->assertSame([true, true, false], array_column($display[0]['rows'], 'imprimer'));
    }

    // ---------------------------------------------------------------- écran d'administration

    public function test_admin_builds_the_form_through_the_screen(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.fif.sections.store', 'enfant'), ['titre' => 'Santé', 'description' => 'Suivi médical'])
            ->assertSessionHasNoErrors();
        $section = FifSection::query()->firstOrFail();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.fif.fields.store', $section), ['libelle' => 'Allergies', 'type' => 'textarea', 'obligatoire' => true])
            ->assertSessionHasNoErrors();
        $field = FifField::query()->firstOrFail();
        $this->assertTrue($field->obligatoire);

        $this->actingAs($admin, 'admin')
            ->put(route('admin.fif.fields.update', $field), ['libelle' => 'Allergies connues', 'type' => 'textarea', 'visible' => false])
            ->assertSessionHasNoErrors();
        $this->assertSame('Allergies connues', $field->fresh()->libelle);
        $this->assertFalse($field->fresh()->visible);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.fif'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Fif/Index')
                ->has('cibles', 3)
                ->where('cibles.2.cible', 'enfant')
                ->where('cibles.2.sections.0.titre', 'Santé')
                ->where('cibles.2.sections.0.fields.0.visible', false));

        $this->actingAs($admin, 'admin')->delete(route('admin.fif.sections.destroy', $section))->assertSessionHasNoErrors();
        $this->assertSame([], $this->service()->adminSchema('enfant'));
    }

    public function test_the_screen_refuses_invalid_input(): void
    {
        $admin = $this->admin();
        $section = $this->section();

        $this->actingAs($admin, 'admin')->post(route('admin.fif.sections.store', 'enfant'), ['titre' => ''])->assertSessionHasErrors('titre');
        $this->actingAs($admin, 'admin')->post(route('admin.fif.sections.store', 'inconnu'), ['titre' => 'X'])->assertNotFound();
        $this->actingAs($admin, 'admin')->post(route('admin.fif.fields.store', $section), ['libelle' => 'X', 'type' => 'piratage'])->assertSessionHasErrors('type');
        $this->actingAs($admin, 'admin')->post(route('admin.fif.fields.store', $section), ['libelle' => 'X', 'type' => 'select', 'options' => ['A']])->assertSessionHasErrors('options');
    }

    public function test_only_accounts_with_the_fif_permission_reach_the_builder(): void
    {
        $gestionnaire = $this->admin(['dossiers.view'], AdminRole::Gestionnaire, 'gest@cama.bf');

        $this->actingAs($gestionnaire, 'admin')->get(route('admin.fif'))->assertForbidden();
        $this->actingAs($gestionnaire, 'admin')->post(route('admin.fif.sections.store', 'enfant'), ['titre' => 'X'])->assertForbidden();
        $this->assertSame(0, FifSection::query()->count());

        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $permitted = $this->admin(['fif.manage'], AdminRole::Gestionnaire, 'ok@cama.bf');
        $this->actingAs($permitted, 'admin')->get(route('admin.fif'))->assertOk();
    }
}
