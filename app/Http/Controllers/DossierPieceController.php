<?php

namespace App\Http\Controllers;

use App\Models\Dossier;
use App\Services\DossierPieceStorageService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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

        $filename = $piece['filename'] ?? basename($absolute);

        if ($request->boolean('inline')) {
            return response()->file($absolute, [
                'Content-Disposition' => 'inline; filename="'.$filename.'"',
            ]);
        }

        return response()->download($absolute, $filename);
    }

    private function authorizePieceAccess(Request $request, Dossier $dossier): void
    {
        if ($request->user('admin')) {
            return;
        }

        $assure = $request->user('assure');
        abort_unless($assure && $dossier->assure_id === $assure->id, 403);
    }
}
