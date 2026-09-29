<?php

use App\Models\CmsPartner;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $antennas = [
            [
                'name' => 'Siège CAMA Ouagadougou',
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
            ],
            [
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
            ],
        ];

        foreach ($antennas as $antenna) {
            CmsPartner::query()->firstOrCreate(
                ['name' => $antenna['name']],
                $antenna,
            );
        }
    }

    public function down(): void
    {
        CmsPartner::query()->whereIn('name', [
            'Siège CAMA Ouagadougou',
            'Délégation régionale Bobo-Dioulasso',
        ])->delete();
    }
};
