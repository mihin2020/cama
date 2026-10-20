<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * La page « Accessibilité » est retirée du site. Ses versions et ses entrées
     * de menu de type « page » partent avec elle (clés étrangères en cascade).
     */
    public function up(): void
    {
        DB::table('cms_pages')->where('slug', 'accessibilite')->delete();
        DB::table('cms_menu_items')->where('type', 'custom')->where('url', 'like', '%/accessibilite%')->delete();

        Cache::forget('public_site.shared.v1');
        Cache::forget('public_site.sections.accessibilite.v1');
    }

    public function down(): void
    {
        //
    }
};
