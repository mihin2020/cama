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

        if (! is_array($sections) || ! $this->shouldRepair($sections)) {
            return;
        }

        DB::table('cms_pages')
            ->where('id', $page->id)
            ->update([
                'sections_json' => json_encode(app(CmsPageService::class)->aboutSystemSections(), JSON_UNESCAPED_UNICODE),
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
    private function shouldRepair(array $sections): bool
    {
        $firstType = $sections[0]['columns'][0]['widgets'][0]['type'] ?? null;

        return str_starts_with((string) $firstType, 'home_')
            || str_starts_with((string) $firstType, 'services_')
            || $this->isGenericStarter($sections);
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
