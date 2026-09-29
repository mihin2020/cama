<?php

namespace App\Services;

use App\Enums\AdminRole;
use App\Enums\AssureStatut;
use App\Models\AdminUser;
use App\Models\Dossier;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class AdminDossierService
{
    private const OPEN_STATUTS = [
        'Soumis',
        'En instruction',
        'Pièce manquante demandée',
        'En attente supervision',
    ];

    public function __construct(
        private DossierService $dossiers,
        private DossierAffectationService $affectation,
        private PlatformSettingsService $settings,
        private AdminAssureService $assures,
        private AssureNotificationDeliveryService $assureNotifications,
    ) {}

    public function gestionnaires(): array
    {
        $all = Dossier::query()->where('statut', '!=', 'Brouillon')->get();

        return collect($this->affectation->pool())->map(function ($nom) use ($all) {
            $mine = $all->where('gestionnaire', $nom);
            $open = $mine->whereIn('statut', self::OPEN_STATUTS);

            return [
                'nom' => $nom,
                'assignes' => $mine->count(),
                'nonTraites' => $open->count(),
                'enSupervision' => $mine->where('statut', 'En attente supervision')->count(),
                'valides' => $mine->where('statut', 'Validé')->count(),
                'derniereAffectation' => $this->derniereAffectation($mine),
            ];
        })->all();
    }

    public function nonAffectesCount(): int
    {
        return Dossier::query()
            ->where('statut', '!=', 'Brouillon')
            ->where(fn ($q) => $q->whereNull('gestionnaire')->orWhereIn('gestionnaire', ['', 'Non affecté']))
            ->count();
    }

    /** Statistiques personnelles du gestionnaire connecté sur ses dossiers affectés. */
    public function statsPourGestionnaire(AdminUser $admin): array
    {
        $mine = Dossier::query()
            ->where('statut', '!=', 'Brouillon')
            ->where('gestionnaire', $admin->display_name)
            ->get();

        return [
            'total' => $mine->count(),
            'aTraiter' => $mine->whereIn('statut', ['Soumis', 'En instruction'])->count(),
            'complement' => $mine->where('statut', 'Pièce manquante demandée')->count(),
            'enSupervision' => $mine->where('statut', 'En attente supervision')->count(),
            'valides' => $mine->where('statut', 'Validé')->count(),
            'refuses' => $mine->where('statut', 'Refusé')->count(),
            'derniereAffectation' => $this->derniereAffectation($mine),
        ];
    }

    /** Plus récente date d'affectation (affichage d/m/Y H:i) parmi une collection de dossiers. */
    private function derniereAffectation(Collection $dossiers): ?string
    {
        return $dossiers
            ->map(fn ($d) => $this->affectationDate($d))
            ->filter()
            ->sortByDesc(function (string $date) {
                try {
                    return \Carbon\Carbon::createFromFormat('d/m/Y H:i', $date)->getTimestamp();
                } catch (\Throwable) {
                    return 0;
                }
            })
            ->first();
    }

    /**
     * Date (d/m/Y H:i) de la dernière affectation tracée dans le journal du dossier,
     * ou la date de soumission à défaut (dossiers antérieurs à la traçabilité).
     */
    private function affectationDate(Dossier $dossier): ?string
    {
        $entry = collect($dossier->journal ?? [])
            ->last(fn ($j) => str_starts_with($j['libelle'] ?? '', 'Affecté à')
                || str_starts_with($j['libelle'] ?? '', 'Affectation automatique à'));

        if ($entry) {
            return $entry['date'] ?? null;
        }

        return $dossier->gestionnaire && $dossier->gestionnaire !== 'Non affecté'
            ? $dossier->date_soumission?->format('d/m/Y H:i')
            : null;
    }

    public function scopedDossiers(?AdminUser $admin): Collection
    {
        $query = Dossier::query()
            ->with('assure')
            ->where('statut', '!=', 'Brouillon')
            ->orderByDesc('date_soumission');

        if ($admin && $admin->role === AdminRole::Gestionnaire) {
            $query->where('gestionnaire', $admin->display_name);
        }

        return $query->get();
    }

    /**
     * Identifiants assurés ayant au moins un dossier ouvert (file active).
     *
     * @return array<int, int>
     */
    public function pendingFamilyAssureIds(?AdminUser $admin): array
    {
        $query = Dossier::query()
            ->where('statut', '!=', 'Brouillon')
            ->whereIn('statut', self::OPEN_STATUTS);

        if ($admin && $admin->role === AdminRole::Gestionnaire) {
            $query->where('gestionnaire', $admin->display_name);
        }

        return $query->distinct()
            ->pluck('assure_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    public function groupFamilies(Collection $dossiers): array
    {
        return $dossiers->groupBy('assure_id')->map(function (Collection $items) {
            $assure = $items->first()->assure;
            $sorted = $items->sortByDesc('date_soumission')->values();
            $submitted = $sorted->where('statut', '!=', 'Brouillon');
            $pending = $submitted->whereIn('statut', self::OPEN_STATUTS)->count();
            $gestionnaire = $sorted->first(fn ($d) => $d->gestionnaire && $d->gestionnaire !== 'Non affecté')?->gestionnaire
                ?? $sorted->first()?->gestionnaire
                ?? 'Non affecté';

            $lastJournal = $sorted
                ->flatMap(fn ($d) => collect($d->journal ?? [])->map(fn ($j) => array_merge($j, ['ref' => $d->ref])))
                ->sortByDesc('date')
                ->first();

            $allValidated = $submitted->isNotEmpty() && $submitted->every(fn ($d) => $d->statut === 'Validé');
            $hasRefus = $submitted->contains(fn ($d) => $d->statut === 'Refusé');
            $hasComplement = $submitted->contains(fn ($d) => $d->statut === 'Pièce manquante demandée');
            $hasOpen = $submitted->contains(fn ($d) => in_array($d->statut, self::OPEN_STATUTS, true));

            // Ajout de membre(s) sur un dossier déjà validé : l'assuré est déjà pris
            // en charge (≥1 membre validé) et un lot complémentaire est en cours d'instruction.
            $hasValidated = $submitted->contains(fn ($d) => $d->statut === 'Validé');
            $ajoutEnCoursCount = $submitted
                ->filter(fn ($d) => in_array($d->statut, self::OPEN_STATUTS, true) && ($d->lot_type ?? 'initial') === 'complementaire')
                ->count();
            $ajoutEnCours = $hasValidated && $ajoutEnCoursCount > 0;

            $queue = $allValidated ? 'valides'
                : ($ajoutEnCours ? 'ajout'
                : ($hasRefus && ! $hasOpen ? 'refuses'
                : ($hasComplement ? 'complement' : 'a_traiter')));

            return [
                'assureId' => $assure->id,
                'assureNom' => $assure->full_name,
                'matricule' => $assure->matricule,
                'numeroCama' => $assure->numero_cama,
                'gestionnaire' => $gestionnaire,
                'total' => $sorted->count(),
                'pending' => $pending,
                'queue' => $queue,
                'hasValidated' => $hasValidated,
                'ajoutEnCours' => $ajoutEnCours,
                'ajoutEnCoursCount' => $ajoutEnCoursCount,
                'validesCount' => $submitted->where('statut', 'Validé')->count(),
                'lastTraitement' => $lastJournal['date'] ?? '',
                'lastTraitementLibelle' => $lastJournal['libelle'] ?? '',
                'latestDate' => $sorted->first()?->date_soumission?->format('Y-m-d') ?? '',
                'initiales' => $assure->initiales,
                'assureProfile' => $this->assures->profileForAdmin($assure),
                'exportProfile' => $this->assures->profileForAdmin($assure),
                'membresExport' => $submitted
                    ->map(fn ($d) => $this->dossiers->formatMembre($d))
                    ->values()
                    ->all(),
                'dossiers' => $sorted->map(fn ($d) => $this->formatDossierRow($d))->values()->all(),
            ];
        })->sortByDesc('latestDate')->values()->all();
    }

    public function queueCounts(array $families): array
    {
        $counts = ['a_traiter' => 0, 'ajout' => 0, 'valides' => 0, 'refuses' => 0, 'complement' => 0];
        foreach ($families as $family) {
            $q = $family['queue'] ?? 'a_traiter';
            if (isset($counts[$q])) {
                $counts[$q]++;
            }
        }

        return $counts;
    }

    public function formatDossierDetail(Dossier $dossier): array
    {
        $formatted = $this->dossiers->formatMembre($dossier);

        return array_merge($formatted, [
            'assureNom' => $dossier->assure->full_name,
            'assureId' => $dossier->assure_id,
            'gestionnaire' => $dossier->gestionnaire,
            'messages' => $dossier->wizard_meta['messages'] ?? [],
            'canValidate' => $this->canValidate($dossier),
        ]);
    }

    public function assignFamily(int $assureId, string $gestionnaire, ?AdminUser $admin = null): void
    {
        $gestionnaire = $gestionnaire ?: 'Non affecté';

        Dossier::query()
            ->where('assure_id', $assureId)
            ->where('statut', '!=', 'Brouillon')
            ->where(fn ($q) => $q->whereNull('gestionnaire')->orWhere('gestionnaire', '!=', $gestionnaire))
            ->get()
            ->each(function (Dossier $dossier) use ($gestionnaire, $admin) {
                $dossier->update(['gestionnaire' => $gestionnaire]);

                $par = $admin ? " par {$admin->display_name}" : '';
                $this->dossiers->appendJournal(
                    $dossier,
                    $gestionnaire === 'Non affecté' ? "Affectation retirée{$par}" : "Affecté à {$gestionnaire}{$par}"
                );
            });
    }

    public function batchAssign(array $assureIds, string $gestionnaire, ?AdminUser $admin = null): int
    {
        $count = 0;
        foreach ($assureIds as $assureId) {
            $this->assignFamily((int) $assureId, $gestionnaire, $admin);
            $count++;
        }

        return $count;
    }

    public function validate(Dossier $dossier, ?AdminUser $admin = null): Dossier
    {
        if (in_array($dossier->statut, ['Validé', 'Refusé', 'Brouillon'], true)) {
            throw ValidationException::withMessages(['statut' => 'Ce dossier ne peut plus être validé.']);
        }

        if ($this->settings->fifSigneeRequise() && ! $this->dossiers->hasSignedLotFif($dossier)) {
            throw ValidationException::withMessages([
                'fif' => 'La FIF signée du lot familial est absente : demandez un complément à l\'assuré avant de valider.',
            ]);
        }

        $deuxNiveaux = $this->settings->validation2Niveaux();
        $estGestionnaire = $admin?->role === AdminRole::Gestionnaire;

        if ($dossier->statut === 'En attente supervision' && $estGestionnaire) {
            throw ValidationException::withMessages([
                'statut' => 'Ce dossier attend la validation finale du superviseur : un gestionnaire ne peut pas le valider.',
            ]);
        }

        // Validation à deux niveaux : la validation du gestionnaire (niveau 1)
        // transmet le dossier à la supervision au lieu de le valider définitivement.
        if ($deuxNiveaux && $estGestionnaire) {
            $dossier->update(['statut' => 'En attente supervision']);
            $this->dossiers->appendJournal($dossier, "Validation niveau 1 par {$admin->display_name} — transmis à la supervision");

            return $dossier->fresh();
        }

        $niveau2 = $dossier->statut === 'En attente supervision';

        $dossier->update([
            'statut' => 'Validé',
            'date_decision' => now()->toDateString(),
        ]);

        $auteur = $admin?->display_name ?? 'le gestionnaire';
        $this->dossiers->appendJournal(
            $dossier,
            $niveau2 ? "Validation finale (niveau 2) par {$auteur}" : "Dossier validé par {$auteur}"
        );

        $this->notifyAssure(
            $dossier,
            'validation',
            'Dossier validé',
            $this->settings->renderEmailTemplate('validation', [
                'ref' => $dossier->ref ?? (string) $dossier->id,
                'beneficiaire' => $dossier->beneficiaire,
            ]) ?: "Le dossier de {$dossier->beneficiaire} a été validé.",
        );

        return $dossier->fresh();
    }

    public function refuse(Dossier $dossier, string $motif): Dossier
    {
        $dossier->update([
            'statut' => 'Refusé',
            'motif_refus' => $motif,
            'date_decision' => now()->toDateString(),
        ]);

        $this->dossiers->appendJournal($dossier, "Dossier refusé : {$motif}");

        $contenu = $this->settings->renderEmailTemplate('refus', [
            'ref' => $dossier->ref ?? (string) $dossier->id,
            'beneficiaire' => $dossier->beneficiaire,
            'motif' => $motif,
        ]) ?: "Le dossier de {$dossier->beneficiaire} a été refusé : {$motif}";

        $this->notifyAssure($dossier, 'refus', 'Dossier refusé', $contenu);

        return $dossier->fresh();
    }

    public function requestComplement(Dossier $dossier, array $pieceTypes, ?string $message = null): Dossier
    {
        $message = trim($message ?? '');
        $pieceTypes = array_values(array_filter($pieceTypes));

        if ($pieceTypes === [] && $message === '') {
            throw ValidationException::withMessages([
                'complement' => 'Indiquez au moins une pièce à demander ou un message à l\'assuré.',
            ]);
        }

        $pieces = collect($dossier->pieces ?? []);
        $updated = $pieces->map(function ($piece) use ($pieceTypes) {
            if (in_array($piece['type'] ?? '', $pieceTypes, true)) {
                $piece['statut'] = 'Manquante';
            }

            return $piece;
        });

        foreach ($pieceTypes as $type) {
            if (! $updated->contains(fn ($p) => ($p['type'] ?? '') === $type)) {
                $updated->push(['type' => $type, 'statut' => 'Manquante']);
            }
        }

        $updateData = [
            'statut' => 'Pièce manquante demandée',
            'pieces' => $updated->values()->all(),
        ];

        if ($message !== '') {
            $meta = $dossier->wizard_meta ?? [];
            $messages = $meta['messages'] ?? [];
            $messages[] = [
                'auteur' => 'gestionnaire',
                'texte' => $message,
                'date' => now()->format('d/m/Y H:i'),
            ];
            $meta['messages'] = $messages;
            $updateData['wizard_meta'] = $meta;
        }

        $dossier->update($updateData);

        $libelle = $pieceTypes
            ? 'Pièce complémentaire demandée : '.implode(', ', $pieceTypes)
            : 'Demande de complément';
        if ($message !== '') {
            $libelle .= $pieceTypes ? " — {$message}" : " : {$message}";
        }
        $this->dossiers->appendJournal($dossier, $libelle);

        $piecesLabel = $pieceTypes ? implode(', ', $pieceTypes) : 'complément';
        $contenu = $message ?: $this->settings->renderEmailTemplate('complement', [
            'ref' => $dossier->ref ?? (string) $dossier->id,
            'piece' => $piecesLabel,
            'beneficiaire' => $dossier->beneficiaire,
        ]);

        $this->notifyAssure(
            $dossier,
            'complement',
            'Pièce complémentaire demandée',
            $contenu ?: "Complément demandé pour {$dossier->beneficiaire} : {$piecesLabel}"
        );

        return $dossier->fresh();
    }

    public function setEnAttente(Dossier $dossier): Dossier
    {
        $dossier->update(['statut' => 'En attente supervision']);
        $this->dossiers->appendJournal($dossier, 'Passage en attente de supervision');

        return $dossier->fresh();
    }

    public function sendMessage(Dossier $dossier, string $text, string $auteur = 'gestionnaire'): Dossier
    {
        $meta = $dossier->wizard_meta ?? [];
        $messages = $meta['messages'] ?? [];
        $messages[] = [
            'auteur' => $auteur,
            'texte' => $text,
            'date' => now()->format('d/m/Y H:i'),
        ];
        $meta['messages'] = $messages;
        $dossier->update(['wizard_meta' => $meta]);

        if ($auteur === 'gestionnaire') {
            $contenu = $this->settings->renderEmailTemplate('message', [
                'ref' => $dossier->ref ?? (string) $dossier->id,
                'beneficiaire' => $dossier->beneficiaire,
                'message' => $text,
            ]) ?: $text;

            $this->notifyAssure(
                $dossier,
                'message',
                'Message du gestionnaire CAMA',
                $contenu,
                route('assure.membres'),
            );
        }

        return $dossier->fresh();
    }

    private function canValidate(Dossier $dossier): bool
    {
        return ! in_array($dossier->statut, ['Validé', 'Refusé', 'Brouillon', 'En attente supervision'], true);
    }

    private function formatDossierRow(Dossier $dossier): array
    {
        $meta = $dossier->wizard_meta ?? [];

        $pieces = collect($dossier->pieces ?? [])->map(function ($piece) {
            return array_merge($piece, [
                'hasFile' => ! empty($piece['path']),
                'lot' => (bool) ($piece['lot'] ?? false),
            ]);
        })->values()->all();

        return array_merge($this->dossiers->formatMembre($dossier), [
            'beneficiaire' => $dossier->beneficiaire,
            'assureNom' => $dossier->assure->full_name,
            'gestionnaire' => $dossier->gestionnaire,
            'dateSoumission' => $dossier->date_soumission?->format('Y-m-d'),
            'dateSoumissionFr' => $dossier->date_soumission?->format('d/m/Y'),
            'affecteLe' => $this->affectationDate($dossier),
            'messages' => $meta['messages'] ?? [],
            'pieces' => $pieces,
            'hasLotFif' => $this->dossiers->hasSignedLotFif($dossier),
        ]);
    }

    private function notifyAssure(Dossier $dossier, string $event, string $titre, string $contenu, ?string $lien = null): void
    {
        $this->assureNotifications->notify(
            $dossier->assure,
            $event,
            $titre,
            $contenu,
            $lien ?? route('assure.membres'),
        );
    }
}
