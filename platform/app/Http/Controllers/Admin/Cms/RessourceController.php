<?php

namespace App\Http\Controllers\Admin\Cms;

use App\Http\Controllers\Controller;
use App\Models\CmsResource;
use App\Models\CmsResourceCategory;
use App\Services\CmsDocumentUploadService;
use App\Services\CmsResourceCategoryService;
use App\Services\CmsResourceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RessourceController extends Controller
{
    public function index(Request $request, CmsResourceService $resources, CmsResourceCategoryService $categories): Response
    {
        $categoryId = $request->integer('category') ?: null;

        return Inertia::render('Admin/Cms/Ressources/Index', [
            'items' => $resources->listForAdmin($categoryId),
            'categoryList' => $categories->listForAdmin(),
            'categoryOptions' => $categories->options(),
            'activeCategoryId' => $categoryId,
        ]);
    }

    public function store(Request $request, CmsResourceService $resources): RedirectResponse
    {
        $data = $this->validatedResource($request);
        $resources->create($data, $request->file('document_file'), $request->user('admin'));

        return back()->with('success', 'Document enregistré.');
    }

    public function update(Request $request, CmsResource $resource, CmsResourceService $resources): RedirectResponse
    {
        $data = $this->validatedResource($request, $resource);
        $resources->update($resource, $data, $request->file('document_file'), $request->user('admin'));

        return back()->with('success', 'Document mis à jour.');
    }

    public function destroy(Request $request, CmsResource $resource, CmsResourceService $resources): RedirectResponse
    {
        $resources->delete($resource, $request->user('admin'));

        return back()->with('success', 'Document supprimé.');
    }

    public function storeCategory(Request $request, CmsResourceCategoryService $categories): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:cms_resource_categories,name'],
        ]);

        $data['sort_order'] = (CmsResourceCategory::query()->max('sort_order') ?? 0) + 1;

        $categories->create($data, $request->user('admin'));

        return back()->with('success', 'Catégorie créée.');
    }

    public function destroyCategory(Request $request, CmsResourceCategory $category, CmsResourceCategoryService $categories): RedirectResponse
    {
        $categories->delete($category, $request->user('admin'));

        return back()->with('success', 'Catégorie supprimée.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedResource(Request $request, ?CmsResource $resource = null): array
    {
        $data = $request->validate([
            'titre' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category_id' => ['required', 'exists:cms_resource_categories,id'],
            'ordre' => ['required', 'integer', 'min:1'],
            'url' => ['nullable', 'string', 'max:1000'],
            'document_file' => ['nullable', 'file', 'max:10240', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg'],
            'publie' => ['boolean'],
        ]);

        $payload = [
            'title' => $data['titre'],
            'description' => $data['description'] ?? '',
            'category_id' => $data['category_id'],
            'sort_order' => $data['ordre'],
            'published' => $request->boolean('publie'),
        ];

        $url = trim($data['url'] ?? '');

        if ($url !== '' && (str_starts_with($url, 'http://') || str_starts_with($url, 'https://'))) {
            $payload['file_url'] = $url;
            $payload['format'] = CmsDocumentUploadService::formatFromFilename($url);
        } elseif (! $request->hasFile('document_file') && ! $resource) {
            $payload['file_url'] = '#';
            $payload['format'] = 'PDF';
        }

        return $payload;
    }
}
