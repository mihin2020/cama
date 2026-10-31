<?php

use App\Services\PublicSiteService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Contenu CMS déjà en base : 17 régions couvertes, taux de prise en charge
 * unique de 80 % sur la page Services, et boutons d'appel (« Nous appeler »,
 * « Urgence ») qui affichent désormais les numéros du pied de page.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('cms_key_figures')
            ->where('label', 'like', 'Régions couvertes%')
            ->update(['value' => 17, 'updated_at' => now()]);

        DB::table('cms_pages')
            ->whereIn('slug', ['services', 'apropos'])
            ->get(['id', 'sections_json'])
            ->each(function ($page) {
                $sections = json_decode($page->sections_json, true);
                if (! is_array($sections)) {
                    return;
                }
                DB::table('cms_pages')
                    ->where('id', $page->id)
                    ->update(['sections_json' => json_encode($this->updated($sections), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
            });

        app(PublicSiteService::class)->forgetCaches('services');
        app(PublicSiteService::class)->forgetCaches('apropos');
    }

    private function updated(array $node): array
    {
        $type = $node['type'] ?? null;

        if ($type === 'services_coverage' && is_array($node['content']['items'] ?? null)) {
            foreach ($node['content']['items'] as $i => $item) {
                if (is_array($item)) {
                    $node['content']['items'][$i]['rate'] = '80%';
                }
            }
        }

        if ($type === 'services_cta' && ($node['content']['secondaryLabel'] ?? null) === 'Urgence : 112') {
            $node['content']['secondaryLabel'] = 'Urgence';
        }

        if ($type === 'about_cta' && ($node['content']['secondaryHref'] ?? null) === 'tel:+22625300000') {
            $node['content']['secondaryHref'] = 'tel:+22625308103';
        }

        foreach ($node as $key => $value) {
            if (is_array($value) && $key !== 'content') {
                $node[$key] = $this->updated($value);
            }
        }

        return $node;
    }

    public function down(): void
    {
        // Les anciennes valeurs (6 régions, taux différenciés) ne sont pas restaurées :
        // les ajuster depuis le CMS si nécessaire.
    }
};
