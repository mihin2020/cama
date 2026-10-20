<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\AdminUser;
use App\Models\CmsPartner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPartnersPaginationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): AdminUser
    {
        return AdminUser::query()->firstOrCreate(['email' => 'cms@cama.bf'], [
            'nom' => 'Admin', 'prenom' => 'Cms',
            'password' => 'motdepasse-solide', 'role' => AdminRole::Administrateur, 'actif' => true,
        ]);
    }

    private function seedPartners(): void
    {
        // Les migrations importent déjà les prestataires réels : on part d'une table vide.
        CmsPartner::query()->delete();

        foreach (range(1, 45) as $n) {
            CmsPartner::query()->create([
                'name' => "Pharmacie {$n}", 'type' => 'Partenaire', 'city' => $n <= 5 ? 'Kaya' : 'Ouagadougou',
                'latitude' => $n % 3 ? 12.3 : null, 'longitude' => $n % 3 ? -1.5 : null,
                'sort_order' => $n, 'published' => true,
            ]);
        }
    }

    private function props(string $query = ''): array
    {
        return $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.cms.partenaires').$query)
            ->assertOk()
            ->viewData('page')['props'];
    }

    public function test_list_is_paginated_and_map_keeps_every_located_point(): void
    {
        $this->seedPartners();

        $props = $this->props('?page=3');

        $this->assertCount(5, $props['items']);
        $this->assertSame(['currentPage' => 3, 'lastPage' => 3, 'perPage' => 20, 'total' => 45], $props['pagination']);
        // La carte montre tous les points géolocalisés, pas seulement ceux de la page.
        $this->assertCount(30, $props['mapPoints']);
    }

    public function test_search_filter_and_page_size_are_applied_on_the_server(): void
    {
        $this->seedPartners();

        $this->assertSame(5, $this->props('?q=Kaya')['pagination']['total']);
        $this->assertSame(15, $this->props('?type='.urlencode('À localiser'))['pagination']['total']);
        $this->assertCount(45, $this->props('?per_page=50')['items']);
        // Taille non proposée : on revient à la valeur par défaut.
        $this->assertSame(20, $this->props('?per_page=5000')['pagination']['perPage']);
    }

    public function test_page_beyond_the_last_one_redirects_to_the_last_page(): void
    {
        $this->seedPartners();

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.cms.partenaires', ['page' => 9]))
            ->assertRedirect(route('admin.cms.partenaires', ['page' => 3]));
    }
}
