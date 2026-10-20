<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Les mentions légales quittent le page builder pour un module dédié du studio de contenu
 * (paramètre « legal_notice », cf. PlatformSettingsService::legalNotice).
 *
 * Contenu repris seulement si la page était publiée : un brouillon n'était pas visible en
 * ligne, le site continue donc d'afficher exactement le même texte.
 */
return new class extends Migration
{
    public function up(): void
    {
        $page = DB::table('cms_pages')->where('slug', 'mention_legales')->first();
        if (! $page) {
            return;
        }

        $alreadyMoved = DB::table('platform_settings')->where('key', 'legal_notice')->exists();
        if ($page->status === 'published' && ! $alreadyMoved) {
            $content = $this->legalContent(json_decode((string) $page->sections_json, true) ?: []);
            if ($content) {
                DB::table('platform_settings')->insert([
                    'key' => 'legal_notice',
                    'value' => json_encode([
                        'title' => (string) ($content['title'] ?? ''),
                        'intro' => (string) ($content['intro'] ?? ''),
                        'articles' => array_values($content['articles'] ?? []),
                        'updatedAt' => now()->toIso8601String(),
                    ], JSON_UNESCAPED_UNICODE),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Un lien de menu vers la page devient un lien direct vers l'adresse publique.
        DB::table('cms_menu_items')->where('page_id', $page->id)
            ->update(['type' => 'custom', 'page_id' => null, 'url' => '/mention_legales']);

        DB::table('cms_page_versions')->where('cms_page_id', $page->id)->delete();
        DB::table('cms_pages')->where('id', $page->id)->delete();
    }

    public function down(): void
    {
        // Le module reste la source du contenu ; la page n'est pas recréée dans le page builder.
    }

    /**
     * @param  array<int, mixed>  $sections
     * @return array<string, mixed>|null
     */
    private function legalContent(array $sections): ?array
    {
        foreach ($sections as $section) {
            foreach ($section['columns'] ?? [] as $column) {
                foreach ($column['widgets'] ?? [] as $widget) {
                    if (($widget['type'] ?? null) === 'legal_page' && is_array($widget['content'] ?? null)) {
                        return $widget['content'];
                    }
                }
            }
        }

        return null;
    }
};
