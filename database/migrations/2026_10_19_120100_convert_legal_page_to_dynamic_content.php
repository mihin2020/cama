<?php

use App\Services\CmsPageService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Les mentions légales étaient un bloc HTML figé (mal rendu : ses classes Tailwind,
     * écrites dans du PHP, n'étaient pas compilées). On passe au widget structuré
     * « legal_page », uniquement si la page contient encore cet ancien bloc.
     */
    public function up(): void
    {
        $page = DB::table('cms_pages')->where('slug', 'mention_legales')->first();

        if (! $page || ! $this->isLegacyHtml($page->sections_json)) {
            return;
        }

        DB::table('cms_pages')->where('id', $page->id)->update([
            'sections_json' => json_encode(app(CmsPageService::class)->legalSystemSections(), JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);

        Cache::forget('public_site.sections.mention_legales.v1');
    }

    public function down(): void
    {
        //
    }

    private function isLegacyHtml(?string $json): bool
    {
        if ($json === null) {
            return true;
        }

        $sections = json_decode($json, true);
        if (! is_array($sections) || $sections === []) {
            return true;
        }

        $widgets = collect($sections)
            ->flatMap(fn ($section) => $section['columns'] ?? [])
            ->flatMap(fn ($column) => $column['widgets'] ?? []);

        return $widgets->count() === 1
            && ($widgets->first()['type'] ?? null) === 'html'
            && str_contains((string) ($widgets->first()['content']['html'] ?? ''), 'Informations L');
    }
};
