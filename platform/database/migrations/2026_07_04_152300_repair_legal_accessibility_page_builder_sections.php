<?php

use App\Services\CmsPageService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $service = app(CmsPageService::class);

        foreach (['mention_legales', 'accessibilite'] as $slug) {
            $page = DB::table('cms_pages')->where('slug', $slug)->first();

            if (! $page) {
                continue;
            }

            $sections = json_decode($page->sections_json ?? '[]', true);
            $firstType = (string) ($sections[0]['columns'][0]['widgets'][0]['type'] ?? '');

            if (is_array($sections) && $firstType === 'html' && ! $this->isGenericStarter($sections)) {
                continue;
            }

            DB::table('cms_pages')
                ->where('id', $page->id)
                ->update([
                    'sections_json' => json_encode($service->systemSectionsForSlug($slug), JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        //
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     */
    private function isGenericStarter(array $sections): bool
    {
        if (count($sections) !== 1) {
            return false;
        }

        $widgets = $sections[0]['columns'][0]['widgets'] ?? [];

        return count($widgets) === 2
            && in_array(($widgets[0]['type'] ?? null), ['heading', 'elementor_heading'], true)
            && in_array(($widgets[1]['type'] ?? null), ['text', 'elementor_text_editor'], true);
    }
};
