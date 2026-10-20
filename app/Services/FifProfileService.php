<?php

namespace App\Services;

use App\Models\Assure;
use App\Models\PlatformSetting;
use App\Support\NumeroFif;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Fiche d'Identification Famille (FIF) : numéro de la famille et données imprimées
 * sur la fiche, côté assuré comme côté admin.
 */
class FifProfileService
{
    /** Ligne de platform_settings verrouillée pour attribuer les rangs un par un. */
    public const LOCK_KEY = 'fif_lock';

    /**
     * @return array<string, mixed>
     */
    public function profile(Assure $assure): array
    {
        return [
            'numeroFif' => $this->numero($assure),
            'nom' => $assure->nom,
            'prenom' => $assure->prenom,
            'prenoms' => $assure->prenom,
            'fullName' => $assure->full_name,
            'sexe' => $assure->sexe,
            'matricule' => $assure->matricule,
            'numeroInformatique' => $assure->numero_informatique,
            'grade' => $assure->grade,
            'categorie' => $assure->categorie,
            'numeroCim' => $assure->numero_cim,
            'numeroCama' => $assure->numero_cama,
            'numeroIup' => $assure->numero_iup,
            'armee' => $assure->armee,
            'region' => $assure->region,
            'corps' => $assure->corps,
            'service' => $assure->service,
            'section' => $assure->section,
            'sousSection' => $assure->sous_section,
            'telephone' => $assure->telephone,
            'email' => $assure->email,
            'personneAPrevenir' => $assure->personne_a_prevenir,
            'telPersonneAPrevenir' => $assure->tel_personne_a_prevenir,
        ];
    }

    /** Numéro FIF de l'assuré ; attribué au premier besoin s'il n'en a pas encore. */
    public function numero(Assure $assure): string
    {
        return $assure->numero_fif ?: $this->assign($assure);
    }

    /**
     * Attribue le prochain rang libre pour l'année, l'armée et la région de l'assuré.
     * Les attributions simultanées passent une par une (verrou), et l'index unique
     * reste la garantie finale : en cas de collision, on recalcule.
     */
    public function assign(Assure $assure): string
    {
        $year = (int) ($assure->created_at ?? now())->format('Y');
        $prefix = NumeroFif::prefix($year, $assure->armee, $assure->region);

        for ($attempt = 1; ; $attempt++) {
            try {
                $numero = DB::transaction(function () use ($assure, $prefix) {
                    PlatformSetting::query()->whereKey(self::LOCK_KEY)->lockForUpdate()->first();

                    // Un autre processus a pu l'attribuer entre-temps.
                    $existing = Assure::query()->whereKey($assure->id)->value('numero_fif');
                    if ($existing) {
                        return $existing;
                    }

                    $numero = NumeroFif::format($prefix, $this->lastRank($prefix) + 1);
                    Assure::query()->whereKey($assure->id)->update(['numero_fif' => $numero]);

                    return $numero;
                });

                return $assure->numero_fif = $numero;
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt >= 5) {
                    throw $e;
                }
            }
        }
    }

    private function lastRank(string $prefix): int
    {
        return Assure::query()
            ->where('numero_fif', 'like', str_replace(['%', '_'], ['\%', '\_'], $prefix).'%')
            ->pluck('numero_fif')
            ->map(fn (string $numero) => NumeroFif::rank($prefix, $numero))
            ->filter()
            ->max() ?? 0;
    }
}
