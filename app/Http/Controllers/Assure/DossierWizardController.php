<?php

namespace App\Http\Controllers\Assure;

use App\Http\Controllers\Controller;
use App\Services\AssureDashboardService;
use App\Services\DossierPieceStorageService;
use App\Services\DossierService;
use App\Services\PlatformSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DossierWizardController extends Controller
{
    public function create(Request $request, AssureDashboardService $dashboard, DossierService $dossiers, PlatformSettingsService $settings): Response
    {
        $assure = auth('assure')->user();
        $initial = ['conjoints' => [], 'enfants' => []];
        $org = $settings->orgStructure();

        // Reprise complète : on recharge TOUS les brouillons de la famille (données + pièces déjà téléversées).
        $brouillons = $assure->dossiers()
            ->where('statut', 'Brouillon')
            ->orderBy('id')
            ->get();

        $validatedMembers = $assure->dossiers()
            ->where('statut', 'Validé')
            ->orderBy('id')
            ->get()
            ->map(fn ($d) => $dossiers->formatMembre($d))
            ->values()
            ->all();

        $hasValidatedMembers = count($validatedMembers) > 0;
        $lotType = $hasValidatedMembers ? 'complementaire' : 'initial';

        foreach ($brouillons as $dossier) {
            $formatted = $dossiers->formatForWizard($dossier);
            $lien = $dossier->lien ?? '';
            if (str_starts_with($lien, 'Enfant') || in_array($lien, ['Enfant biologique', 'Enfant du conjoint', 'Enfant adopté'], true)) {
                $initial['enfants'][] = $this->mapDossierToRow($formatted, 'enfants');
            } else {
                $initial['conjoints'][] = $this->mapDossierToRow($formatted, 'conjoints');
            }
        }

        $focusDraftId = $request->integer('draft') ?: null;
        if ($focusDraftId && ! $brouillons->contains('id', $focusDraftId)) {
            $focusDraftId = null;
        }

        return Inertia::render('Assure/Membres/Wizard', [
            'groupesSanguins' => $org['groupesSanguins'] ?? config('cama.groupes_sanguins'),
            'ageMaxEnfant' => $settings->ageMaxEnfant(),
            'piecesMatrix' => $settings->piecesFamilleMatrix(),
            'filiationOptions' => collect($settings->enfantFiliations())
                ->filter(fn (array $f) => $f['actif'] ?? false)
                ->pluck('label')
                ->values()
                ->all(),
            'certificatScolarite' => $settings->certificatScolarite(),
            'membrePhoto' => $settings->membrePhoto(),
            'fifSigneeRequise' => $settings->fifSigneeRequise(),
            'hasValidatedMembers' => $hasValidatedMembers,
            'validatedMembers' => $validatedMembers,
            'lotType' => $lotType,
            'parentLabel' => 'Nom et prénoms de la mère (du père si personnel féminin)',
            'unreadCount' => $dashboard->stats($assure)['unreadCount'],
            'peutEnroler' => $assure->peutEnroler(),
            'initialRows' => $initial,
            'focusDraftId' => $focusDraftId,
            'assureExport' => $this->formatAssureExport($assure),
            'fifPieceType' => config('cama.fif_piece_type', 'FIF signée'),
        ]);
    }

    public function storeDraft(Request $request, DossierService $service, DossierPieceStorageService $storage, PlatformSettingsService $settings): RedirectResponse
    {
        $assure = auth('assure')->user();
        $data = $this->validatedFamille($request, $settings);
        $data['conjoints'] = $this->hydratePieces($request, $assure, $storage, 'conjoints', $data['conjoints']);
        $data['enfants'] = $this->hydratePieces($request, $assure, $storage, 'enfants', $data['enfants']);
        $count = $service->saveFamilleDraft($assure, $data['conjoints'], $data['enfants']);

        return back()->with('success', $count > 0 ? 'Brouillon enregistré.' : 'Aucun membre à enregistrer.');
    }

    public function submit(Request $request, DossierService $service, DossierPieceStorageService $storage, PlatformSettingsService $settings): RedirectResponse
    {
        $assure = auth('assure')->user();
        $data = $this->validatedFamille($request, $settings, true);
        $data['conjoints'] = $this->hydratePieces($request, $assure, $storage, 'conjoints', $data['conjoints']);
        $data['enfants'] = $this->hydratePieces($request, $assure, $storage, 'enfants', $data['enfants']);

        $fifPiece = null;
        if ($settings->fifSigneeRequise()) {
            $request->validate([
                'fif_signee' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            ]);

            $fifPiece = $storage->storeLotPiece(
                $assure,
                $request->file('fif_signee'),
                config('cama.fif_piece_type', 'FIF signée'),
            );
        } elseif ($request->hasFile('fif_signee')) {
            $request->validate([
                'fif_signee' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            ]);
            $fifPiece = $storage->storeLotPiece(
                $assure,
                $request->file('fif_signee'),
                config('cama.fif_piece_type', 'FIF signée'),
            );
        }

        $count = $service->submitFamille($assure, $data['conjoints'], $data['enfants'], $fifPiece);

        return back()->with('famille_submitted', $count);
    }

    /**
     * Fusionne les pièces déjà stockées (chargées depuis la BD par id), applique les retraits
     * demandés et enregistre les nouveaux fichiers téléversés pour chaque membre.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function hydratePieces(Request $request, $assure, DossierPieceStorageService $storage, string $list, array $rows): array
    {
        $labels = config('cama.piece_labels', []);

        foreach ($rows as $i => $row) {
            $existing = [];
            if (! empty($row['id'])) {
                $dossier = $assure->dossiers()->where('id', $row['id'])->first();
                $existing = $dossier ? ($dossier->pieces ?? []) : [];
            }

            $removed = $row['removed_pieces'] ?? [];
            $existing = array_values(array_filter(
                $existing,
                fn ($p) => ! in_array($p['type'] ?? '', $removed, true),
            ));

            $files = $request->file("{$list}.{$i}.piece_files", []) ?? [];
            foreach ($files as $key => $file) {
                if (! $file) {
                    continue;
                }
                $type = $labels[$key] ?? $key;
                $stored = $storage->storeMemberPiece($assure, $file, $key, $type);
                $existing = array_values(array_filter($existing, fn ($p) => ($p['type'] ?? '') !== $type));
                $existing[] = $stored;
            }

            unset($row['piece_files'], $row['removed_pieces']);
            $row['pieces'] = $existing;
            $rows[$i] = $row;
        }

        return $rows;
    }

    private function validatedFamille(Request $request, PlatformSettingsService $settings, bool $forSubmit = false): array
    {
        $validated = $request->validate([
            'conjoints' => ['nullable', 'array'],
            'enfants' => ['nullable', 'array'],
            'conjoints.*.id' => ['nullable', 'integer', 'exists:dossiers,id'],
            'conjoints.*.nom' => ['nullable', 'string', 'max:100'],
            'conjoints.*.prenom' => ['nullable', 'string', 'max:150'],
            'conjoints.*.lien' => ['nullable', 'string', 'max:100'],
            'conjoints.*.sexe' => ['nullable', 'in:Masculin,Féminin'],
            'conjoints.*.date_naissance' => ['nullable', 'date'],
            'conjoints.*.lieu_naissance' => ['nullable', 'string', 'max:150'],
            'conjoints.*.groupe_sanguin' => ['nullable', 'string', 'max:10'],
            'conjoints.*.ref_identite' => ['nullable', 'string', 'max:150'],
            'conjoints.*.ref_acte_mariage' => ['nullable', 'string', 'max:150'],
            'conjoints.*.profession' => ['nullable', 'string', 'max:150'],
            'conjoints.*.lieu_residence' => ['nullable', 'string', 'max:150'],
            'conjoints.*.nationalite' => ['nullable', 'string', 'max:100'],
            'conjoints.*.telephone' => ['nullable', 'string', 'max:255'],
            'conjoints.*.removed_pieces' => ['nullable', 'array'],
            'conjoints.*.removed_pieces.*' => ['string', 'max:150'],
            'conjoints.*.piece_files' => ['nullable', 'array'],
            'conjoints.*.piece_files.*' => ['file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
            'enfants.*.id' => ['nullable', 'integer', 'exists:dossiers,id'],
            'enfants.*.nom' => ['nullable', 'string', 'max:100'],
            'enfants.*.prenom' => ['nullable', 'string', 'max:150'],
            'enfants.*.lien' => ['nullable', 'string', 'max:100'],
            'enfants.*.sexe' => ['nullable', 'in:Masculin,Féminin'],
            'enfants.*.date_naissance' => ['nullable', 'date'],
            'enfants.*.lieu_naissance' => ['nullable', 'string', 'max:150'],
            'enfants.*.groupe_sanguin' => ['nullable', 'string', 'max:10'],
            'enfants.*.ref_identite' => ['nullable', 'string', 'max:150'],
            'enfants.*.ref_acte_scolarite' => ['nullable', 'string', 'max:150'],
            'enfants.*.nom_prenoms_parent' => ['nullable', 'string', 'max:150'],
            'enfants.*.telephone' => ['nullable', 'string', 'max:255'],
            'enfants.*.removed_pieces' => ['nullable', 'array'],
            'enfants.*.removed_pieces.*' => ['string', 'max:150'],
            'enfants.*.piece_files' => ['nullable', 'array'],
            'enfants.*.piece_files.*' => ['file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $enfants = $validated['enfants'] ?? [];
        $conjoints = $validated['conjoints'] ?? [];

        if ($forSubmit) {
            $this->assertRequiredPieces($conjoints, $enfants, $settings, $request);
        }

        return [
            'conjoints' => $conjoints,
            'enfants' => $enfants,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $conjoints
     * @param  array<int, array<string, mixed>>  $enfants
     */
    private function assertRequiredPieces(array $conjoints, array $enfants, PlatformSettingsService $settings, Request $request): void
    {
        $matrix = $settings->piecesFamilleMatrix();
        $scolarite = $settings->certificatScolarite();
        $photos = $settings->membrePhoto();
        $ageMax = $settings->ageMaxEnfant();
        $labels = config('cama.piece_labels', []);

        $checkRow = function (array $row, string $list, int $index) use ($matrix, $scolarite, $photos, $ageMax, $labels, $request): void {
            $lien = $list === 'conjoints' ? 'Conjoint(e)' : ($row['lien'] ?? 'Enfant biologique');
            $piecesDef = $matrix[$lien] ?? [];

            if ($list === 'enfants' && ($scolarite['actif'] ?? false) && ! empty($row['date_naissance'])) {
                $age = \Carbon\Carbon::parse($row['date_naissance'])->age;
                if ($age >= $ageMax) {
                    $piecesDef[] = [
                        'key' => 'certificat_scolarite',
                        'label' => $scolarite['label'],
                        'required' => true,
                    ];
                }
            }

            $photoCfg = $list === 'conjoints' ? ($photos['conjoint'] ?? []) : ($photos['enfant'] ?? []);
            if (($photoCfg['actif'] ?? false) && ($photoCfg['required'] ?? false)) {
                $piecesDef[] = [
                    'key' => 'photo_membre',
                    'label' => $labels['photo_membre'] ?? 'Photo du membre',
                    'required' => true,
                ];
            }

            $existingTypes = [];
            if (! empty($row['id'])) {
                $dossier = auth('assure')->user()->dossiers()->find($row['id']);
                $existingTypes = collect($dossier?->pieces ?? [])->pluck('type')->all();
            }
            $removed = $row['removed_pieces'] ?? [];
            $files = $request->file("{$list}.{$index}.piece_files", []) ?? [];

            foreach ($piecesDef as $piece) {
                if (! ($piece['required'] ?? false)) {
                    continue;
                }
                $key = $piece['key'];
                $label = $piece['label'];
                $hasNew = ! empty($files[$key]);
                $kept = in_array($label, $existingTypes, true) && ! in_array($label, $removed, true);
                if (! $hasNew && ! $kept) {
                    throw ValidationException::withMessages([
                        "{$list}.{$index}.piece_files.{$key}" => "Pièce obligatoire manquante : {$label}.",
                    ]);
                }
            }
        };

        foreach ($conjoints as $i => $row) {
            if (! trim($row['nom'] ?? '') && ! trim($row['prenom'] ?? '')) {
                continue;
            }
            $checkRow($row, 'conjoints', $i);
        }
        foreach ($enfants as $i => $row) {
            if (! trim($row['nom'] ?? '') && ! trim($row['prenom'] ?? '')) {
                continue;
            }
            $checkRow($row, 'enfants', $i);
        }
    }

    private function mapDossierToRow(array $d, string $list): array
    {
        $meta = $d['wizard_meta'] ?? [];

        $base = [
            'id' => $d['id'],
            'nom' => $d['nom'],
            'prenom' => $d['prenom'],
            'sexe' => $d['sexe'],
            'date_naissance' => $d['dateNaissanceIso'] ?? null,
            'lieu_naissance' => $d['lieuNaissance'] ?? $meta['lieu_naissance'] ?? null,
            'groupe_sanguin' => $d['groupeSanguin'] ?? $meta['groupe_sanguin'] ?? null,
            'ref_identite' => $d['refIdentite'] ?? $meta['ref_identite'] ?? null,
            'telephone' => $d['telephone'] ?? $meta['telephone'] ?? null,
            'pieces' => $d['pieces'] ?? [],
        ];

        if ($list === 'conjoints') {
            return array_merge($base, [
                'lien' => 'Conjoint(e)',
                'ref_acte_mariage' => $d['refActeMariage'] ?? $meta['ref_acte_mariage'] ?? null,
                'profession' => $d['profession'] ?? $meta['profession'] ?? null,
                'lieu_residence' => $d['lieuResidence'] ?? $meta['lieu_residence'] ?? null,
                'nationalite' => $d['nationalite'] ?? $meta['nationalite'] ?? null,
            ]);
        }

        return array_merge($base, [
            'lien' => $d['lien'] ?? 'Enfant biologique',
            'ref_acte_scolarite' => $d['refActeScolariteEtatCivil'] ?? $meta['ref_acte_scolarite'] ?? null,
            'nom_prenoms_parent' => $d['nomPrenomsParent'] ?? $meta['nom_prenoms_parent'] ?? null,
        ]);
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
