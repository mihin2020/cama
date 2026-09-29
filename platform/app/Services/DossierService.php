<?php

namespace App\Services;

use App\Models\Assure;
use App\Models\AssureNotification;
use App\Models\Dossier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DossierService
{
    public function __construct(
        private DossierAffectationService $affectation,
        private PlatformSettingsService $settings,
    ) {}

    public function generateRef(): string
    {
        return 'CAMA-'.now()->year.'-'.random_int(10000, 99999);
    }

    public function appendJournal(Dossier $dossier, string $libelle): void
    {
        $journal = $dossier->journal ?? [];
        $journal[] = ['date' => now()->format('d/m/Y H:i'), 'libelle' => $libelle];
        $dossier->update(['journal' => $journal]);
    }

    public function saveDraft(Assure $assure, array $data): Dossier
    {
        if (! trim($data['prenom'] ?? '') && ! trim($data['nom'] ?? '')) {
            throw ValidationException::withMessages([
                'nom' => 'Indiquez au moins le prénom ou le nom du membre.',
            ]);
        }

        return DB::transaction(function () use ($assure, $data) {
            if (! empty($data['id'])) {
                $dossier = $assure->dossiers()->where('id', $data['id'])->firstOrFail();
                if ($dossier->statut !== 'Brouillon') {
                    throw ValidationException::withMessages([
                        'statut' => 'Seuls les brouillons peuvent être modifiés.',
                    ]);
                }
            } else {
                $dossier = new Dossier([
                    'assure_id' => $assure->id,
                    'ref' => $this->generateRef(),
                    'statut' => 'Brouillon',
                    'gestionnaire' => 'Non affecté',
                    'journal' => [],
                ]);
            }

            $dossier->fill([
                'nom' => $data['nom'] ?? '',
                'prenom' => $data['prenom'] ?? '',
                'lien' => $data['lien'] ?? '',
                'sexe' => $data['sexe'] ?? null,
                'date_naissance' => $data['date_naissance'] ?? null,
                'membre_numero_cama' => $data['membre_numero_cama'] ?? null,
                'pieces' => $data['pieces'] ?? [],
                'wizard_meta' => $data,
                'lot_id' => $data['lot_id'] ?? $dossier->lot_id,
                'lot_type' => $data['lot_type'] ?? $dossier->lot_type ?? 'initial',
            ]);

            $dossier->save();
            $this->appendJournal($dossier, empty($data['id']) ? 'Brouillon créé par l\'assuré' : 'Brouillon mis à jour par l\'assuré');

            return $dossier->fresh();
        });
    }

    public function submit(Assure $assure, array $data): Dossier
    {
        if (! $assure->peutEnroler()) {
            throw ValidationException::withMessages([
                'statut' => 'Votre compte doit être validé avant de soumettre un dossier.',
            ]);
        }

        return DB::transaction(function () use ($assure, $data) {
            $dossier = $this->saveDraft($assure, $data);

            $dossier->update([
                'statut' => 'Soumis',
                'date_soumission' => now()->toDateString(),
            ]);

            $this->appendJournal($dossier, 'Dossier soumis par l\'assuré');

            if ($gestionnaire = $this->affectation->autoAssign($dossier)) {
                $this->appendJournal($dossier, "Affectation automatique à {$gestionnaire} ({$this->affectation->modeLabel()})");
            }

            AssureNotification::query()->create([
                'assure_id' => $assure->id,
                'type' => 'soumission_dossier',
                'titre' => 'Dossier soumis',
                'contenu' => "Le dossier de {$dossier->beneficiaire} a été soumis (réf. {$dossier->ref}).",
                'lien' => route('assure.membres'),
                'lu' => false,
            ]);

            return $dossier->fresh();
        });
    }

    public function saveFamilleDraft(Assure $assure, array $conjoints, array $enfants): int
    {
        $count = 0;
        foreach ($conjoints as $row) {
            if ($this->isEmptyMemberRow($row)) {
                continue;
            }
            $this->saveDraft($assure, $this->normalizeMemberPayload($row, 'conjoints'));
            $count++;
        }
        foreach ($enfants as $row) {
            if ($this->isEmptyMemberRow($row)) {
                continue;
            }
            $this->saveDraft($assure, $this->normalizeMemberPayload($row, 'enfants'));
            $count++;
        }

        return $count;
    }

    public function submitFamille(Assure $assure, array $conjoints, array $enfants, ?array $fifPiece = null): int
    {
        if (! $assure->peutEnroler()) {
            throw ValidationException::withMessages([
                'statut' => 'Votre compte doit être validé avant de soumettre un dossier.',
            ]);
        }

        $fifRequired = $this->settings->fifSigneeRequise();
        if ($fifRequired && $fifPiece === null) {
            throw ValidationException::withMessages([
                'fif_signee' => 'La FIF signée (scan) est obligatoire pour soumettre le lot familial.',
            ]);
        }

        // Lot complémentaire si l'assuré a déjà ≥ 1 dossier Validé (membres acceptés).
        // Sinon lot initial (y compris reprise après refus / dossiers en cours uniquement).
        $lotType = $assure->dossiers()->where('statut', 'Validé')->exists()
            ? 'complementaire'
            : 'initial';
        $lotId = (string) \Illuminate\Support\Str::uuid();

        $count = 0;
        foreach ($conjoints as $row) {
            if ($this->isEmptyMemberRow($row)) {
                continue;
            }
            $payload = $this->normalizeMemberPayload($row, 'conjoints');
            if ($fifPiece !== null) {
                $payload['pieces'] = $this->mergeLotFifPiece($payload['pieces'] ?? [], $fifPiece);
            }
            $payload['lot_id'] = $lotId;
            $payload['lot_type'] = $lotType;
            $this->submit($assure, $payload);
            $count++;
        }
        foreach ($enfants as $row) {
            if ($this->isEmptyMemberRow($row)) {
                continue;
            }
            $payload = $this->normalizeMemberPayload($row, 'enfants');
            if ($fifPiece !== null) {
                $payload['pieces'] = $this->mergeLotFifPiece($payload['pieces'] ?? [], $fifPiece);
            }
            $payload['lot_id'] = $lotId;
            $payload['lot_type'] = $lotType;
            $this->submit($assure, $payload);
            $count++;
        }

        if ($count === 0) {
            throw ValidationException::withMessages([
                'membres' => 'Ajoutez au moins un membre à soumettre.',
            ]);
        }

        return $count;
    }

    /**
     * @param  array<int, array<string, mixed>>  $pieces
     * @param  array<string, mixed>  $fifPiece
     * @return array<int, array<string, mixed>>
     */
    private function mergeLotFifPiece(array $pieces, array $fifPiece): array
    {
        $type = config('cama.fif_piece_type', 'FIF signée');
        $filtered = array_values(array_filter($pieces, fn ($p) => ($p['type'] ?? '') !== $type));

        return array_merge($filtered, [$fifPiece]);
    }

    public function hasSignedLotFif(Dossier $dossier): bool
    {
        $type = config('cama.fif_piece_type', 'FIF signée');

        return collect($dossier->pieces ?? [])->contains(
            fn ($piece) => ($piece['type'] ?? '') === $type && ! empty($piece['path'])
        );
    }

    public function formatForWizard(Dossier $dossier): array
    {
        $meta = $dossier->wizard_meta ?? [];

        return array_merge($this->formatMembre($dossier), [
            'wizard_meta' => $meta,
            'dateNaissanceIso' => $dossier->date_naissance?->format('Y-m-d'),
        ]);
    }

    private function isEmptyMemberRow(array $row): bool
    {
        return ! trim($row['nom'] ?? '') && ! trim($row['prenom'] ?? '');
    }

    private function normalizeMemberPayload(array $row, string $list): array
    {
        $lien = $list === 'conjoints'
            ? 'Conjoint(e)'
            : ($row['lien'] ?? 'Enfant biologique');

        $wizardMeta = array_filter([
            'lieu_naissance' => $row['lieu_naissance'] ?? null,
            'groupe_sanguin' => $row['groupe_sanguin'] ?? null,
            'ref_identite' => $row['ref_identite'] ?? null,
            'ref_acte_mariage' => $row['ref_acte_mariage'] ?? null,
            'profession' => $row['profession'] ?? null,
            'lieu_residence' => $row['lieu_residence'] ?? null,
            'nationalite' => $row['nationalite'] ?? null,
            'ref_acte_scolarite' => $row['ref_acte_scolarite'] ?? null,
            'nom_prenoms_parent' => $row['nom_prenoms_parent'] ?? null,
            'telephone' => $row['telephone'] ?? null,
        ]);

        return [
            'id' => $row['id'] ?? null,
            'nom' => $row['nom'] ?? '',
            'prenom' => $row['prenom'] ?? '',
            'lien' => $lien,
            'sexe' => $row['sexe'] ?? null,
            'date_naissance' => $row['date_naissance'] ?? null,
            'pieces' => $row['pieces'] ?? [],
            'lot_id' => $row['lot_id'] ?? null,
            'lot_type' => $row['lot_type'] ?? null,
            ...$wizardMeta,
            'wizard_meta' => $wizardMeta,
        ];
    }

    public function formatMembre(Dossier $dossier): array
    {
        $meta = $dossier->wizard_meta ?? [];

        return [
            'id' => $dossier->id,
            'ref' => $dossier->ref,
            'dossierId' => $dossier->ref,
            'prenom' => $dossier->prenom,
            'nom' => $dossier->nom,
            'beneficiaire' => $dossier->beneficiaire,
            'lien' => $dossier->lien,
            'sexe' => $dossier->sexe,
            'statut' => $dossier->statut,
            'dateNaissance' => $dossier->date_naissance?->format('d/m/Y'),
            'dateSoumission' => $dossier->date_soumission?->format('d/m/Y'),
            'dateDecision' => $dossier->date_decision?->format('d/m/Y'),
            'motifRefus' => $dossier->motif_refus,
            'numeroCama' => $dossier->membre_numero_cama ?? ($meta['membre_numero_cama'] ?? null),
            'lieuNaissance' => $meta['lieu_naissance'] ?? $meta['lieuNaissance'] ?? null,
            'groupeSanguin' => $meta['groupe_sanguin'] ?? $meta['groupeSanguin'] ?? null,
            'telephone' => $meta['telephone'] ?? null,
            'refIdentite' => $meta['ref_identite'] ?? $meta['refIdentite'] ?? null,
            'refActeMariage' => $meta['ref_acte_mariage'] ?? $meta['refActeMariage'] ?? null,
            'refActeScolariteEtatCivil' => $meta['ref_acte_scolarite'] ?? $meta['refActeScolariteEtatCivil'] ?? null,
            'nomPrenomsParent' => $meta['nom_prenoms_parent'] ?? $meta['nomPrenomsParent'] ?? null,
            'profession' => $meta['profession'] ?? null,
            'lieuResidence' => $meta['lieu_residence'] ?? $meta['lieuResidence'] ?? null,
            'nationalite' => $meta['nationalite'] ?? null,
            'lotId' => $dossier->lot_id,
            'lotType' => $dossier->lot_type ?? 'initial',
            'isComplementFamilial' => ($dossier->lot_type ?? 'initial') === 'complementaire',
            'pieces' => collect($dossier->pieces ?? [])->map(function ($piece) {
                return array_merge($piece, [
                    'hasFile' => ! empty($piece['path']),
                ]);
            })->values()->all(),
            'messages' => $meta['messages'] ?? [],
            'journal' => $dossier->journal ?? [],
            'historique' => $dossier->journal ?? [],
            'initiales' => strtoupper(substr($dossier->prenom, 0, 1).substr($dossier->nom, 0, 1)),
        ];
    }

    public function deleteMembre(Assure $assure, Dossier $dossier): void
    {
        if ($dossier->assure_id !== $assure->id) {
            abort(403);
        }

        if ($dossier->statut !== 'Brouillon') {
            throw ValidationException::withMessages([
                'statut' => 'Seul un membre en brouillon peut être supprimé. Un dossier déjà soumis suit son instruction (un retrait est possible après validation).',
            ]);
        }

        $dossier->delete();
    }

    public function submitComplement(Assure $assure, Dossier $dossier, array $storedPiecesByType): void
    {
        if ($dossier->assure_id !== $assure->id) {
            abort(403);
        }

        if ($dossier->statut !== 'Pièce manquante demandée') {
            throw ValidationException::withMessages([
                'statut' => 'Aucune pièce complémentaire n\'est demandée pour ce dossier.',
            ]);
        }

        if ($storedPiecesByType === []) {
            throw ValidationException::withMessages([
                'pieces' => 'Joignez au moins un fichier.',
            ]);
        }

        $pieces = collect($dossier->pieces ?? []);

        foreach ($storedPiecesByType as $type => $stored) {
            $matched = false;
            $pieces = $pieces->map(function ($piece) use ($type, $stored, &$matched) {
                if (($piece['type'] ?? '') === $type) {
                    $matched = true;

                    return array_merge($piece, $stored, ['statut' => 'Soumise']);
                }

                return $piece;
            });

            if (! $matched) {
                $pieces->push(array_merge($stored, ['statut' => 'Soumise']));
            }
        }

        $stillMissing = $pieces->contains(fn ($p) => ($p['statut'] ?? '') === 'Manquante');
        $typesLabel = implode(', ', array_keys($storedPiecesByType));

        $dossier->update([
            'pieces' => $pieces->values()->all(),
            'statut' => $stillMissing ? 'Pièce manquante demandée' : 'En instruction',
        ]);

        $this->appendJournal(
            $dossier,
            $stillMissing
                ? "Pièce(s) complémentaire(s) partiellement envoyée(s) : {$typesLabel}"
                : "Pièces complémentaires envoyées par l'assuré : {$typesLabel}"
        );

        AssureNotification::query()->create([
            'assure_id' => $assure->id,
            'type' => 'piece_complementaire',
            'titre' => 'Complément envoyé',
            'contenu' => "Vos pièces pour {$dossier->beneficiaire} ont été transmises (réf. {$dossier->ref}).",
            'lien' => route('assure.membres'),
            'lu' => false,
        ]);
    }
}
