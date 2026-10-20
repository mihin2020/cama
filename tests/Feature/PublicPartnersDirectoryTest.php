<?php

namespace Tests\Feature;

use App\Models\CmsPartner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPartnersDirectoryTest extends TestCase
{
    use RefreshDatabase;

    private function seedPartners(): void
    {
        CmsPartner::query()->delete();
        CmsPartner::query()->create(['name' => 'Siège CAMA', 'type' => 'Siège CAMA', 'city' => 'Ouagadougou', 'latitude' => 12.37, 'longitude' => -1.52, 'published' => true, 'sort_order' => 1]);
        CmsPartner::query()->create(['name' => 'Pharmacie Localisée', 'type' => 'Partenaire', 'city' => 'Gaoua', 'latitude' => 10.33, 'longitude' => -3.17, 'published' => true, 'sort_order' => 2]);
        CmsPartner::query()->create(['name' => 'Pharmacie du Poni', 'type' => 'Partenaire', 'city' => 'Gaoua', 'published' => true, 'sort_order' => 3]);
    }

    private function props(string $url): array
    {
        return $this->get($url)->assertOk()->viewData('page')['props'];
    }

    public function test_provider_without_coordinates_is_listed_on_contact_like_the_others(): void
    {
        $this->seedPartners();

        $locations = collect($this->props('/contact')['locations'])->keyBy('name');

        $this->assertTrue($locations->has('Pharmacie du Poni'), 'Une fiche sans coordonnées ne doit pas disparaître de la liste.');
        $this->assertNull($locations['Pharmacie du Poni']['lat']);
        // Itinéraire disponible quand même : recherche Google par nom et ville.
        $this->assertStringContainsString(rawurlencode('Pharmacie du Poni, Gaoua, Burkina Faso'), $locations['Pharmacie du Poni']['mapsUrl']);
        $this->assertStringContainsString('10.33', $locations['Pharmacie Localisée']['mapsUrl']);
    }

    public function test_services_page_lists_providers_only_each_with_a_route_link(): void
    {
        $this->seedPartners();

        $partners = collect($this->props('/services')['partners']);

        $this->assertSame(['Pharmacie Localisée', 'Pharmacie du Poni'], $partners->pluck('name')->sort()->values()->all());
        $this->assertTrue($partners->every(fn ($partner) => filled($partner['mapsUrl'])));
    }
}
