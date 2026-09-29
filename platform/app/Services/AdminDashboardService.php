<?php

namespace App\Services;

use App\Enums\AssureStatut;
use App\Models\Assure;
use App\Models\Dossier;

class AdminDashboardService
{
    private const OPEN_STATUTS = [
        'Soumis',
        'En instruction',
        'Pièce manquante demandée',
        'En attente supervision',
    ];

    public function __construct(private DossierAffectationService $affectation) {}

    /**
     * @param  string|null  $gestionnaireFilter  Nom du gestionnaire connecté : restreint
     *                                            le tableau de bord à ses propres dossiers
     *                                            (vue personnelle). null = vue globale (encadrement).
     */
    public function stats(?string $gestionnaireFilter = null): array
    {
        $personnel = $gestionnaireFilter !== null;

        // Base commune (dossiers soumis, hors brouillon) — une nouvelle requête à chaque appel.
        $base = fn () => Dossier::query()
            ->when($gestionnaireFilter, fn ($q) => $q->where('gestionnaire', $gestionnaireFilter))
            ->where('statut', '!=', 'Brouillon');

        // Comptages par statut en une seule agrégation SQL (au lieu de tout charger en mémoire).
        $byStatut = $base()
            ->selectRaw('statut, count(*) as c')
            ->groupBy('statut')
            ->pluck('c', 'statut')
            ->map(fn ($c) => (int) $c)
            ->all();

        $enAttente = collect(self::OPEN_STATUTS)->sum(fn ($s) => $byStatut[$s] ?? 0);
        $valides = $byStatut['Validé'] ?? 0;
        $refuses = $byStatut['Refusé'] ?? 0;
        $total = array_sum($byStatut);
        $tauxValidation = $total ? round(($valides / $total) * 1000) / 10 : 0;

        $nonAffectes = $base()
            ->where(fn ($q) => $q->whereNull('gestionnaire')->orWhereIn('gestionnaire', ['', 'Non affecté']))
            ->count();

        // Familles distinctes (count(distinct assure_id)).
        $famillesDossiers = $base()->distinct()->count('assure_id');
        $famillesATraiter = $base()->whereIn('statut', self::OPEN_STATUTS)->distinct()->count('assure_id');

        $charges = $this->gestionnaireCharge($gestionnaireFilter);
        $totalRetard = collect($charges)->sum('retard');

        // Dossiers prioritaires — requête bornée (30 lignes max).
        $priority = $base()
            ->whereIn('statut', self::OPEN_STATUTS)
            ->orderBy('date_soumission')
            ->limit(30)
            ->get(['id', 'ref', 'nom', 'prenom', 'statut'])
            ->map(fn ($d) => [
                'ref' => $d->ref,
                'nom' => $d->beneficiaire,
                'statut' => $d->statut,
                'id' => $d->id,
            ])
            ->values()
            ->all();

        // Activité récente : bornée aux 40 dossiers récemment mis à jour, puis 10 évènements.
        $recentActivity = $base()
            ->orderByDesc('updated_at')
            ->limit(40)
            ->get(['ref', 'nom', 'prenom', 'statut', 'journal'])
            ->flatMap(function ($d) {
                return collect($d->journal ?? [])->map(fn ($j) => [
                    'ref' => $d->ref,
                    'beneficiaire' => $d->beneficiaire,
                    'libelle' => $j['libelle'] ?? '',
                    'date' => $j['date'] ?? '',
                    'statut' => $d->statut,
                ]);
            })
            ->sortByDesc(fn ($a) => $this->parseFrDate($a['date']))
            ->take(10)
            ->values()
            ->all();

        if ($personnel) {
            // Vue personnelle : les « assurés » du gestionnaire sont les familles
            // qu'il suit réellement (dossiers qui lui sont affectés). La file
            // d'inscriptions est un flux global qui ne le concerne pas.
            $assuresTotal = $famillesDossiers;
            $assuresActifs = $base()->where('statut', 'Validé')->distinct()->count('assure_id');
            $inscriptionsEnAttente = 0;
        } else {
            // Vue globale (encadrement) : comptage des assurés par statut en une agrégation SQL.
            $assureCounts = Assure::query()
                ->selectRaw('statut, count(*) as c')
                ->groupBy('statut')
                ->pluck('c', 'statut')
                ->map(fn ($c) => (int) $c);
            $assuresTotal = (int) $assureCounts->sum();
            $assuresActifs = (int) ($assureCounts[AssureStatut::Actif->value] ?? 0);
            $inscriptionsEnAttente = (int) ($assureCounts[AssureStatut::EnAttenteValidation->value] ?? 0);
        }

        return [
            'assuresTotal' => $assuresTotal,
            'assuresActifs' => $assuresActifs,
            'inscriptionsEnAttente' => $inscriptionsEnAttente,
            'dossiersEnAttente' => $enAttente,
            'dossiersValides' => $valides,
            'dossiersRefuses' => $refuses,
            'dossiersTotal' => $total,
            'tauxValidation' => $tauxValidation,
            'nonAffectes' => $nonAffectes,
            'famillesDossiers' => $famillesDossiers,
            'famillesATraiter' => $famillesATraiter,
            'delaiMoyenJours' => $this->computeDelaiMoyenJours($gestionnaireFilter),
            'totalRetard' => $totalRetard,
            'byStatut' => $byStatut,
            'charges' => $charges,
            'priority' => $priority,
            'recentActivity' => $recentActivity,
            'stockageGo' => max(12, (int) round($total * 0.8 + $assuresTotal * 2)),
            'projectionGo' => max(1, (int) round($enAttente * 0.15)),
        ];
    }

