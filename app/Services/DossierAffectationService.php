<?php

namespace App\Services;

use App\Enums\AdminRole;
use App\Models\AdminUser;
use App\Models\Dossier;
use App\Models\PlatformSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DossierAffectationService
{
    public const OPEN_STATUTS = [
        'Soumis',
        'En instruction',
        'Pièce manquante demandée',
        'En attente supervision',
    ];

    public const NON_AFFECTE = 'Non affecté';

    /** Ligne de platform_settings verrouillée pour sérialiser les affectations automatiques. */
    private const LOCK_KEY = 'affectation_lock';

    public function __construct(private PlatformSettingsService $settings) {}

    /**
     * Gestionnaires pouvant recevoir des dossiers : uniquement les comptes internes
     * actifs de rôle Gestionnaire (un compte désactivé ou supprimé ne reçoit plus rien).
     *
     * @return list<string>
     */
    public function assignablePool(): array
    {
        return AdminUser::query()
            ->where('role', AdminRole::Gestionnaire)
            ->where('actif', true)
            ->orderBy('id')
            ->get()
            ->map(fn (AdminUser $user) => $user->display_name)
            ->values()
            ->all();
    }

    public function isAssignable(string $gestionnaire): bool
    {
        return in_array($gestionnaire, $this->assignablePool(), true);
    }

    /**
     * Gestionnaires affichés dans les tableaux de suivi : les comptes actifs, plus les
     * noms qui détiennent encore des dossiers en cours (compte désactivé, renommé…),
     * pour que le superviseur voie ces dossiers et puisse les réaffecter.
     *
     * @return list<string>
     */
    public function pool(): array
    {
        $orphelins = Dossier::query()
            ->whereIn('statut', self::OPEN_STATUTS)
            ->whereNotNull('gestionnaire')
            ->whereNotIn('gestionnaire', ['', self::NON_AFFECTE])
            ->distinct()
            ->pluck('gestionnaire')
            ->all();

        return array_values(array_unique([...$this->assignablePool(), ...$orphelins]));
    }

    public function modeLabel(): string
    {
        return match ($this->mode()) {
            'round_robin' => 'répartition équilibrée',
            'charge_min' => 'charge la plus faible',
            default => 'manuelle',
        };
    }

    /**
     * Affecte automatiquement un dossier fraîchement soumis selon le mode configuré.
     * Retourne le nom du gestionnaire, ou null (mode manuel, aucun gestionnaire actif,
     * ou incident : le dossier reste alors « Non affecté » et le superviseur l'attribue).
     *
     * À appeler hors de toute transaction : les affectations simultanées sont
     * sérialisées par un verrou et chacune doit voir celles déjà validées.
     */
    public function autoAssign(Dossier $dossier): ?string
    {
        $mode = $this->mode();
        if ($mode === 'manuelle') {
            return null;
        }

        try {
            $this->ensureLockRow();

            // Plusieurs tentatives si MySQL abandonne une transaction sous forte charge.
            return DB::transaction(fn () => $this->assignUnderLock($dossier->id, $mode), 5);
        } catch (\Throwable $e) {
            // Ne jamais faire échouer la soumission de l'assuré pour un problème d'affectation.
            Log::error('Affectation automatique impossible : le dossier reste non affecté', [
                'dossier_id' => $dossier->id,
                'mode' => $mode,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function assignUnderLock(int $dossierId, string $mode): ?string
    {
        // Une seule affectation à la fois : la suivante attend ici, puis lit un état à jour
        // (curseur de rotation, charges) incluant les affectations qui viennent d'être faites.
        $this->acquireLock();

        $dossier = Dossier::query()->lockForUpdate()->find($dossierId);
        if (! $dossier || ! $this->isUnassigned($dossier->gestionnaire)) {
            return null;
        }

        $pool = $this->assignablePool();
        if ($pool === []) {
            Log::warning('Affectation automatique : aucun gestionnaire actif', ['dossier_id' => $dossierId]);

            return null;
        }

        // Cohérence familiale : même gestionnaire que les autres dossiers de l'assuré,
        // à condition qu'il soit toujours actif.
        $gestionnaire = Dossier::query()
            ->where('assure_id', $dossier->assure_id)
            ->where('id', '!=', $dossier->id)
            ->where('statut', '!=', 'Brouillon')
            ->whereIn('gestionnaire', $pool)
            ->orderByDesc('id')
            ->value('gestionnaire');

        $gestionnaire ??= $mode === 'charge_min' ? $this->leastLoaded($pool) : $this->nextRoundRobin($pool);

        $dossier->update(['gestionnaire' => $gestionnaire]);

        return $gestionnaire;
    }

    /**
     * La ligne de verrou est créée par migration ; filet de sécurité hors transaction.
     * Surtout pas d'insertion dans la transaction verrouillée : sur une clé existante,
     * MySQL y pose un verrou partagé, et plusieurs requêtes qui veulent ensuite le verrou
     * exclusif s'interbloquent (constaté au test de charge : 2 dossiers sur 3 non affectés).
     */
    private function ensureLockRow(): void
    {
        if (PlatformSetting::query()->whereKey(self::LOCK_KEY)->exists()) {
            return;
        }

        PlatformSetting::query()->insertOrIgnore([
            'key' => self::LOCK_KEY,
            'value' => json_encode(null),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function acquireLock(): void
    {
        PlatformSetting::query()->whereKey(self::LOCK_KEY)->lockForUpdate()->first();
    }

    /**
     * @param  list<string>  $pool
     */
    private function nextRoundRobin(array $pool): string
    {
        $next = $this->rotationFromCursor($pool)[0];
        $this->settings->rememberRoundRobinCursor($next);

        return $next;
    }

    /**
     * @param  list<string>  $pool
     */
    private function leastLoaded(array $pool): string
    {
        $charges = Dossier::query()
            ->whereIn('statut', self::OPEN_STATUTS)
            ->whereIn('gestionnaire', $pool)
            ->selectRaw('gestionnaire, count(*) as total')
            ->groupBy('gestionnaire')
            ->pluck('total', 'gestionnaire');

        // À charge égale, on tourne (à partir du dernier servi) au lieu de toujours
        // favoriser le premier de la liste : le tri est stable, l'ordre de rotation départage.
        $chosen = collect($this->rotationFromCursor($pool))
            ->sortBy(fn (string $nom) => (int) ($charges[$nom] ?? 0))
            ->first();

        $this->settings->rememberRoundRobinCursor($chosen);

        return $chosen;
    }

    /**
     * Gestionnaires dans l'ordre de passage, en commençant juste après le dernier servi.
     *
     * @param  list<string>  $pool
     * @return list<string>
     */
    private function rotationFromCursor(array $pool): array
    {
        $index = array_search($this->settings->roundRobinCursor(), $pool, true);
        $start = $index === false ? 0 : ($index + 1) % count($pool);

        return [...array_slice($pool, $start), ...array_slice($pool, 0, $start)];
    }

    private function isUnassigned(?string $gestionnaire): bool
    {
        return $gestionnaire === null || in_array(trim($gestionnaire), ['', self::NON_AFFECTE], true);
    }

    private function mode(): string
    {
        return $this->settings->get('affectation_mode') ?? 'manuelle';
    }
}
