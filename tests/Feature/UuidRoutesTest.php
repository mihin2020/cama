<?php

namespace Tests\Feature;

use App\Enums\AssureStatut;
use App\Models\AdminUser;
use App\Models\Assure;
use App\Models\Dossier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/** Les adresses n'exposent jamais le numéro séquentiel : un UUID, impossible à deviner. */
class UuidRoutesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): AdminUser
    {
        return AdminUser::query()->create([
            'nom' => 'Agent', 'prenom' => 'Test', 'email' => 'agent@cama.bf', 'password' => 'motdepasse-solide',
            'role' => 'administrateur', 'actif' => true,
        ]);
    }

    private function dossier(): Dossier
    {
        $assure = Assure::query()->create([
            'nom' => 'A', 'prenom' => 'B', 'matricule' => 'M-1', 'numero_cim' => 'C', 'numero_cama' => 'K',
            'email' => 'a@example.bf', 'password' => 'mot-de-passe-solide', 'statut' => AssureStatut::Actif, 'journal' => [],
        ]);

        return Dossier::query()->create([
            'assure_id' => $assure->id, 'ref' => 'REF-U1', 'nom' => 'E', 'prenom' => 'F', 'lien' => 'Enfant biologique',
            'statut' => 'Soumis', 'journal' => [], 'pieces' => [],
        ]);
    }

    public function test_every_new_record_gets_a_uuid(): void
    {
        $dossier = $this->dossier();

        $this->assertTrue(Str::isUuid($dossier->uuid));
        $this->assertTrue(Str::isUuid($dossier->assure->uuid));
        $this->assertSame('uuid', $dossier->getRouteKeyName());
    }

    public function test_urls_carry_the_uuid_and_the_sequential_id_is_refused(): void
    {
        $dossier = $this->dossier();
        $admin = $this->admin();

        $this->assertStringContainsString($dossier->uuid, route('admin.dossiers.piece.download', $dossier));
        $this->assertStringNotContainsString('/dossiers/'.$dossier->id.'/', route('admin.dossiers.piece.download', $dossier));

        // Avec le numéro séquentiel, la ressource est introuvable.
        $this->actingAs($admin, 'admin')
            ->get('/admin/dossiers/'.$dossier->id.'/pieces/telecharger?type=Photo')
            ->assertNotFound();
    }

    public function test_the_screens_send_the_uuid_to_the_front(): void
    {
        $dossier = $this->dossier();

        $this->actingAs($this->admin(), 'admin')->get(route('admin.dossiers'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('families.0.dossiers.0.uuid', $dossier->uuid));
    }
}
