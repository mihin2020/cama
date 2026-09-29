<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $pages = [
            ['title' => 'Accueil', 'slug' => 'accueil', 'subtitle' => 'La Caisse d\'Assurance Maladie des Armées au service des familles militaires.'],
            ['title' => 'À propos', 'slug' => 'apropos', 'subtitle' => 'Notre mission, notre histoire et nos engagements.'],
            ['title' => 'Services et prestations', 'slug' => 'services', 'subtitle' => 'Découvrez l\'ensemble de nos prestations.'],
            ['title' => 'Ressources', 'slug' => 'ressources', 'subtitle' => 'Formulaires, guides, attestations et textes de référence.'],
            ['title' => 'Actualités', 'slug' => 'actualites', 'subtitle' => 'Suivez les dernières évolutions de la CAMA.'],
            ['title' => 'Contact', 'slug' => 'contact', 'subtitle' => 'Nos équipes vous répondent du lundi au vendredi.'],
            ['title' => 'Mentions légales', 'slug' => 'mention_legales', 'subtitle' => 'Informations légales du site CAMA.'],
            ['title' => 'Accessibilité', 'slug' => 'accessibilite', 'subtitle' => 'Déclaration d\'accessibilité du site CAMA.'],
        ];

        foreach ($pages as $page) {
            if (DB::table('cms_pages')->where('slug', $page['slug'])->exists()) {
                continue;
            }

            DB::table('cms_pages')->insert([
                'title' => $page['title'],
                'slug' => $page['slug'],
                'status' => $page['slug'] === 'mention_legales' ? 'draft' : 'published',
                'sections_json' => json_encode($this->starterSections($page['title'], $page['subtitle']), JSON_UNESCAPED_UNICODE),
                'author_id' => null,
                'published_at' => $page['slug'] === 'mention_legales' ? null : $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('cms_pages')
            ->whereIn('slug', ['ressources', 'actualites', 'accessibilite'])
            ->whereNull('author_id')
            ->delete();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function starterSections(string $title, string $subtitle): array
    {
        return [[
            'uid' => (string) Str::uuid(),
            'type' => 'section',
            'settings' => ['bgColor' => 'transparent', 'padding' => ['t' => 0, 'r' => 0, 'b' => 0, 'l' => 0]],
            'columns' => [[
                'uid' => (string) Str::uuid(),
                'type' => 'column',
                'width' => 100,
                'settings' => ['padding' => ['t' => 0, 'r' => 0, 'b' => 0, 'l' => 0]],
                'widgets' => [
                    ['uid' => (string) Str::uuid(), 'type' => 'heading', 'content' => ['text' => $title, 'tag' => 'h1']],
                    ['uid' => (string) Str::uuid(), 'type' => 'text', 'content' => ['html' => $subtitle]],
                ],
            ]],
        ]];
    }
};
