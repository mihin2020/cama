<?php

namespace App\Services;

use App\Models\Assure;
use App\Models\AssureNotification;
use App\Models\Dossier;

class AssureDashboardService
{
    public function stats(Assure $assure): array
    {
        $dossiers = $assure->dossiers()->orderByDesc('date_soumission')->get();

        $enAttente = $dossiers->whereIn('statut', ['Soumis', 'En instruction'])->count();
        $valides = $dossiers->where('statut', 'Validé')->count();
        $refuses = $dossiers->where('statut', 'Refusé')->count();
        $aCompleter = $dossiers->whereIn('statut', ['Brouillon', 'Pièce manquante demandée'])->count();

        $membres = $dossiers->map(fn ($d) => [
            'id' => $d->id,
            'prenom' => $d->prenom,
            'nom' => $d->nom,
            'lien' => $d->lien,
            'statut' => $d->statut,
            'dateSoumission' => $d->date_soumission?->format('d/m/Y'),
            'initiales' => strtoupper(substr($d->prenom, 0, 1).substr($d->nom, 0, 1)),
        ])->values()->all();

        $notifications = $assure->notifications()
            ->orderByDesc('created_at')
            ->take(4)
            ->get()
            ->map(fn (AssureNotification $n) => [
                'id' => $n->id,
                'type' => $n->type,
                'titre' => $n->titre,
                'contenu' => $n->contenu,
                'lien' => $n->lien,
                'lu' => $n->lu,
                'date' => $n->created_at->format('d/m/Y H:i'),
            ])
            ->all();

        $unreadCount = $assure->notifications()->where('lu', false)->count();

        return [
            'stats' => [
                'enAttente' => $enAttente,
                'valides' => $valides,
                'refuses' => $refuses,
                'aCompleter' => $aCompleter,
            ],
            'membres' => $membres,
            'notifications' => $notifications,
            'unreadCount' => $unreadCount,
        ];
    }
}
