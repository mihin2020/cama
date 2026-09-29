<?php

namespace App\Services;

use App\Models\CmsArticle;
use App\Models\CmsInfoBanner;
use App\Models\CmsKeyFigure;
use App\Models\CmsMenuItem;
use App\Models\CmsPage;
use App\Models\CmsSlide;
use Illuminate\Support\Facades\Cache;

class PublicSiteService
{
    /**
     * @return array<string, mixed>
     */
    /**
     * Vide les caches du site public (à appeler après toute modification de contenu).
     */
    public function forgetCaches(?string $slug = null): void
    {
        Cache::forget('public_site.shared.v1');
        Cache::forget('public_site.home.v1');
        Cache::forget('public_site.sections.services.v1');

        foreach ([3, 6, 12] as $limit) {
            Cache::forget("public_site.articles.{$limit}.v1");
        }

        if ($slug !== null && $slug !== '') {
            Cache::forget("public_site.sections.{$slug}.v1");
        }
    }

    public function shared(): array
    {
        return Cache::remember('public_site.shared.v1', now()->addMinutes(5), fn () => [
            'menu' => $this->menu(),
            'banner' => $this->banner(),
            'footer' => app(PlatformSettingsService::class)->footer(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function home(): array
    {
        return Cache::remember('public_site.home.v1', now()->addMinutes(5), fn () => [
            'slides' => $this->slides(),
            'pillars' => $this->homePillars(),
            'pageSections' => $this->homeSections(),
            'keyFigures' => $this->keyFigures(),
            'latestArticles' => $this->latestArticles(),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function pageSections(string $slug): array
    {
        return Cache::remember("public_site.sections.{$slug}.v1", now()->addMinutes(5), function () use ($slug) {
            $page = CmsPage::query()
                ->where('slug', $slug)
                ->where('status', 'published')
                ->first();

            $default = app(CmsPageService::class)->systemSectionsForSlug($slug);

            if (! $page || ! is_array($page->sections_json)) {
                return $default ?? [];
            }

            if ($this->isStarterPageSection($page, $page->sections_json)) {
                return $default ?? [];
            }

            return $page->sections_json;
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function servicesSections(): array
    {
        return Cache::remember('public_site.sections.services.v1', now()->addMinutes(5), function () {
            $page = CmsPage::query()
                ->where('slug', 'services')
                ->first();

            $default = app(CmsPageService::class)->servicesSystemSections();

            if (! $page || ! is_array($page->sections_json)) {
                return $default;
            }

            if ($this->isStarterPageSection($page, $page->sections_json)) {
                return $default;
            }

            return $page->sections_json;
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function menu(): array
    {
        $items = CmsMenuItem::query()
            ->with('page')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (CmsMenuItem $item) => [
                'id' => $item->id,
                'label' => $item->label,
                'href' => $this->publicHref(app(CmsMenuService::class)->href($item)),
                'depth' => $item->depth,
                'icon' => $this->iconFor($item->page?->slug, $item->label),
            ]);

        $groups = [];
        foreach ($items as $item) {
            if ($item['depth'] === 0 || empty($groups)) {
                $item['children'] = [];
                $groups[] = $item;
                continue;
            }

            $groups[array_key_last($groups)]['children'][] = $item;
        }

        return $this->withRequiredPublicMenu($groups ?: $this->defaultMenu());
    }

    /**
     * @return array<string, mixed>|null
     */
    public function banner(): ?array
    {
        $banner = CmsInfoBanner::query()->first();
        if (! $banner || ! $banner->active) {
            return null;
        }

        return [
            'type' => $banner->type,
            'message' => $this->normalizeHtmlLinks($banner->message ?? ''),
            'link_url' => $banner->link_url ? $this->publicHref($banner->link_url) : null,
            'link_label' => $banner->link_label,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function slides(): array
    {
        return CmsSlide::query()
            ->where('active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (CmsSlide $slide) => [
                'id' => $slide->id,
                'title' => $slide->title,
                'subtitle' => $slide->subtitle,
                'image_src' => CmsArticleService::resolveImageUrl($slide->image_url),
                'link_url' => $this->publicHref($slide->link_url),
                'link_label' => $slide->link_label,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function keyFigures(): array
    {
        return CmsKeyFigure::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (CmsKeyFigure $figure) => [
                'id' => $figure->id,
                'value' => $figure->value,
                'suffix' => $figure->suffix ?? '',
                'label' => $figure->label,
                'icon' => $figure->icon ?: 'monitoring',
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function publishedArticles(int $limit = 12): array
    {
        return Cache::remember("public_site.articles.{$limit}.v1", now()->addMinutes(5), function () use ($limit) {
            return CmsArticle::query()
                ->with('category')
                ->where('status', 'published')
                ->orderByDesc('featured')
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->limit(max(1, $limit))
                ->get()
                ->map(fn (CmsArticle $article) => [
                    'id' => $article->id,
                    'slug' => $article->slug,
                    'title' => $article->title,
                    'category' => $article->category?->name ?? 'Actualité',
                    'excerpt' => $article->excerpt,
                    'image_src' => CmsArticleService::resolveImageUrl($article->image_url),
                    'published_at' => $article->published_at?->format('d M Y') ?? '',
                    'href' => '/actualites/'.$article->slug,
                    'featured' => $article->featured,
                ])
                ->all();
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function latestArticles(): array
    {
        return $this->publishedArticles(3);
    }

    /**
     * Les piliers sont personnalisables dans l'éditeur de pages :
     * page "Accueil" > widget "Cartes" > items.
     *
     * @return array<int, array<string, string>>
     */
    private function homePillars(): array
    {
        $page = CmsPage::query()
            ->where('slug', 'accueil')
            ->first();

        $sections = $page?->sections_json ?? [];
        if (is_array($sections)) {
            foreach ($sections as $section) {
                foreach (($section['columns'] ?? []) as $column) {
                    foreach (($column['widgets'] ?? []) as $widget) {
                        if (($widget['type'] ?? null) !== 'cards') {
                            continue;
                        }

                        $items = $widget['content']['items'] ?? [];
                        if (! is_array($items) || count($items) < 3) {
                            continue;
                        }

                        return collect($items)->take(3)->map(fn (array $item) => [
                            'icon' => $item['icon'] ?? 'health_and_safety',
                            'title' => $item['title'] ?? '',
                            'text' => $item['text'] ?? '',
                        ])->values()->all();
                    }
                }
            }
        }

        return [
            [
                'icon' => 'medical_services',
                'title' => 'Accès',
                'text' => 'Garantir à chaque service membre un accès immédiat à un vaste réseau de prestataires de santé agréés sur l’ensemble du territoire national.',
            ],
            [
                'icon' => 'diversity_3',
                'title' => 'Solidarité',
                'text' => 'Un système mutualisé où la force du collectif protège l’individu, assurant une prise en charge équitable pour tous les ayants droit.',
            ],
            [
                'icon' => 'visibility',
                'title' => 'Transparence',
                'text' => 'Une gestion rigoureuse et éthique des cotisations, avec des processus clairs pour les remboursements et la gestion des droits.',
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function homeSections(): array
    {
        $page = CmsPage::query()
            ->where('slug', 'accueil')
            ->first();

        $default = app(CmsPageService::class)->homeSystemSections();

        if (! $page || ! is_array($page->sections_json)) {
            return $default;
        }

        if ($this->isStarterPageSection($page, $page->sections_json)) {
            return $default;
        }

        return $page->sections_json;
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     */
    private function isStarterPageSection(CmsPage $page, array $sections): bool
    {
        if (count($sections) !== 1) {
            return false;
        }

        $columns = $sections[0]['columns'] ?? [];
        if (count($columns) !== 1) {
            return false;
        }

        $widgets = $columns[0]['widgets'] ?? [];
        if (count($widgets) !== 2) {
            return false;
        }

        $heading = $widgets[0];
        $text = $widgets[1];

        return ($heading['type'] ?? null) === 'heading'
            && ($text['type'] ?? null) === 'text'
            && trim((string) ($heading['content']['text'] ?? '')) === $page->title;
    }

    private function publicHref(?string $href): string
    {
        if (! $href || $href === '#') {
            return '#';
        }

        if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://') || str_starts_with($href, '#')) {
            return $href;
        }

        $map = [
            'index.html' => '/',
            'accueil.html' => '/',
            'apropos.html' => '/apropos',
            'services.html' => '/services',
            'contact.html' => '/contact',
            'actualite.html' => '/actualites',
            'actualites.html' => '/actualites',
            'ressources.html' => '/ressources',
            'mention_legales.html' => '/mention_legales',
            'inscription-assure.html' => '/inscription-assure',
            'espace-assure.html' => '/espace-assure/connexion',
        ];

        return $map[$href] ?? (str_starts_with($href, '/') ? $href : '/'.ltrim($href, '/'));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function defaultMenu(): array
    {
        return [
            ['id' => 'home', 'label' => 'Accueil', 'href' => '/', 'depth' => 0, 'icon' => 'home', 'children' => []],
            ['id' => 'apropos', 'label' => 'À propos', 'href' => '/apropos', 'depth' => 0, 'icon' => 'info', 'children' => []],
            ['id' => 'services', 'label' => 'Services', 'href' => '/services', 'depth' => 0, 'icon' => 'medical_services', 'children' => []],
            ['id' => 'ressources', 'label' => 'Ressources', 'href' => '/ressources', 'depth' => 0, 'icon' => 'folder_open', 'children' => []],
            ['id' => 'actualites', 'label' => 'Actualités', 'href' => '/actualites', 'depth' => 0, 'icon' => 'newspaper', 'children' => []],
            ['id' => 'contact', 'label' => 'Contact', 'href' => '/contact', 'depth' => 0, 'icon' => 'mail', 'children' => []],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $menu
     * @return array<int, array<string, mixed>>
     */
    private function withRequiredPublicMenu(array $menu): array
    {
        $required = $this->defaultMenu();
        $byHref = collect($menu)->keyBy('href');

        $ordered = collect($required)->map(function (array $item) use ($byHref) {
            return $byHref->get($item['href'], $item);
        });

        return $ordered->values()->all();
    }

    private function normalizeHtmlLinks(string $html): string
    {
        $replacements = [
            'href="index.html"' => 'href="/"',
            'href="accueil.html"' => 'href="/"',
            'href="apropos.html"' => 'href="/apropos"',
            'href="services.html"' => 'href="/services"',
            'href="contact.html"' => 'href="/contact"',
            'href="actualite.html"' => 'href="/actualites"',
            'href="actualites.html"' => 'href="/actualites"',
            'href="ressources.html"' => 'href="/ressources"',
            'href="mention_legales.html"' => 'href="/mention_legales"',
            'href="inscription-assure.html"' => 'href="/inscription-assure"',
            'href="espace-assure.html"' => 'href="/espace-assure/connexion"',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $html);
    }

    private function iconFor(?string $slug, string $label): string
    {
        $key = $slug ?: str($label)->lower()->ascii()->toString();

        return match (true) {
            str_contains($key, 'accueil') => 'home',
            str_contains($key, 'apropos') => 'info',
            str_contains($key, 'service') => 'medical_services',
            str_contains($key, 'ressource') => 'folder_open',
            str_contains($key, 'actual') => 'newspaper',
            str_contains($key, 'contact') => 'mail',
            default => 'chevron_right',
        };
    }
}
