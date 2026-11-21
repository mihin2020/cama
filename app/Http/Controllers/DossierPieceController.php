<?php

namespace App\Http\Controllers;

use App\Models\Dossier;
use App\Services\AdminDossierService;
use App\Services\DossierPieceStorageService;
use App\Support\StoredFileResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;

class DossierPieceController extends Controller
{
    public function download(Request $request, Dossier $dossier, DossierPieceStorageService $storage): BinaryFileResponse
    {
        $type = $request->query('type');
        abort_unless($type, 404);

        $this->authorizePieceAccess($request, $dossier);

        $piece = collect($dossier->pieces ?? [])->first(fn ($p) => ($p['type'] ?? '') === $type);
        abort_unless($piece && ! empty($piece['path']), 404);

        $absolute = $storage->diskPath($piece['path']);
        abort_unless($absolute, 404);

        // « Visualiser » (?inline=1) ouvre le PDF ou l'image dans le navigateur ; sinon téléchargement.
        return StoredFileResponse::make($absolute, $piece['filename'] ?? null, $request->boolean('inline'));
    }

    private function authorizePieceAccess(Request $request, Dossier $dossier): void
    {
        // Route admin : un agent ayant la permission de consulter les dossiers (pas un compte réservé au site, par exemple),
        // et, sans « Consulter tous les dossiers », uniquement pour un dossier qui lui est affecté.
        if ($request->routeIs('admin.*')) {
            $admin = $request->user('admin');
            abort_unless($admin?->hasPermission('dossiers.view'), 403, 'Accès non autorisé pour vos permissions.');
            app(AdminDossierService::class)->authorizeAccess($admin, $dossier);

            return;
        }

        // Route assuré : uniquement les pièces de ses propres dossiers.
        $assure = $request->user('assure');
        abort_unless($assure && $dossier->assure_id === $assure->id, 403);
    }
}
