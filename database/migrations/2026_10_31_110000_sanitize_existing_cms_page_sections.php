<?php

use App\Services\PublicSiteService;
use App\Support\CmsSectionsSanitizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Les sections enregistrées avant le filtre (CmsSectionsSanitizer) passent une fois par lui :
 * pages publiées comme versions, qui peuvent être prévisualisées ou restaurées.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['cms_pages', 'cms_page_versions'] as $table) {
            DB::table($table)->whereNotNull('sections_json')->orderBy('id')->each(function ($row) use ($table) {
                $sections = json_decode((string) $row->sections_json, true);
                if (! is_array($sections)) {
                    return;
                }

                $clean = CmsSectionsSanitizer::sanitize($sections);
                if ($clean !== $sections) {
                    DB::table($table)->where('id', $row->id)->update(['sections_json' => json_encode($clean)]);

                    if ($table === 'cms_pages') {
                        app(PublicSiteService::class)->forgetCaches($row->slug);
                    }
                }
            });
        }
    }

    public function down(): void
    {
        // Pas de retour : le contenu retiré était du code exécutable.
    }
};
