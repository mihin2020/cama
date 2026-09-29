<?php

use App\Services\CmsPageService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $page = DB::table('cms_pages')->where('slug', 'apropos')->first();

        if (! $page) {
            return;
        }

        $sections = json_decode($page->sections_json ?? '[]', true);

        if (! is_array($sections)) {
            return;
        }

        $hasCta = collect($sections)->contains(
            fn (array $section) => ($section['columns'][0]['widgets'][0]['type'] ?? null) === 'about_cta'
        );

        if ($hasCta) {
            return;
        }

        $systemSections = app(CmsPageService::class)->aboutSystemSections();
        $ctaSection = $systemSections[array_key_last($systemSections)] ?? null;

        if (! $ctaSection) {
            return;
        }

        $sections[] = $ctaSection;

        DB::table('cms_pages')
            ->where('id', $page->id)
            ->update([
                'sections_json' => json_encode($sections, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        $page = DB::table('cms_pages')->where('slug', 'apropos')->first();

        if (! $page) {
            return;
        }

        $sections = json_decode($page->sections_json ?? '[]', true);

        if (! is_array($sections)) {
            return;
        }

        $sections = array_values(array_filter(
            $sections,
            fn (array $section) => ($section['columns'][0]['widgets'][0]['type'] ?? null) !== 'about_cta'
        ));

        DB::table('cms_pages')
            ->where('id', $page->id)
            ->update([
                'sections_json' => json_encode($sections, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
    }
};
