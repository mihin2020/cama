<?php

namespace App\Services;

use App\Models\AdminUser;
use App\Models\Dossier;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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
        $base = fn () => Dossier::query()->where('statut', '!=', 'Brouillon');

        // Comptages par gestionnaire en agrégations SQL (au lieu de charger tous les dossiers).
        $assignes = $base()->selectRaw('gestionnaire, count(*) c')->groupBy('gestionnaire')->pluck('c', 'gestionnaire');
        $open = $base()->whereIn('statut', self::OPEN_STATUTS)->selectRaw('gestionnaire, count(*) c')->groupBy('gestionnaire')->pluck('c', 'gestionnaire');
        $supervision = $base()->where('statut', 'En attente supervision')->selectRaw('gestionnaire, count(*) c')->groupBy('gestionnaire')->pluck('c', 'gestionnaire');
        $valides = $base()->where('statut', 'Validé')->selectRaw('gestionnaire, count(*) c')->groupBy('gestionnaire')->pluck('c', 'gestionnaire');

        // Dernière affectation : bornée aux 150 dossiers récemment mis à jour.
        $recentByGest = $base()
            ->orderByDesc('updated_at')
            ->limit(150)
            ->get(['gestionnaire', 'journal', 'date_soumission'])
            ->groupBy('gestionnaire');

        $assignables = $this->affectation->assignablePool();

        return collect($this->affectation->pool())->map(fn ($nom) => [
            'nom' => $nom,
            // false = compte désactivé ou inexistant qui détient encore des dossiers en cours (à réaffecter).
            'actif' => in_array($nom, $assignables, true),
            'assignes' => (int) ($assignes[$nom] ?? 0),
            'nonTraites' => (int) ($open[$nom] ?? 0),
            'enSupervision' => (int) ($supervision[$nom] ?? 0),
            'valides' => (int) ($valides[$nom] ?? 0),
            'derniereAffectation' => $this->derniereAffectation($recentByGest->get($nom, collect())),
        ])->all();
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
        $base = fn () => Dossier::query()
            ->where('statut', '!=', 'Brouillon')
            ->where('gestionnaire', $admin->display_name);

        $byStatut = $base()
            ->selectRaw('statut, count(*) c')
            ->groupBy('statut')
            ->pluck('c', 'statut')
            ->map(fn ($c) => (int) $c);

        $recent = $base()->orderByDesc('updated_at')->limit(150)->get(['gestionnaire', 'journal', 'date_soumission']);

        return [
            'total' => (int) $byStatut->sum(),
            'aTraiter' => (int) (($byStatut['Soumis'] ?? 0) + ($byStatut['En instruction'] ?? 0)),
            'complement' => (int) ($byStatut['Pièce manquante demandée'] ?? 0),
            'enSupervision' => (int) ($byStatut['En attente supervision'] ?? 0),
            'valides' => (int) ($byStatut['Validé'] ?? 0),
            'refuses' => (int) ($byStatut['Refusé'] ?? 0),
            'derniereAffectation' => $this->derniereAffectation($recent),
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
                    return Carbon::createFromFormat('d/m/Y H:i', $date)->getTimestamp();
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

        if ($this->seesOnlyOwnDossiers($admin)) {
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

        if ($this->seesOnlyOwnDossiers($admin)) {
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

            // Les membres retirés ne comptent plus dans l'état global de la famille.
            $effective = $submitted->where('statut', '!=', 'Retiré');
            $allValidated = $effective->isNotEmpty() && $effective->every(fn ($d) => $d->statut === 'Validé');
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
        $gestionnaire = trim($gestionnaire) ?: DossierAffectationService::NON_AFFECTE;

        if ($gestionnaire !== DossierAffectationService::NON_AFFECTE && ! $this->affectation->isAssignable($gestionnaire)) {
            throw ValidationException::withMessages([
                'gestionnaire' => "« {$gestionnaire} » n'est pas un gestionnaire actif : choisissez un compte actif dans la liste.",
            ]);
        }

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
        // Tout le lot ou rien : une erreur en cours de route n'en laisse pas une moitié affectée.
        return DB::transaction(function () use ($assureIds, $gestionnaire, $admin) {
            $count = 0;
            foreach ($assureIds as $assureId) {
                $this->assignFamily((int) $assureId, $gestionnaire, $admin);
                $count++;
            }

            return $count;
        });
    }

    public function validate(Dossier $dossier, ?AdminUser $admin = null): Dossier
    {
        return $this->decide($dossier, $admin, 'valider', function (Dossier $dossier) use ($admin) {
            if ($this->settings->fifSigneeRequise() && ! $this->dossiers->hasSignedLotFif($dossier)) {
                throw ValidationException::withMessages([
                    'fif' => 'La FIF signée du lot familial est absente : demandez un complément à l\'assuré avant de valider.',
                ]);
            }

            // Validation à deux niveaux : la validation du gestionnaire (niveau 1)
            // transmet le dossier à la supervision au lieu de le valider définitivement.
            if ($this->settings->validation2Niveaux() && $this->isGestionnaire($admin)) {
                $dossier->update(['statut' => 'En attente supervision']);
                $this->dossiers->appendJournal($dossier, "Validation niveau 1 par {$admin->display_name} — transmis à la supervision");

                return;
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

            $this->notifyAssureAfterCommit(
                $dossier,
                'validation',
                'Dossier validé',
                $this->settings->renderEmailTemplate('validation', [
                    'ref' => $dossier->ref ?? (string) $dossier->id,
                    'beneficiaire' => $dossier->beneficiaire,
                ]) ?: "Le dossier de {$dossier->beneficiaire} a été validé.",
            );
        });
    }

    /**
     * Retrait d'un membre par le gestionnaire / superviseur
     * (ex. devenu militaire → assuré à part entière).
     * Le membre passe en « Retiré » : il n'est plus couvert comme ayant droit.
     * Possible aussi sur un dossier validé : c'est le cas d'usage principal.
     */
    public function withdraw(Dossier $dossier, string $motif, ?AdminUser $admin = null): Dossier
    {
        $motif = trim($motif) ?: 'Retrait';

        return DB::transaction(function () use ($dossier, $motif, $admin) {
            $dossier = $this->lockDossier($dossier);

            if (in_array($dossier->statut, ['Brouillon', 'Retiré'], true)) {
                throw ValidationException::withMessages([
                    'statut' => $dossier->statut === 'Retiré'
                        ? 'Ce membre a déjà été retiré.'
                        : 'Un brouillon ne peut pas être retiré : l\'assuré ne l\'a pas encore soumis.',
                ]);
            }

            $dossier->update([
                'statut' => 'Retiré',
                'date_decision' => now()->toDateString(),
            ]);

            $auteur = $admin?->display_name ?? 'le gestionnaire';
            $this->dossiers->appendJournal($dossier, "Membre retiré par {$auteur} — {$motif}");

            $this->notifyAssureAfterCommit(
                $dossier,
                'validation',
                'Membre retiré',
                "{$dossier->beneficiaire} a été retiré de votre dossier familial ({$motif}). Ce membre n'est plus couvert comme ayant droit.",
            );

            return $dossier->fresh();
        });
    }

    public function refuse(Dossier $dossier, string $motif, ?AdminUser $admin = null): Dossier
    {
        return $this->decide($dossier, $admin, 'refuser', function (Dossier $dossier) use ($motif, $admin) {
            $dossier->update([
                'statut' => 'Refusé',
                'motif_refus' => $motif,
                'date_decision' => now()->toDateString(),
            ]);

            $par = $admin ? " par {$admin->display_name}" : '';
            $this->dossiers->appendJournal($dossier, "Dossier refusé{$par} : {$motif}");

            $contenu = $this->settings->renderEmailTemplate('refus', [
                'ref' => $dossier->ref ?? (string) $dossier->id,
                'beneficiaire' => $dossier->beneficiaire,
                'motif' => $motif,
            ]) ?: "Le dossier de {$dossier->beneficiaire} a été refusé : {$motif}";

            $this->notifyAssureAfterCommit($dossier, 'refus', 'Dossier refusé', $contenu);
        });
    }

    public function requestComplement(Dossier $dossier, array $pieceTypes, ?string $message = null, ?AdminUser $admin = null): Dossier
    {
        $message = trim($message ?? '');
        $pieceTypes = array_values(array_filter($pieceTypes));

        if ($pieceTypes === [] && $message === '') {
            throw ValidationException::withMessages([
                'complement' => 'Indiquez au moins une pièce à demander ou un message à l\'assuré.',
            ]);
        }

        return $this->decide($dossier, $admin, 'compléter', function (Dossier $dossier) use ($pieceTypes, $message) {
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

            $this->notifyAssureAfterCommit(
                $dossier,
                'complement',
                'Pièce complémentaire demandée',
                $contenu ?: "Complément demandé pour {$dossier->beneficiaire} : {$piecesLabel}"
            );
        });
    }

    public function setEnAttente(Dossier $dossier, ?AdminUser $admin = null): Dossier
    {
        return $this->decide($dossier, $admin, 'mettre en attente', function (Dossier $dossier) {
            if ($dossier->statut === 'En attente supervision') {
                throw ValidationException::withMessages(['statut' => 'Ce dossier est déjà en attente de supervision.']);
            }

            $dossier->update(['statut' => 'En attente supervision']);
            $this->dossiers->appendJournal($dossier, 'Passage en attente de supervision');
        });
    }

    /**
     * Cadre commun des décisions sur un dossier : la ligne est verrouillée le temps de la
     * décision (deux agents ne peuvent pas trancher en même temps), puis le statut est
     * revérifié une fois le verrou obtenu, car il a pu changer entre-temps.
     *
     * @param  callable(Dossier): void  $apply
     */
    private function decide(Dossier $dossier, ?AdminUser $admin, string $action, callable $apply): Dossier
    {
        return DB::transaction(function () use ($dossier, $admin, $action, $apply) {
            $dossier = $this->lockDossier($dossier);
            $this->assertCanDecide($dossier, $admin, $action);
            $apply($dossier);

            return $dossier->fresh();
        });
    }

    private function lockDossier(Dossier $dossier): Dossier
    {
        return Dossier::query()->lockForUpdate()->findOrFail($dossier->id);
    }

    /**
     * Un dossier clos (validé, refusé, retiré) ou non soumis ne se rouvre pas par une
     * nouvelle décision ; un dossier en attente de supervision est réservé au superviseur
     * ou à l'administrateur.
     */
    private function assertCanDecide(Dossier $dossier, ?AdminUser $admin, string $action): void
    {
        $messages = [
            'Brouillon' => "Ce dossier n'a pas encore été soumis par l'assuré : impossible de le {$action}.",
            'Validé' => "Ce dossier est déjà validé : impossible de le {$action}.",
            'Refusé' => "Ce dossier est déjà refusé : impossible de le {$action}.",
            'Retiré' => "Ce membre a été retiré : impossible de {$action} son dossier.",
        ];

        if (isset($messages[$dossier->statut])) {
            throw ValidationException::withMessages(['statut' => $messages[$dossier->statut]]);
        }

        if ($dossier->statut === 'En attente supervision' && $this->isGestionnaire($admin)) {
            throw ValidationException::withMessages([
                'statut' => 'Ce dossier attend la décision du superviseur : un gestionnaire ne peut plus le modifier.',
            ]);
        }
    }

    /**
     * Sans la permission « Valider en second niveau », la validation d'un agent passe par la
     * supervision (si elle est activée) et il ne peut plus toucher un dossier en attente de supervision.
     */
    private function isGestionnaire(?AdminUser $admin): bool
    {
        return $admin !== null && ! $admin->hasPermission('dossiers.supervise');
    }

    /** Sans « Consulter tous les dossiers », un agent ne voit que les dossiers qui lui sont affectés. */
    public function seesOnlyOwnDossiers(?AdminUser $admin): bool
    {
        return $admin !== null && ! $admin->hasPermission('dossiers.view_all');
    }

    /**
     * Notification (et e-mail) envoyée une fois la décision enregistrée : rien n'est
     * envoyé si la transaction échoue, et l'envoi ne prolonge pas le verrou.
     */
    private function notifyAssureAfterCommit(Dossier $dossier, string $event, string $titre, string $contenu, ?string $lien = null): void
    {
        DB::afterCommit(fn () => $this->notifyAssure($dossier, $event, $titre, $contenu, $lien));
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
        return ! in_array($dossier->statut, ['Validé', 'Refusé', 'Brouillon', 'Retiré', 'En attente supervision'], true);
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
