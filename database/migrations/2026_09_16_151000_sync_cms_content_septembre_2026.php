<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('cms_key_figures')
            ->where('label', 'Bénéficiaires couverts')
            ->orWhere(function ($q) {
                $q->where('value', 250)->where('suffix', 'k');
            })
            ->update([
                'value' => 13,
                'suffix' => '',
                'label' => 'Régions couvertes',
                'icon' => 'map',
            ]);

        DB::table('cms_resource_categories')
            ->where('name', 'Textes législatifs')
            ->update(['name' => 'Textes réglementaires et législatifs']);
    }

    public function down(): void
    {
        DB::table('cms_key_figures')
            ->where('label', 'Régions couvertes')
            ->update([
                'value' => 250,
                'suffix' => 'k',
                'label' => 'Bénéficiaires couverts',
                'icon' => 'groups',
            ]);

        DB::table('cms_resource_categories')
            ->where('name', 'Textes réglementaires et législatifs')
            ->update(['name' => 'Textes législatifs']);
    }
};
