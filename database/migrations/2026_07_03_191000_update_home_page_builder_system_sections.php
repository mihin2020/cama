<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $page = DB::table('cms_pages')->where('slug', 'accueil')->first();

        if (! $page) {
            return;
        }

        $sections = json_decode($page->sections_json ?? '[]', true);

        if (! is_array($sections) || ! $this->isStarterHomeSection((string) $page->title, $sections)) {
            return;
        }

        DB::table('cms_pages')
            ->where('id', $page->id)
            ->update([
                'sections_json' => json_encode($this->homeSystemSections(), JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        //
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     */
    private function isStarterHomeSection(string $title, array $sections): bool
    {
        if (count($sections) !== 1) {
            return false;
        }

        $columns = $sections[0]['columns'] ?? [];
        $widgets = $columns[0]['widgets'] ?? [];

        return count($columns) === 1
            && count($widgets) === 2
            && ($widgets[0]['type'] ?? null) === 'heading'
            && ($widgets[1]['type'] ?? null) === 'text'
            && trim((string) ($widgets[0]['content']['text'] ?? '')) === $title;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function homeSystemSections(): array
    {
        return [
            $this->systemSection('home_hero', [
                'badgeIcon' => 'health_and_safety',
                'badgeText' => 'CAMA Burkina Faso',
                'secondaryLabel' => 'Espace assuré',
                'secondaryHref' => '/espace-assure/connexion',
            ]),
            $this->systemSection('home_pillars', [
                'title' => 'Nos Piliers',
                'items' => [
                    [
                        'icon' => 'medical_services',
                        'title' => 'Accès',
                        'text' => 'Garantir à chaque service membre un accès immédiat à un vaste réseau de prestataires de santé agréés sur l’ensemble du territoire national.',
                    ],
                    [
                        'icon' => 'diversity_3',
                        'title' => 'Solidarité',
                        'text' => 'Un système mutualisé où la force du collectif protège l’individu, assurant une prise en charge équitable pour tous les ayants droit.',
                    ],
                    [
                        'icon' => 'visibility',
                        'title' => 'Transparence',
                        'text' => 'Une gestion rigoureuse et éthique des cotisations, avec des processus clairs pour les remboursements et la gestion des droits.',
                    ],
                ],
            ]),
            $this->systemSection('home_key_figures', [
                'eyebrow' => 'En Chiffres',
                'title' => "La CAMA aujourd'hui",
            ]),
            $this->systemSection('home_latest_articles', [
                'eyebrow' => 'Informations',
                'title' => 'Dernières Actualités',
                'linkLabel' => 'Voir tout le flux',
                'linkHref' => '/actualites',
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    private function systemSection(string $widgetType, array $content): array
    {
        return [
            'type' => 'section',
            'settings' => [
                'contentWidth' => 'full',
                'bgColor' => 'transparent',
                'padding' => ['t' => 0, 'r' => 0, 'b' => 0, 'l' => 0],
            ],
            'columns' => [[
                'type' => 'column',
                'width' => 100,
                'settings' => ['padding' => ['t' => 0, 'r' => 0, 'b' => 0, 'l' => 0]],
                'widgets' => [[
                    'type' => $widgetType,
                    'content' => $content,
                    'style' => [],
                ]],
            ]],
        ];
    }
};
