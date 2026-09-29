<?php

use App\Services\CmsPageService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $service = app(CmsPageService::class);

        foreach (['ressources', 'actualites', 'contact'] as $slug) {
            $page = DB::table('cms_pages')->where('slug', $slug)->first();

            if (! $page) {
                continue;
            }

            $sections = json_decode($page->sections_json ?? '[]', true);

            if (! is_array($sections) || ! $this->shouldRepair($sections, $slug)) {
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
    private function shouldRepair(array $sections, string $slug): bool
    {
        $firstType = (string) ($sections[0]['columns'][0]['widgets'][0]['type'] ?? '');
        $expectedPrefix = match ($slug) {
            'ressources' => 'resources_',
            'actualites' => 'news_',
            'contact' => 'contact_',
            default => '',
        };

        if (str_starts_with($firstType, $expectedPrefix)) {
            return false;
        }

        return $this->isGenericStarter($sections)
            || str_starts_with($firstType, 'home_')
            || str_starts_with($firstType, 'services_')
            || str_starts_with($firstType, 'about_');
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
