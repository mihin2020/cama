<?php

use App\Services\CmsPageService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $page = DB::table('cms_pages')->where('slug', 'services')->first();

        if (! $page) {
            return;
        }

        $sections = json_decode($page->sections_json ?? '[]', true);

        if (! is_array($sections) || ! $this->isGenericStarter($sections)) {
            return;
        }

        DB::table('cms_pages')
            ->where('id', $page->id)
            ->update([
                'sections_json' => json_encode(app(CmsPageService::class)->servicesSystemSections(), JSON_UNESCAPED_UNICODE),
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
    private function isGenericStarter(array $sections): bool
    {
        if (count($sections) !== 1) {
            return false;
        }

        $widgets = $sections[0]['columns'][0]['widgets'] ?? [];

        return count($widgets) === 2
            && ($widgets[0]['type'] ?? null) === 'heading'
            && ($widgets[1]['type'] ?? null) === 'text';
    }
};
