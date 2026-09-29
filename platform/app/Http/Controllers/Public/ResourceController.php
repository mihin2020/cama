<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\CmsResource;
use App\Models\CmsResourceCategory;
use App\Services\CmsDocumentUploadService;
use App\Services\PublicSiteService;
use Inertia\Inertia;
use Inertia\Response;

class ResourceController extends Controller
{
    public function index(PublicSiteService $site): Response
    {
        $categories = CmsResourceCategory::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name')
            ->all();

        $resources = CmsResource::query()
            ->with('category')
            ->where('published', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (CmsResource $resource) => [
                'id' => $resource->id,
                'title' => $resource->title,
                'category' => $resource->category?->name ?? 'Autres',
                'description' => $resource->description ?? '',
                'format' => $resource->format ?: 'PDF',
                'size' => $resource->file_size ?? '',
                'url' => CmsDocumentUploadService::resolveFileUrl($resource->file_url),
            ])
            ->all();

        return Inertia::render('Public/Ressources', [
            'categories' => $categories,
            'resources' => $resources,
            'articles' => $site->publishedArticles(12),
            'pageSections' => $site->pageSections('ressources'),
        ]);
    }
}
