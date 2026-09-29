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
        // On n'expose que les catégories qui contiennent au moins un document publié,
        // pour ne pas afficher d'onglet de filtre vide sur la page publique.
        $categories = CmsResourceCategory::query()
            ->whereHas('resources', fn ($q) => $q->where('published', true))
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
                'url' => $this->downloadableUrl($resource->file_url),
            ])
            ->all();

        return Inertia::render('Public/Ressources', [
            'categories' => $categories,
            'resources' => $resources,
            'articles' => $site->publishedArticles(12),
            'pageSections' => $site->pageSections('ressources'),
        ]);
    }

    /**
     * Renvoie l'URL de téléchargement seulement si le fichier existe réellement.
     * Les URL externes sont conservées telles quelles ; un fichier local absent
     * renvoie null (le front affiche alors « document bientôt disponible »).
     */
    private function downloadableUrl(?string $fileUrl): ?string
    {
        $url = CmsDocumentUploadService::resolveFileUrl($fileUrl);

        if ($url === null) {
            return null;
        }

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        return is_file(public_path(ltrim($url, '/'))) ? $url : null;
    }
}
