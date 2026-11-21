<?php

namespace App\Http\Controllers;

use App\Models\Assure;
use App\Models\Dossier;
use App\Services\AdminDossierService;
use App\Support\StoredFileResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Fichiers des champs « fichier » du formulaire FIF : à visualiser (?inline=1) ou à télécharger.
 *
 * Deux portées : « assure » (champs de l'assuré) et « dossier » (champs d'un conjoint ou d'un enfant).
 * L'administration doit avoir le droit de consulter l'élément ; un assuré n'accède qu'aux siens.
 */
class FifFileController extends Controller
{
    public function show(Request $request, string $scope, int $id, string $code): BinaryFileResponse
    {
        abort_unless(in_array($scope, ['assure', 'dossier'], true), 404);

        $owner = $scope === 'assure' ? Assure::query()->findOrFail($id) : Dossier::query()->findOrFail($id);
        $this->authorizeAccess($request, $scope, $owner);

        // Le chemin vient des données enregistrées, jamais de la requête, et reste dans l'espace privé des pièces.
        $meta = ($owner->fif_donnees ?? [])[$code] ?? null;
        $path = is_array($meta) ? (string) ($meta['path'] ?? '') : '';

        abort_unless($path !== '' && str_starts_with($path, 'assures/') && Storage::disk('local')->exists($path), 404);

        return StoredFileResponse::make(
            Storage::disk('local')->path($path),
            $meta['filename'] ?? null,
            $request->boolean('inline'),
        );
    }

    private function authorizeAccess(Request $request, string $scope, Assure|Dossier $owner): void
    {
        if ($request->routeIs('admin.*')) {
            $admin = $request->user('admin');

            if ($scope === 'assure') {
                abort_unless(
                    $admin?->hasPermission('assures.view') || $admin?->hasPermission('inscriptions.view') || $admin?->hasPermission('dossiers.view'),
                    403,
                    'Accès non autorisé pour vos permissions.',
                );

                return;
            }

            abort_unless($admin?->hasPermission('dossiers.view'), 403, 'Accès non autorisé pour vos permissions.');
            app(AdminDossierService::class)->authorizeAccess($admin, $owner);

            return;
        }

        $assure = $request->user('assure');
        $ownId = $scope === 'assure' ? $owner->id : $owner->assure_id;
        abort_unless($assure && $ownId === $assure->id, 403);
    }
}
