<?php

namespace App\Services;

use App\Enums\AdminRole;
use App\Models\AdminUser;
use App\Models\Dossier;

class DossierAffectationService
{
    public const OPEN_STATUTS = [
        'Soumis',
        'En instruction',
        'Pièce manquante demandée',
        'En attente supervision',
    ];

    public function __construct(private PlatformSettingsService $settings) {}

    /**
     * Gestionnaires pouvant recevoir des dossiers : comptes internes actifs
     * de rôle Gestionnaire, complétés des noms déjà présents sur des dossiers
     * (continuité avec les données existantes).
     *
     * @return list<string>
     */
    public function pool(): array
    {
        $actifs = AdminUser::query()
            ->where('role', AdminRole::Gestionnaire)
            ->where('actif', true)
            ->orderBy('id')
            ->get()
            ->map(fn (AdminUser $user) => $user->display_name)
            ->all();

        $existants = Dossier::query()
            ->where('statut', '!=', 'Brouillon')
            ->whereNotNull('gestionnaire')
            ->whereNotIn('gestionnaire', ['', 'Non affecté'])
            ->distinct()
            ->pluck('gestionnaire')
            ->all();

        return array_values(array_unique([...$actifs, ...$existants]));
    }

    public function modeLabel(): string
    {
        return match ($this->settings->get('affectation_mode') ?? 'manuelle') {
            'round_robin' => 'répartition équilibrée',
            'charge_min' => 'charge la plus faible',
            default => 'manuelle',
        };
    }

    /**
     * Affecte automatiquement un dossier fraîchement soumis selon le mode
     * configuré dans les paramètres. Retourne le nom du gestionnaire affecté,
     * ou null en mode manuel (le dossier reste « Non affecté »).
     */
    public function autoAssign(Dossier $dossier): ?string
    {
        $mode = $this->settings->get('affectation_mode') ?? 'manuelle';
        if ($mode === 'manuelle') {
            return null;
        }

        // Cohérence familiale : les dossiers d'un même assuré restent chez le même gestionnaire.
        $gestionnaireFamille = Dossier::query()
            ->where('assure_id', $dossier->assure_id)
            ->where('id', '!=', $dossier->id)
            ->where('statut', '!=', 'Brouillon')
            ->whereNotNull('gestionnaire')
            ->whereNotIn('gestionnaire', ['', 'Non affecté'])
            ->value('gestionnaire');

        $gestionnaire = $gestionnaireFamille ?: match ($mode) {
            'round_robin' => $this->nextRoundRobin(),
            'charge_min' => $this->leastLoaded(),
            default => null,
        };

        if (! $gestionnaire) {
            return null;
        }

        $dossier->update(['gestionnaire' => $gestionnaire]);

        return $gestionnaire;
    }

    private function nextRoundRobin(): ?string
    {
        $pool = $this->pool();
        if (! $pool) {
            return null;
        }

        $last = $this->settings->roundRobinCursor();
        $index = array_search($last, $pool, true);
        $next = $pool[$index === false ? 0 : ($index + 1) % count($pool)];

        $this->settings->rememberRoundRobinCursor($next);

        return $next;
    }

    private function leastLoaded(): ?string
    {
        $pool = $this->pool();
        if (! $pool) {
            return null;
        }

        $charges = Dossier::query()
            ->whereIn('statut', self::OPEN_STATUTS)
            ->whereIn('gestionnaire', $pool)
            ->selectRaw('gestionnaire, count(*) as total')
            ->groupBy('gestionnaire')
            ->pluck('total', 'gestionnaire');

        return collect($pool)
            ->sortBy(fn (string $nom) => $charges[$nom] ?? 0)
            ->first();
    }
}
