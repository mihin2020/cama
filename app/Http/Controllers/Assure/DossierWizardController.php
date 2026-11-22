<?php

namespace App\Http\Controllers\Assure;

use App\Http\Controllers\Controller;
use App\Services\AssureDashboardService;
use App\Services\DossierPieceStorageService;
use App\Services\DossierService;
use App\Services\FifProfileService;
use App\Services\PlatformSettingsService;
use App\Rules\MemberPieceFile;
use App\Rules\PdfDocument;
use App\Support\UploadFilename;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DossierWizardController extends Controller
{
    public function create(Request $request, AssureDashboardService $dashboard, DossierService $dossiers, PlatformSettingsService $settings, FifProfileService $fif): Response
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

        $focusDraftId = (string) $request->query('draft', '') ?: null;
        if ($focusDraftId && ! $brouillons->contains('uuid', $focusDraftId)) {
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
            // Sections et champs ajoutés par l'administration pour chaque type de membre.
            'fifSchema' => [
                'conjoint' => app(\App\Services\FifSchemaService::class)->schema('conjoint'),
                'enfant' => app(\App\Services\FifSchemaService::class)->schema('enfant'),
            ],
            'hasValidatedMembers' => $hasValidatedMembers,
            'validatedMembers' => $validatedMembers,
            'lotType' => $lotType,
            'parentLabel' => 'Nom et prénoms de la mère (du père si personnel féminin)',
            'unreadCount' => $dashboard->stats($assure)['unreadCount'],
            'peutEnroler' => $assure->peutEnroler(),
            'initialRows' => $initial,
            'focusDraftId' => $focusDraftId,
            'assureExport' => $fif->profile($assure),
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
                'fif_signee' => ['required', new PdfDocument],
            ]);

            $fifPiece = $storage->storeLotPiece(
                $assure,
                $request->file('fif_signee'),
                config('cama.fif_piece_type', 'FIF signée'),
            );
        } elseif ($request->hasFile('fif_signee')) {
            $request->validate([
                'fif_signee' => [new PdfDocument],
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
                $dossier = $assure->dossiers()->where('uuid', $row['id'])->first();
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
                $stored = $storage->storeMemberPiece(
                    $assure,
                    $file,
                    $key,
                    $type,
                    UploadFilename::memberCode($list === 'conjoints', (int) $i + 1),
                );
                $existing = array_values(array_filter($existing, fn ($p) => ($p['type'] ?? '') !== $type));
                $existing[] = $stored;
            }

            // Champs « fichier » du formulaire FIF : le nouveau fichier remplace l'ancien.
            $cible = $list === 'conjoints' ? 'conjoint' : 'enfant';
            foreach (app(\App\Services\FifSchemaService::class)->fields($cible) as $field) {
                if ($field['type'] === 'fichier' && ($file = $request->file("{$list}.{$i}.fif.{$field['code']}"))) {
                    $storage->deleteStored($row['fif_donnees'][$field['code']]['path'] ?? null);
                    $row['fif_donnees'][$field['code']] = $storage->storeFifFile(
                        $assure,
                        $file,
                        $field['libelle'],
                        UploadFilename::memberCode($list === 'conjoints', (int) $i + 1),
                        "{$list}.{$i}.fif.{$field['code']}",
                    );
                }
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
            'conjoints.*.id' => ['nullable', 'uuid', 'exists:dossiers,uuid'],
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
            'conjoints.*.piece_files.*' => [new MemberPieceFile],
            'conjoints.*.fif' => ['nullable', 'array'],
            'enfants.*.id' => ['nullable', 'uuid', 'exists:dossiers,uuid'],
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
            'enfants.*.piece_files.*' => [new MemberPieceFile],
            'enfants.*.fif' => ['nullable', 'array'],
            // Champs ajoutés par l'administration : leur type est contrôlé ici ; leur caractère
            // obligatoire l'est à la soumission, pour les membres réellement renseignés.
            ...app(\App\Services\FifSchemaService::class)->rules('conjoint', 'conjoints.*.fif', false),
            ...app(\App\Services\FifSchemaService::class)->rules('enfant', 'enfants.*.fif', false),
        ]);

        $enfants = $this->withCleanFif($validated['enfants'] ?? [], 'enfant', 'enfants', $forSubmit, $request);
        $conjoints = $this->withCleanFif($validated['conjoints'] ?? [], 'conjoint', 'conjoints', $forSubmit, $request);

        if ($forSubmit) {
            $this->assertRequiredPieces($conjoints, $enfants, $settings, $request);
        }

        return [
            'conjoints' => $conjoints,
            'enfants' => $enfants,
        ];
    }

    /**
     * Remplace « fif » (saisie) par « fif_donnees » (valeurs nettoyées, champs visibles seulement) et,
     * à la soumission, exige les champs obligatoires des membres réellement renseignés.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function withCleanFif(array $rows, string $cible, string $list, bool $forSubmit, Request $request): array
    {
        $fif = app(\App\Services\FifSchemaService::class);

        foreach ($rows as $i => $row) {
            $values = $fif->clean($cible, $row['fif'] ?? []);
            unset($row['fif']);

            // Champs « fichier » : un fichier déjà enregistré reste valable tant qu'on n'en envoie pas un nouveau.
            $existing = ! empty($row['id']) ? (auth('assure')->user()->dossiers()->where('uuid', $row['id'])->first()?->fif_donnees ?? []) : [];
            $filesPresent = [];
            foreach ($fif->fields($cible) as $field) {
                if ($field['type'] !== 'fichier') {
                    continue;
                }
                if (! empty($existing[$field['code']]['path'])) {
                    $values[$field['code']] = $existing[$field['code']];
                    $filesPresent[] = $field['code'];
                }
                if ($request->hasFile("{$list}.{$i}.fif.{$field['code']}")) {
                    $filesPresent[] = $field['code'];
                }
            }
            $row['fif_donnees'] = $values;

            $filled = trim($row['nom'] ?? '') !== '' || trim($row['prenom'] ?? '') !== '';
            if ($forSubmit && $filled && ($missing = $fif->missingRequired($cible, $values, $filesPresent)) !== []) {
                throw ValidationException::withMessages([
                    "{$list}.{$i}.fif" => 'Champ(s) obligatoire(s) manquant(s) : '.implode(', ', $missing).'.',
                ]);
            }

            $rows[$i] = $row;
        }

        return $rows;
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
                $age = Carbon::parse($row['date_naissance'])->age;
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
                $dossier = auth('assure')->user()->dossiers()->where('uuid', $row['id'])->first();
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
            'id' => $d['uuid'],
            'nom' => $d['nom'],
            'prenom' => $d['prenom'],
            'sexe' => $d['sexe'],
            'date_naissance' => $d['dateNaissanceIso'] ?? null,
            'lieu_naissance' => $d['lieuNaissance'] ?? $meta['lieu_naissance'] ?? null,
            'groupe_sanguin' => $d['groupeSanguin'] ?? $meta['groupe_sanguin'] ?? null,
            'ref_identite' => $d['refIdentite'] ?? $meta['ref_identite'] ?? null,
            'telephone' => $d['telephone'] ?? $meta['telephone'] ?? null,
            'pieces' => $d['pieces'] ?? [],
            'fif' => $d['fifDonnees'] ?? [],
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
}
