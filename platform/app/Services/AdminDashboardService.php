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

    private const GESTIONNAIRES = [
        'Lt. Aminata KABORÉ',
        'Sgt. Daniel ZONGO',
        'Adj. Rasmané BANCÉ',
    ];

    public function stats(?string $gestionnaireFilter = null): array
    {
        $allDossiers = Dossier::query()
            ->with('assure')
            ->when($gestionnaireFilter, fn ($q) => $q->where('gestionnaire', $gestionnaireFilter))
            ->get();

        $dossiers = $allDossiers->where('statut', '!=', 'Brouillon');
        $byStatut = $dossiers->groupBy('statut')->map->count()->all();

        $enAttente = $dossiers->whereIn('statut', self::OPEN_STATUTS)->count();
        $valides = $byStatut['Validé'] ?? 0;
        $refuses = $byStatut['Refusé'] ?? 0;
        $total = $dossiers->count();
        $tauxValidation = $total ? round(($valides / $total) * 1000) / 10 : 0;
        $nonAffectes = $dossiers->filter(fn ($d) => ! $d->gestionnaire || $d->gestionnaire === 'Non affecté')->count();

        $familles = $allDossiers->groupBy('assure_id');
        $famillesATraiter = $familles->filter(function ($group) {
            return $group->contains(fn ($d) => in_array($d->statut, self::OPEN_STATUTS, true));
        })->count();

        $charges = $this->gestionnaireCharge($allDossiers);
        $totalRetard = collect($charges)->sum('retard');

        $priority = $dossiers
            ->whereIn('statut', self::OPEN_STATUTS)
            ->sortBy('date_soumission')
            ->take(6)
            ->map(fn ($d) => [
                'ref' => $d->ref,
                'nom' => $d->beneficiaire,
                'statut' => $d->statut,
                'id' => $d->id,
            ])
            ->values()
            ->all();

        $recentActivity = $dossiers
            ->flatMap(function ($d) {
                return collect($d->journal ?? [])->map(fn ($j) => [
                    'ref' => $d->ref,
                    'beneficiaire' => $d->beneficiaire,
                    'libelle' => $j['libelle'] ?? '',
                    'date' => $j['date'] ?? '',
                    'statut' => $d->statut,
                ]);
            })
            ->sortByDesc('date')
            ->take(8)
            ->values()
            ->all();

        $assures = Assure::query()->get();

        return [
            'assuresTotal' => $assures->count(),
            'assuresActifs' => $assures->where('statut', AssureStatut::Actif)->count(),
            'inscriptionsEnAttente' => $assures->where('statut', AssureStatut::EnAttenteValidation)->count(),
            'dossiersEnAttente' => $enAttente,
            'dossiersValides' => $valides,
            'dossiersRefuses' => $refuses,
            'dossiersTotal' => $total,
            'tauxValidation' => $tauxValidation,
            'nonAffectes' => $nonAffectes,
            'famillesDossiers' => $familles->count(),
            'famillesATraiter' => $famillesATraiter,
            'delaiMoyenJours' => $this->computeDelaiMoyenJours($dossiers),
            'totalRetard' => $totalRetard,
            'byStatut' => $byStatut,
            'charges' => $charges,
            'priority' => $priority,
            'recentActivity' => $recentActivity,
            'stockageGo' => max(12, (int) round($total * 0.8 + $assures->count() * 2)),
            'projectionGo' => max(1, (int) round($enAttente * 0.15)),
        ];
    }

    private function gestionnaireCharge($dossiers): array
    {
        return collect(self::GESTIONNAIRES)->map(function ($nom) use ($dossiers) {
            $mine = $dossiers->where('gestionnaire', $nom)->where('statut', '!=', 'Brouillon');
            $nonTraites = $mine->whereIn('statut', self::OPEN_STATUTS)->count();
            $retard = $mine->filter(function ($d) {
                if (! in_array($d->statut, self::OPEN_STATUTS, true) || ! $d->date_soumission) {
                    return false;
                }

                return $d->date_soumission->diffInDays(now()) > 7;
            })->count();

            return [
                'nom' => $nom,
                'assignes' => $mine->count(),
                'nonTraites' => $nonTraites,
                'valides' => $mine->where('statut', 'Validé')->count(),
                'retard' => $retard,
            ];
        })->all();
    }

    private function computeDelaiMoyenJours($dossiers): float
    {
        $traites = $dossiers->filter(fn ($d) => in_array($d->statut, ['Validé', 'Refusé'], true) && $d->date_soumission);

        if ($traites->isEmpty()) {
            return 3.2;
        }

        $total = $traites->sum(function ($d) {
            $end = $d->date_decision ?? now();

            return $d->date_soumission->diffInDays($end);
        });

        return round($total / $traites->count(), 1);
    }
}
