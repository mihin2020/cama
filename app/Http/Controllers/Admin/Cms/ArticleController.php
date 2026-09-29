<?php

namespace App\Http\Controllers\Admin\Cms;

use App\Http\Controllers\Controller;
use App\Models\CmsArticle;
use App\Models\CmsArticleCategory;
use App\Services\CmsArticleCategoryService;
use App\Services\CmsArticleService;
use App\Services\CmsImageUploadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ArticleController extends Controller
{
    public function index(Request $request, CmsArticleService $articles, CmsArticleCategoryService $categories): Response
    {
        $articles->publishDueArticles();

        $filter = $request->string('status', 'Tous')->toString();
        $categoryId = $request->integer('category') ?: null;

        return Inertia::render('Admin/Cms/Articles/Index', [
            'items' => $articles->listForAdmin($filter, $categoryId),
            'statusFilters' => array_merge(['Tous'], array_values(CmsArticleService::STATUSES)),
            'activeFilter' => $filter,
            'categoryList' => $categories->listForAdmin(),
            'categoryOptions' => $categories->options(),
            'activeCategoryId' => $categoryId,
            'statusOptions' => CmsArticleService::STATUSES,
        ]);
    }

    public function store(Request $request, CmsArticleService $articles): RedirectResponse
    {
        $data = $this->validated($request);
        $articles->create($data, $request->user('admin'));

        return back()->with('success', 'Article enregistré.');
    }

    public function update(Request $request, CmsArticle $article, CmsArticleService $service): RedirectResponse
    {
        $data = $this->validated($request, $article);
        $service->update($article, $data, $request->user('admin'));

        return back()->with('success', 'Article mis à jour.');
    }

    public function destroy(Request $request, CmsArticle $article, CmsArticleService $service): RedirectResponse
    {
        $service->delete($article, $request->user('admin'));

        return back()->with('success', 'Article supprimé.');
    }

    public function storeCategory(Request $request, CmsArticleCategoryService $categories): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:cms_article_categories,name'],
            'sort_order' => ['nullable', 'integer', 'min:1'],
        ]);

        $data['sort_order'] ??= (CmsArticleCategory::query()->max('sort_order') ?? 0) + 1;

        $categories->create($data, $request->user('admin'));

        return back()->with('success', 'Catégorie créée.');
    }

    public function updateCategory(Request $request, CmsArticleCategory $category, CmsArticleCategoryService $service): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('cms_article_categories', 'name')->ignore($category->id)],
            'sort_order' => ['required', 'integer', 'min:1'],
        ]);

        $service->update($category, $data, $request->user('admin'));

        return back()->with('success', 'Catégorie mise à jour.');
    }

    public function destroyCategory(Request $request, CmsArticleCategory $category, CmsArticleCategoryService $service): RedirectResponse
    {
        $service->delete($category, $request->user('admin'));

        return back()->with('success', 'Catégorie supprimée.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?CmsArticle $article = null): array
    {
        $request->validate([
            'image_file' => ['nullable', 'file', 'image', 'mimes:jpeg,jpg,png,webp,gif,jfif', 'max:5120'],
        ], [
            'image_file.image' => 'Le fichier doit être une image.',
            'image_file.mimes' => 'Formats acceptés : JPEG, PNG, WebP, GIF.',
            'image_file.max' => 'L\'image ne doit pas dépasser 5 Mo.',
        ]);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:500'],
            'category_id' => ['required', 'exists:cms_article_categories,id'],
            'status' => ['required', Rule::in(array_keys(CmsArticleService::STATUSES))],
            'image_url' => ['nullable', 'string', 'max:500'],
            'excerpt' => ['nullable', 'string', 'max:2000'],
            'body_html' => ['nullable', 'string'],
            'published_at' => ['nullable', 'date'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('cms_articles', 'slug')->ignore($article?->id)],
        ]);

        if (! empty($data['body_html']) && ! str_contains($data['body_html'], '<')) {
            $paragraphs = preg_split('/\n\s*\n/', trim($data['body_html'])) ?: [];
            $data['body_html'] = collect($paragraphs)
                ->filter()
                ->map(fn (string $p) => '<p>'.e(trim($p)).'</p>')
                ->join('');
        }

        if ($data['status'] === 'published' && empty($data['published_at'])) {
            $data['published_at'] = now()->toDateString();
        }

        if ($data['status'] === 'scheduled') {
            $dateRules = ['required', 'date'];
            if (! $article) {
                $dateRules[] = 'after_or_equal:today';
            }

            $request->validate([
                'published_at' => $dateRules,
            ], [
                'published_at.required' => 'La date de publication est obligatoire pour un article programmé.',
                'published_at.after_or_equal' => 'La date de programmation doit être aujourd\'hui ou ultérieure.',
            ]);
        }

        if ($request->hasFile('image_file')) {
            $uploader = app(CmsImageUploadService::class);
            $uploader->deleteIfUploaded($article?->image_url);
            $data['image_url'] = $uploader->store($request->file('image_file'));
        } elseif (empty($data['image_url'])) {
            $data['image_url'] = $article?->image_url ?? 'images/CAMA_8.jfif';
        }

        return $data;
    }
}
