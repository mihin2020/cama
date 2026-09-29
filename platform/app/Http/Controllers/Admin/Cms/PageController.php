<?php

namespace App\Http\Controllers\Admin\Cms;

use App\Http\Controllers\Controller;
use App\Models\CmsFaq;
use App\Models\CmsPageVersion;
use App\Models\CmsPage;
use App\Models\CmsPartner;
use App\Services\CmsMediaService;
use App\Services\CmsPageService;
use App\Services\CmsPageVersionService;
use App\Services\CmsPermissionService;
use App\Services\PublicSiteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    public function index(Request $request, CmsPageService $pages): Response
    {
        $status = $request->string('status')->toString();
        $query = $request->string('q')->toString();

        return Inertia::render('Admin/Cms/Pages/Index', [
            'items' => $pages->listForAdmin($query ?: null, $status ?: null),
            'filters' => [
                'q' => $query,
                'status' => $status,
            ],
            'statusOptions' => CmsPageService::STATUSES,
        ]);
    }

    public function builder(Request $request, CmsPageService $pages, CmsMediaService $media, CmsPageVersionService $versions): Response
    {
        $page = CmsPage::query()->find($request->integer('page'))
            ?? CmsPage::query()->orderByRaw("case when slug = 'accueil' then 0 else 1 end")->orderBy('title')->first();

        if (! $page) {
            $page = $pages->create([
                'title' => 'Nouvelle page',
                'status' => 'draft',
            ], $request->user('admin'));
        }

        return Inertia::render('Admin/Cms/PageBuilder/Index', [
            'currentPage' => [
                ...$pages->format($page),
                'sections' => $page->sections_json ?? [],
            ],
            'pageOptions' => $pages->options(),
            'statusOptions' => CmsPageService::STATUSES,
            'systemPreviewData' => $this->systemPreviewData($page),
            'mediaItems' => $media->list(type: 'images'),
            'versions' => $versions->list($page),
            'cmsPermissions' => app(CmsPermissionService::class)->forAdmin($request->user('admin')),
        ]);
    }

    public function store(Request $request, CmsPageService $pages): RedirectResponse
    {
        $page = $pages->create($this->validated($request), $request->user('admin'));

        if ($request->boolean('from_builder')) {
            return redirect()
                ->route('admin.cms.page_builder', ['page' => $page->id])
                ->with('success', 'Page « '.$page->title.' » créée.');
        }

        return back()->with('success', 'Page « '.$page->title.' » créée.');
    }

    public function update(Request $request, CmsPage $page, CmsPageService $pages): RedirectResponse
    {
        $updated = $pages->update($page, $this->validated($request, $page), $request->user('admin'));

        return back()->with('success', 'Page « '.$updated->title.' » mise à jour.');
    }

    public function toggleStatus(Request $request, CmsPage $page, CmsPageService $pages): RedirectResponse
    {
        $updated = $pages->toggleStatus($page, $request->user('admin'));

        return back()->with('success', $updated->status === 'published' ? 'Page publiée.' : 'Page repassée en brouillon.');
    }

    public function saveSections(Request $request, CmsPage $page, CmsPageService $pages): RedirectResponse
    {
        $data = $request->validate([
            'sections' => ['present', 'array'],
            'version_comment' => ['nullable', 'string', 'max:255'],
        ]);

        $this->authorizeHtmlUsage($request, $data['sections']);
        $pages->updateSections($page, $data['sections'], $request->user('admin'), $data['version_comment'] ?? null);

        return back()->with('success', 'Page enregistrée.');
    }

    public function publishFromBuilder(Request $request, CmsPage $page, CmsPageService $pages): RedirectResponse
    {
        app(CmsPermissionService::class)->authorize($request->user('admin'), 'canPublishPages');

        $request->validate([
            'sections' => ['present', 'array'],
            'version_comment' => ['nullable', 'string', 'max:255'],
        ]);

        $this->authorizeHtmlUsage($request, $request->input('sections', []));
        $pages->updateSections($page, $request->input('sections', []), $request->user('admin'), $request->string('version_comment')->toString() ?: null);

        if ($page->fresh()->status !== 'published') {
            $pages->toggleStatus($page->fresh(), $request->user('admin'));
        }

        app(CmsPageVersionService::class)->snapshot($page->fresh(), 'publish', $request->user('admin'), $request->string('version_comment')->toString() ?: null);

        return back()->with('success', 'Page publiée.');
    }

    public function restoreVersion(Request $request, CmsPage $page, CmsPageVersion $version, CmsPageVersionService $versions): RedirectResponse
    {
        app(CmsPermissionService::class)->authorize($request->user('admin'), 'canRestoreVersions');

        $versions->restore($page, $version, $request->user('admin'));

        return redirect()
            ->route('admin.cms.page_builder', ['page' => $page->id])
            ->with('success', 'Version restaurée.');
    }

    public function duplicateVersion(Request $request, CmsPage $page, CmsPageVersion $version, CmsPageVersionService $versions): RedirectResponse
    {
        app(CmsPermissionService::class)->authorize($request->user('admin'), 'canDuplicateVersions');

        $copy = $versions->duplicate($page, $version, $request->user('admin'));

        return redirect()
            ->route('admin.cms.page_builder', ['page' => $copy->id])
            ->with('success', 'Version dupliquée en nouvelle page.');
    }

    public function duplicate(Request $request, CmsPage $page, CmsPageService $pages): RedirectResponse
    {
        $copy = $pages->duplicate($page, $request->user('admin'));

        return back()->with('success', 'Copie « '.$copy->title.' » créée.');
    }

    public function destroy(Request $request, CmsPage $page, CmsPageService $pages): RedirectResponse
    {
        if ($page->slug === 'accueil') {
            return back()->with('error', 'La page d’accueil ne peut pas être supprimée.');
        }

        $pages->delete($page, $request->user('admin'));

        return back()->with('success', 'Page supprimée.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?CmsPage $page = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('cms_pages', 'slug')->ignore($page?->id)],
            'status' => ['required', Rule::in(array_keys(CmsPageService::STATUSES))],
        ]);
    }

    /**
     * @param  array<int, mixed>  $sections
     */
    private function authorizeHtmlUsage(Request $request, array $sections): void
    {
        if (! $this->containsHtmlWidget($sections)) {
            return;
        }

        app(CmsPermissionService::class)->authorize($request->user('admin'), 'canUseHtmlWidget');
    }

    /**
     * @param  array<int, mixed>  $sections
     */
    private function containsHtmlWidget(array $sections): bool
    {
        foreach ($sections as $section) {
            foreach ($section['columns'] ?? [] as $column) {
                foreach ($column['widgets'] ?? [] as $widget) {
                    if (($widget['type'] ?? null) === 'html') {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function systemPreviewData(CmsPage $page): array
    {
        $site = app(PublicSiteService::class);
        $data = [
            'articles' => $site->publishedArticles(12),
        ];

        if ($page->slug !== 'services') {
            return $data;
        }

        return array_merge($data, [
            'faqs' => CmsFaq::query()
                ->with('category')
                ->where('published', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn (CmsFaq $faq) => [
                    'id' => $faq->id,
                    'question' => $faq->question,
                    'answer' => $faq->answer,
                    'category' => $faq->category?->name ?? 'Général',
                ])
                ->all(),
            'partners' => CmsPartner::query()
                ->where('published', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn (CmsPartner $partner) => [
                    'id' => $partner->id,
                    'name' => $partner->name,
                    'type' => $partner->type,
                    'city' => $partner->city,
                    'description' => $partner->description ?? '',
                    'imageSrc' => $partner->image_url
                        ? (str_starts_with($partner->image_url, 'http') ? $partner->image_url : '/'.ltrim($partner->image_url, '/'))
                        : '/images/CAMA_1.jfif',
                    'mapsUrl' => $partner->maps_url ?: ($partner->latitude && $partner->longitude
                        ? 'https://www.google.com/maps/search/?api=1&query='.$partner->latitude.','.$partner->longitude
                        : null),
                ])
                ->all(),
        ]);
    }
}
