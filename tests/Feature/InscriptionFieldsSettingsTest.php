<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\AdminUser;
use App\Models\Assure;
use App\Services\PlatformSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class InscriptionFieldsSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): AdminUser
    {
        return AdminUser::query()->firstOrCreate(['email' => 'tech@cama.bf'], [
            'nom' => 'Admin', 'prenom' => 'Technique', 'password' => 'motdepasse-solide',
            'role' => AdminRole::Administrateur, 'actif' => true,
        ]);
    }

    private function fields(array $overrides): array
    {
        return array_map(function (array $field) use ($overrides) {
            return ['key' => $field['key']] + ($overrides[$field['key']] ?? [
                'actif' => $field['actif'],
                'required' => $field['required'],
            ]);
        }, PlatformSettingsService::INSCRIPTION_FIELDS);
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

    public function test_admin_can_configure_fields_and_children_follow_their_parent(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.parametres.inscription-fields'), ['fields' => $this->fields([
                'region' => ['actif' => false, 'required' => true],
                'corps' => ['actif' => true, 'required' => true],
                'numero_iup' => ['actif' => true, 'required' => true],
            ])])
            ->assertSessionHasNoErrors();

        $map = app(PlatformSettingsService::class)->inscriptionFieldsMap();

        $this->assertFalse($map['region']['actif']);
        $this->assertFalse($map['region']['required']);
        $this->assertFalse($map['corps']['actif']);
        $this->assertFalse($map['sous_section']['actif']);
        $this->assertTrue($map['numero_iup']['required']);

        $this->get(route('registration.create'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('inscriptionFields.numero_iup.required', true)
                ->where('inscriptionFields.region.actif', false));
    }

    public function test_registration_respects_hidden_and_required_fields(): void
    {
        app(PlatformSettingsService::class)->updateInscriptionFields($this->fields([
            'numero_cim' => ['actif' => false, 'required' => false],
            'numero_iup' => ['actif' => true, 'required' => true],
            'telephone' => ['actif' => true, 'required' => false],
        ]));

        // Champ devenu obligatoire : refusé s'il manque.
        $this->post(route('registration.store'), $this->registration())
            ->assertSessionHasErrors('numero_iup');

        // Champ masqué ignoré, champ devenu facultatif accepté vide.
        $this->post(route('registration.store'), $this->registration([
            'numero_iup' => 'IUP-9', 'numero_cim' => 'IGNORE', 'telephone' => '',
        ]))->assertSessionHasNoErrors();

        $assure = Assure::query()->where('email', 'awa@example.bf')->firstOrFail();
        $this->assertNull($assure->numero_cim);
        $this->assertSame('IUP-9', $assure->numero_iup);
        $this->assertNull($assure->telephone);
    }

    public function test_matricule_can_be_hidden_or_made_optional(): void
    {
        $settings = app(PlatformSettingsService::class);

        $settings->updateInscriptionFields($this->fields(['matricule' => ['actif' => false, 'required' => false]]));
        $this->post(route('registration.store'), $this->registration(['matricule' => 'IGNORE']))
            ->assertSessionHasNoErrors();
        $this->assertNull(Assure::query()->where('email', 'awa@example.bf')->value('matricule'));

        $this->flushSession();
        $this->app['auth']->forgetGuards();

        $settings->updateInscriptionFields($this->fields(['matricule' => ['actif' => true, 'required' => false]]));
        $this->post(route('registration.store'), $this->registration(['matricule' => '', 'email' => 'deux@example.bf']))
            ->assertSessionHasNoErrors();
        $this->assertSame(2, Assure::query()->whereNull('matricule')->count());
    }

    public function test_cim_is_optional_at_registration_by_default(): void
    {
        $this->post(route('registration.store'), $this->registration(['numero_cim' => '']))
            ->assertSessionHasNoErrors();

        $this->assertNull(Assure::query()->where('email', 'awa@example.bf')->value('numero_cim'));
    }

    public function test_cim_can_never_be_made_mandatory(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.parametres.inscription-fields'), ['fields' => $this->fields([
                'numero_cim' => ['actif' => true, 'required' => true],
            ])])
            ->assertSessionHasNoErrors();

        $map = app(PlatformSettingsService::class)->inscriptionFieldsMap();
        $this->assertTrue($map['numero_cim']['actif']);
        $this->assertFalse($map['numero_cim']['required']);

        $this->flushSession();
        $this->post(route('registration.store'), $this->registration(['numero_cim' => '']))
            ->assertSessionHasNoErrors();
    }

    public function test_registration_is_validated_without_cim_but_not_without_cama_card(): void
    {
        $this->post(route('registration.store'), $this->registration(['numero_cim' => '']))->assertSessionHasNoErrors();
        $assure = Assure::query()->where('email', 'awa@example.bf')->firstOrFail();
        $admin = $this->admin();

        // Sans N° Carte CAMA : validation refusée.
        $assure->update(['numero_cama' => '']);
        $this->actingAs($admin, 'admin')
            ->post(route('admin.inscriptions.validate', $assure))
            ->assertSessionHasErrors(['numero_cama' => 'Le N° Carte CAMA doit être renseigné avant validation.']);

        // Le gestionnaire renseigne la carte et laisse le CIM vide : accepté, puis validé.
        $this->actingAs($admin, 'admin')
            ->patch(route('admin.inscriptions.identifiers', $assure), ['numero_cim' => '', 'numero_cama' => 'CAMA-77'])
            ->assertSessionHasNoErrors();
        $this->assertNull($assure->fresh()->numero_cim);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.inscriptions.validate', $assure))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Compte validé (Carte CAMA : CAMA-77).');
        $this->assertSame(\App\Enums\AssureStatut::Actif, $assure->fresh()->statut);
    }
}
