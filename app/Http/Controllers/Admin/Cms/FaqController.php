<?php

namespace App\Http\Controllers\Admin\Cms;

use App\Http\Controllers\Controller;
use App\Models\CmsFaq;
use App\Models\CmsFaqCategory;
use App\Services\CmsFaqCategoryService;
use App\Services\CmsFaqService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class FaqController extends Controller
{
    public function index(Request $request, CmsFaqService $faq, CmsFaqCategoryService $categories): Response
    {
        $categoryId = $request->integer('category') ?: null;

        return Inertia::render('Admin/Cms/Faq/Index', [
            'items' => $faq->listForAdmin($categoryId),
            'categoryList' => $categories->listForAdmin(),
            'categoryOptions' => $categories->options(),
            'activeCategoryId' => $categoryId,
        ]);
    }

    public function store(Request $request, CmsFaqService $faq): RedirectResponse
    {
        $data = $this->validatedFaq($request);
        $faq->create($data, $request->user('admin'));

        return back()->with('success', 'Question FAQ enregistrée.');
    }

    public function update(Request $request, CmsFaq $faq, CmsFaqService $service): RedirectResponse
    {
        $data = $this->validatedFaq($request);
        $service->update($faq, $data, $request->user('admin'));

        return back()->with('success', 'Question FAQ mise à jour.');
    }

    public function destroy(Request $request, CmsFaq $faq, CmsFaqService $service): RedirectResponse
    {
        $service->delete($faq, $request->user('admin'));

        return back()->with('success', 'Question FAQ supprimée.');
    }

    public function storeCategory(Request $request, CmsFaqCategoryService $categories): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:cms_faq_categories,name'],
            'sort_order' => ['nullable', 'integer', 'min:1'],
        ]);

        $data['sort_order'] ??= (CmsFaqCategory::query()->max('sort_order') ?? 0) + 1;

        $categories->create($data, $request->user('admin'));

        return back()->with('success', 'Catégorie FAQ créée.');
    }

    public function updateCategory(Request $request, CmsFaqCategory $category, CmsFaqCategoryService $service): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('cms_faq_categories', 'name')->ignore($category->id)],
            'sort_order' => ['required', 'integer', 'min:1'],
        ]);

        $service->update($category, $data, $request->user('admin'));

        return back()->with('success', 'Catégorie FAQ mise à jour.');
    }

    public function destroyCategory(Request $request, CmsFaqCategory $category, CmsFaqCategoryService $service): RedirectResponse
    {
        $service->delete($category, $request->user('admin'));

        return back()->with('success', 'Catégorie FAQ supprimée.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedFaq(Request $request): array
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'max:1000'],
            'answer' => ['required', 'string', 'max:5000'],
            'category_id' => ['required', 'exists:cms_faq_categories,id'],
            'sort_order' => ['required', 'integer', 'min:1'],
            'published' => ['boolean'],
        ]);

        $data['published'] = $request->boolean('published');

        return $data;
    }
}
