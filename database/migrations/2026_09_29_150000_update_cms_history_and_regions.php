<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Contenu CMS déjà en base : plus aucune mention de la MUFAN, et 17 régions
 * couvertes (découpage administratif actuel du Burkina Faso) au lieu de 13.
 * Les seeders n'écrivent que dans des tables vides : sans cette migration, les
 * installations existantes garderaient l'ancien contenu.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('cms_key_figures')
            ->where('label', 'Régions couvertes')
            ->update(['value' => 17]);

        DB::table('cms_key_figures')
            ->where('label', 'Année de fondation (MUFAN)')
            ->update(['label' => 'Année de création']);

        DB::table('cms_slides')
            ->where('subtitle', 'like', '%MUFAN%')
            ->update([
                'subtitle' => DB::raw("REPLACE(subtitle, 'Successeur de la MUFAN, la CAMA', 'La CAMA')"),
            ]);

        DB::table('cms_articles')
            ->where('body_html', 'like', '%MUFAN%')
            ->update([
                'body_html' => DB::raw("REPLACE(body_html, 'succède à la MUFAN et élargit', 'élargit')"),
            ]);

        DB::table('cms_pages')
            ->where('sections_json', 'like', '%MUFAN%')
            ->get(['id', 'sections_json'])
            ->each(function ($page) {
                $sections = json_decode($page->sections_json, true);
                if (! is_array($sections)) {
                    return;
                }
                DB::table('cms_pages')
                    ->where('id', $page->id)
                    ->update(['sections_json' => json_encode($this->withoutMufan($sections), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
            });
    }

    /**
     * Retire les jalons de frise consacrés à la MUFAN et la mention « succéder à la MUFAN ».
     */
    private function withoutMufan(array $node): array
    {
        foreach ($node as $key => $value) {
            if (is_array($value)) {
                $node[$key] = $this->withoutMufan($value);
            } elseif (is_string($value)) {
                $node[$key] = str_replace(' et succéder à la MUFAN', '', $value);
            }
        }

        if (isset($node['items']) && is_array($node['items']) && array_is_list($node['items'])) {
            $node['items'] = array_values(array_filter(
                $node['items'],
                fn ($item) => ! (is_array($item) && str_contains((string) ($item['title'] ?? ''), 'MUFAN')),
            ));
        }

        return $node;
    }

    public function down(): void
    {
        DB::table('cms_key_figures')
            ->where('label', 'Régions couvertes')
            ->update(['value' => 13]);

        DB::table('cms_key_figures')
            ->where('label', 'Année de création')
            ->update(['label' => 'Année de fondation (MUFAN)']);

        // Le jalon de frise retiré de cms_pages n'est pas restauré : le rétablir
        // depuis le constructeur de pages si nécessaire.
    }
};
