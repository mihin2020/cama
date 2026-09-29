<?php

use App\Models\CmsPartner;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Aligne les coordonnées officielles de la CAMA (siège + délégation régionale)
     * sur les données réelles et retire les antennes fictives.
     */
    public function up(): void
    {
        // Siège CAMA Ouagadougou
        CmsPartner::query()->where('name', 'Siège CAMA Ouagadougou')->update([
            'type' => 'Siège CAMA',
            'city' => 'Ouagadougou',
            'address' => 'Ouagadougou, Bilbalogho, Avenue de la Nation, côté Ouest de la Direction de la Justice Militaire',
            'hours' => 'Lundi au vendredi : 07h30 - 16h00',
            'phone' => '+226 25 30 81 03 / +226 70 76 29 54',
            'email' => 'Cama_bf@gmail.com',
            'latitude' => 12.3697,
            'longitude' => -1.5262,
            'maps_url' => 'https://www.google.com/maps/search/?api=1&query=9F9F%2BQCP+Ouagadougou',
            'sort_order' => 1,
            'published' => true,
        ]);

        // Délégation régionale Bobo-Dioulasso (ex « Antenne Bobo-Dioulasso »)
        $bobo = CmsPartner::query()
            ->whereIn('name', ['Délégation régionale Bobo-Dioulasso', 'Antenne Bobo-Dioulasso'])
            ->first();

        $boboData = [
            'name' => 'Délégation régionale Bobo-Dioulasso',
            'type' => 'Point Focal CAMA',
            'city' => 'Bobo-Dioulasso',
            'address' => 'Point Focal Bobo-Dioulasso, Camp Ouezzin Coulibaly, Bobo-Dioulasso',
            'hours' => 'Lundi au vendredi : 07h30 - 16h00',
            'phone' => '+226 25 30 81 03 / +226 70 76 29 54',
            'email' => 'Cama_bf@gmail.com',
            'latitude' => 11.1771,
            'longitude' => -4.2979,
            'maps_url' => 'https://www.google.com/maps/search/?api=1&query=Camp+Ouezzin+Coulibaly+Bobo-Dioulasso',
            'sort_order' => 2,
            'published' => true,
        ];

        if ($bobo) {
            $bobo->update($boboData);
        } else {
            CmsPartner::query()->create($boboData);
        }

        // Suppression des antennes fictives
        CmsPartner::query()->whereIn('name', [
            'Antenne Ouahigouya',
            "Antenne Fada N'Gourma",
        ])->delete();
    }

    public function down(): void
    {
        // Migration corrective : pas de retour arrière destructif.
    }
};
