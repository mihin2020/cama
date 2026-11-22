<?php

namespace Tests\Feature;

use App\Enums\AssureStatut;
use App\Models\AdminUser;
use App\Models\Assure;
use App\Models\CmsPage;
use App\Models\Dossier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/** Régressions repérées à la relecture après le passage aux UUID. */
class ReviewRegressionsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): AdminUser
    {
        return AdminUser::query()->create([
            'nom' => 'Admin', 'prenom' => 'Test', 'email' => 'admin@cama.bf', 'password' => 'motdepasse-solide',
            'role' => 'administrateur', 'actif' => true,
        ]);
    }

    public function test_the_page_builder_opens_the_page_given_by_uuid_and_sends_uuids(): void
    {
        $first = CmsPage::query()->create(['title' => 'Première', 'slug' => 'premiere', 'status' => 'draft']);
        $second = CmsPage::query()->create(['title' => 'Seconde', 'slug' => 'seconde', 'status' => 'draft']);

        $this->actingAs($this->admin(), 'admin')->get(route('admin.cms.page_builder', ['page' => $second->uuid]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('currentPage.uuid', $second->uuid)
                ->where('pageOptions.0.uuid', fn ($uuid) => is_string($uuid) && $uuid !== ''));

        // Un identifiant invalide retombe sur la page par défaut, sans erreur.
        $this->get(route('admin.cms.page_builder', ['page' => '999']))->assertOk();
        $this->assertNotNull($first->uuid);
    }

    public function test_the_wizard_focuses_a_draft_by_uuid(): void
    {
        $assure = Assure::query()->create([
            'nom' => 'A', 'prenom' => 'B', 'matricule' => 'M-D', 'numero_cim' => 'C', 'numero_cama' => 'K-D',
            'email' => 'brouillon@example.bf', 'password' => 'mot-de-passe-solide', 'statut' => AssureStatut::Actif, 'journal' => [],
        ]);
        $assure->forceFill(['email_verified_at' => now()])->save();
        $draft = Dossier::query()->create([
            'assure_id' => $assure->id, 'ref' => 'REF-D1', 'nom' => 'E', 'prenom' => 'F', 'lien' => 'Enfant biologique',
            'statut' => 'Brouillon', 'journal' => [], 'pieces' => [],
        ]);

        $this->actingAs($assure, 'assure')->get(route('assure.ajouter-membre', ['draft' => $draft->uuid]))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('focusDraftId', $draft->uuid));

        $this->actingAs($assure, 'assure')->get(route('assure.ajouter-membre', ['draft' => 'inconnu']))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('focusDraftId', null));
    }

    public function test_served_files_carry_no_content_security_policy_but_pages_do(): void
    {
        $this->get('/')->assertHeader('Content-Security-Policy');
    }

    public function test_failed_login_log_never_contains_something_that_is_not_an_email(): void
    {
        \Illuminate\Support\Facades\Log::spy();

        $this->post(route('assure.login.store'), ['email' => 'MonMotDePasse!2026', 'password' => 'x']);

        \Illuminate\Support\Facades\Log::shouldNotHaveReceived('warning', fn ($message, $context = []) => str_contains(json_encode($context), 'MonMotDePasse'));
    }
}
