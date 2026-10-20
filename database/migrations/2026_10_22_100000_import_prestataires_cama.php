<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Prestataires de soins CAMA (fichier « prestataire+localisation »), localisés à leur adresse réelle
 * (Google Places / OpenStreetMap, chaque correspondance vérifiée : nom et ville concordants).
 * Les fiches non localisées avec certitude sont importées sans coordonnées : elles apparaissent
 * dans l'admin sous « Sans position » et ne sont pas placées sur la carte.
 */
return new class extends Migration
{
    private const SOURCE = 'database/data/prestataires-cama.json';

    public function up(): void
    {
        $items = json_decode(file_get_contents(base_path(self::SOURCE)), true, flags: JSON_THROW_ON_ERROR);
        $order = (int) DB::table('cms_partners')->max('sort_order');
        $now = now();

        foreach ($items as $item) {
            $exists = DB::table('cms_partners')
                ->where('name', $item['name'])
                ->where('city', $item['city'])
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('cms_partners')->insert([
                'name' => $item['name'],
                'type' => $item['type'],
                'city' => $item['city'],
                'address' => $item['address'],
                'description' => $item['description'],
                'latitude' => $item['latitude'],
                'longitude' => $item['longitude'],
                'maps_url' => $item['maps_url'],
                'sort_order' => ++$order,
                'published' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        $items = json_decode(file_get_contents(base_path(self::SOURCE)), true, flags: JSON_THROW_ON_ERROR);

        foreach ($items as $item) {
            DB::table('cms_partners')->where('name', $item['name'])->where('city', $item['city'])->delete();
        }
    }
};