    private function gestionnaireCharge(?string $gestionnaireFilter): array
    {
        $base = fn () => Dossier::query()
            ->when($gestionnaireFilter, fn ($q) => $q->where('gestionnaire', $gestionnaireFilter))
            ->where('statut', '!=', 'Brouillon');

        // 4 agrégations SQL (par gestionnaire) au lieu de parcourir toute la collection.
        $assignes = $base()->selectRaw('gestionnaire, count(*) c')->groupBy('gestionnaire')->pluck('c', 'gestionnaire');
        $nonTraites = $base()->whereIn('statut', self::OPEN_STATUTS)->selectRaw('gestionnaire, count(*) c')->groupBy('gestionnaire')->pluck('c', 'gestionnaire');
        $valides = $base()->where('statut', 'Validé')->selectRaw('gestionnaire, count(*) c')->groupBy('gestionnaire')->pluck('c', 'gestionnaire');
        $retard = $base()
            ->whereIn('statut', self::OPEN_STATUTS)
            ->whereNotNull('date_soumission')
            ->whereDate('date_soumission', '<', now()->subDays(7)->toDateString())
            ->selectRaw('gestionnaire, count(*) c')
            ->groupBy('gestionnaire')
            ->pluck('c', 'gestionnaire');

        // Pool réel de gestionnaires (comptes internes actifs + noms déjà présents
        // sur des dossiers). En vue personnelle, on ne garde que le gestionnaire connecté.
        $pool = $gestionnaireFilter !== null
            ? [$gestionnaireFilter]
            : $this->affectation->pool();

        return collect($pool)->map(fn ($nom) => [
            'nom' => $nom,
            'assignes' => (int) ($assignes[$nom] ?? 0),
            'nonTraites' => (int) ($nonTraites[$nom] ?? 0),
            'valides' => (int) ($valides[$nom] ?? 0),
            'retard' => (int) ($retard[$nom] ?? 0),
        ])->all();
    }

    private function computeDelaiMoyenJours(?string $gestionnaireFilter): float
    {
        // Échantillon borné aux 200 derniers dossiers traités (suffisant pour une moyenne).
        $traites = Dossier::query()
            ->when($gestionnaireFilter, fn ($q) => $q->where('gestionnaire', $gestionnaireFilter))
            ->whereIn('statut', ['Validé', 'Refusé'])
            ->whereNotNull('date_soumission')
            ->orderByDesc('date_decision')
            ->limit(200)
            ->get(['date_soumission', 'date_decision']);

        if ($traites->isEmpty()) {
            return 3.2;
        }

        $total = $traites->sum(fn ($d) => $d->date_soumission->diffInDays($d->date_decision ?? now()));

        return round($total / $traites->count(), 1);
    }

    /**
     * Convertit une date de journal « jj/mm/aaaa HH:MM » en clé triable « aaaammjjHHMM ».
     */
    private function parseFrDate(?string $dateStr): string
    {
        $dateStr = trim((string) $dateStr);
        if (! preg_match('#^(\d{2})/(\d{2})/(\d{4})(?:\s+(\d{2}):(\d{2}))?#', $dateStr, $m)) {
            return '000000000000';
        }

        return $m[3].$m[2].$m[1].($m[4] ?? '00').($m[5] ?? '00');
    }
}
