<?php

namespace App\Http\Controllers;

use App\Models\Dossier;
use App\Services\DossierPieceStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;

class DossierPieceController extends Controller
{
    /** Seuls ces formats peuvent être affichés dans le navigateur ; tout le reste est téléchargé. */
    private const INLINE_TYPES = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];

    public function download(Request $request, Dossier $dossier, DossierPieceStorageService $storage): BinaryFileResponse
    {
        $type = $request->query('type');
        abort_unless($type, 404);

        $this->authorizePieceAccess($request, $dossier);

        $piece = collect($dossier->pieces ?? [])->first(fn ($p) => ($p['type'] ?? '') === $type);
        abort_unless($piece && ! empty($piece['path']), 404);

        $absolute = $storage->diskPath($piece['path']);
        abort_unless($absolute, 404);

        $filename = $piece['filename'] ?? basename($absolute);
        $mime = File::mimeType($absolute) ?: 'application/octet-stream';
        $inline = $request->boolean('inline') && in_array($mime, self::INLINE_TYPES, true);

        // Nom d'origine encodé proprement (RFC 6266), avec une version ASCII de secours.
        $disposition = HeaderUtils::makeDisposition(
            $inline ? HeaderUtils::DISPOSITION_INLINE : HeaderUtils::DISPOSITION_ATTACHMENT,
            $filename,
            Str::ascii($filename) ?: 'piece',
        );

        return response()->file($absolute, [
            'Content-Type' => $inline ? $mime : 'application/octet-stream',
            'Content-Disposition' => $disposition,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function authorizePieceAccess(Request $request, Dossier $dossier): void
    {
        // Route admin : un agent ayant la permission de consulter les dossiers (pas un compte réservé au site, par exemple).
        if ($request->routeIs('admin.*')) {
            $admin = $request->user('admin');
            abort_unless($admin?->hasPermission('dossiers.view'), 403, 'Accès non autorisé pour vos permissions.');

            return;
        }

        // Route assuré : uniquement les pièces de ses propres dossiers.
        $assure = $request->user('assure');
        abort_unless($assure && $dossier->assure_id === $assure->id, 403);
    }
}
