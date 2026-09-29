<?php

namespace App\Http\Controllers\Assure;

use App\Http\Controllers\Controller;
use App\Models\Dossier;
use App\Services\AssureDashboardService;
use App\Services\DossierPieceStorageService;
use App\Services\DossierService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class MembreController extends Controller
{
    public function index(DossierService $dossiers, AssureDashboardService $dashboard): Response
    {
        $assure = auth('assure')->user();
        $data = $dashboard->stats($assure);

        $membres = $assure->dossiers()
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn ($d) => $dossiers->formatMembre($d));

        return Inertia::render('Assure/Membres/Index', [
            'membres' => $membres,
            'unreadCount' => $data['unreadCount'],
            'peutEnroler' => $assure->peutEnroler(),
            'assureExport' => $this->formatAssureExport($assure),
        ]);
    }

    public function destroy(Dossier $dossier, DossierService $dossiers): RedirectResponse
    {
        $dossiers->deleteMembre(auth('assure')->user(), $dossier);

        return back()->with('success', 'Membre supprimé.');
    }

    public function submitComplement(
        Request $request,
        Dossier $dossier,
        DossierService $dossiers,
        DossierPieceStorageService $storage,
    ): RedirectResponse {
        $assure = auth('assure')->user();

        if ($dossier->assure_id !== $assure->id) {
            abort(403);
        }

        $missingTypes = collect($dossier->pieces ?? [])
            ->filter(fn ($p) => ($p['statut'] ?? '') === 'Manquante')
            ->pluck('type')
            ->values()
            ->all();

        $storedByType = [];

        if ($missingTypes !== []) {
            foreach ($missingTypes as $type) {
                $file = $this->complementFileForType($request, $type);
                if (! $file) {
                    throw ValidationException::withMessages([
                        "piece_files.{$type}" => "Le fichier pour « {$type} » est requis.",
                    ]);
                }
                $storedByType[$type] = $this->storeComplementPiece($storage, $assure, $file, $type);
            }
        } else {
            $request->validate([
                'complement_file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            ]);
            $type = config('cama.complement_generic_type', 'Pièce complémentaire');
            $storedByType[$type] = $this->storeComplementPiece(
                $storage,
                $assure,
                $request->file('complement_file'),
                $type,
            );
        }

        $dossiers->submitComplement($assure, $dossier, $storedByType);

        return back()->with('success', 'Pièces complémentaires envoyées.');
    }

    public function requestWithdrawal(Dossier $dossier, DossierService $dossiers): RedirectResponse
    {
        $dossiers->requestWithdrawal(auth('assure')->user(), $dossier);

        return back()->with('success', 'Demande de retrait envoyée au gestionnaire.');
    }

    private function complementFileForType(Request $request, string $type): ?UploadedFile
    {
        $files = $request->file('piece_files', []);

        if (is_array($files) && isset($files[$type]) && $files[$type] instanceof UploadedFile) {
            return $files[$type];
        }

        return $request->file("piece_files.{$type}");
    }

    /**
     * @return array<string, mixed>
     */
    private function storeComplementPiece(
        DossierPieceStorageService $storage,
        $assure,
        UploadedFile $file,
        string $type,
    ): array {
        $fifType = config('cama.fif_piece_type', 'FIF signée');

        if ($type === $fifType) {
            return $storage->storeLotPiece($assure, $file, $type);
        }

        return $storage->storeMemberPiece($assure, $file, $this->pieceKeyForType($type), $type);
    }

    private function pieceKeyForType(string $type): string
    {
        $labels = config('cama.piece_labels', []);
        $key = array_search($type, $labels, true);

        if ($key !== false) {
            return (string) $key;
        }

        return 'complement_'.Str::slug($type, '_');
    }

    private function formatAssureExport($assure): array
    {
        return [
            'nom' => $assure->nom,
            'prenom' => $assure->prenom,
            'prenoms' => $assure->prenom,
            'fullName' => $assure->full_name,
            'sexe' => $assure->sexe,
            'matricule' => $assure->matricule,
            'numeroInformatique' => $assure->numero_informatique,
            'grade' => $assure->grade,
            'categorie' => $assure->categorie,
            'numeroCim' => $assure->numero_cim,
            'numeroCama' => $assure->numero_cama,
            'numeroIup' => $assure->numero_iup,
            'armee' => $assure->armee,
            'region' => $assure->region,
            'corps' => $assure->corps,
            'service' => $assure->service,
            'section' => $assure->section,
            'sousSection' => $assure->sous_section,
            'telephone' => $assure->telephone,
            'email' => $assure->email,
            'personneAPrevenir' => $assure->personne_a_prevenir,
            'telPersonneAPrevenir' => $assure->tel_personne_a_prevenir,
        ];
    }
}
