<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Pagination commune à toutes les listes : la taille de page dépend du type de liste
 * (un tableau dense n'affiche pas autant de lignes qu'une grille de vignettes ou un fil
 * de notifications), et seules les tailles proposées sont acceptées.
 */
final class Pagination
{
    /** Tableaux de gestion (assurés, comptes, pages, articles…). */
    public const TABLE = [20, 50, 100];

    /** Grilles de vignettes (médiathèque). */
    public const GRID = [24, 48, 96];

    /** Fils de notifications et journaux d'activité. */
    public const FEED = [15, 30, 60];

    /** Cartes détaillées (campagnes, familles de dossiers). */
    public const CARDS = [10, 25, 50];

    /** Espace assuré, consulté surtout sur téléphone : pages courtes. */
    public const COMPACT = [10, 20, 50];

    /**
     * Taille de page demandée (?per_page=), ramenée à la première option si elle n'est pas proposée.
     *
     * @param  list<int>  $options
     */
    public static function perPage(Request $request, array $options = self::TABLE): int
    {
        $requested = $request->integer('per_page');

        return in_array($requested, $options, true) ? $requested : $options[0];
    }

    /**
     * Données de pagination transmises à la page (composant CamaPagination).
     *
     * @param  list<int>  $options
     * @return array{currentPage: int, lastPage: int, perPage: int, total: int, perPageOptions: list<int>}
     */
    public static function meta(LengthAwarePaginator $paginator, array $options = self::TABLE): array
    {
        return [
            'currentPage' => $paginator->currentPage(),
            'lastPage' => $paginator->lastPage(),
            'perPage' => $paginator->perPage(),
            'total' => $paginator->total(),
            'perPageOptions' => $options,
        ];
    }

    /** Page demandée au-delà de la dernière (après une suppression, un lien ancien…). */
    public static function isBeyondLastPage(LengthAwarePaginator $paginator): bool
    {
        return $paginator->isEmpty() && $paginator->currentPage() > 1;
    }
}
