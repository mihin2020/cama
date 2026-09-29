<?php

namespace App\Services;

use App\Enums\AssureStatut;
use App\Models\Assure;
use App\Models\Dossier;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class AdminAssureService
{
    public function list(array $filters = []): array
    {
        return $this->buildListQuery($filters)->get()->map(fn (Assure $a) => $this->formatListRow($a))->all();
    }

    public function paginate(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->buildListQuery($filters)
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Assure $a) => $this->formatListRow($a));
    }

    private function buildListQuery(array $filters)
    {
        $query = Assure::query()
            ->withCount(['dossiers' => fn ($q) => $q->where('statut', '!=', 'Brouillon')])
            ->with(['dossiers' => fn ($q) => $q->where('statut', '!=', 'Brouillon')->orderByDesc('date_soumission')])
            ->orderByDesc('created_at');

        $statut = $filters['statut'] ?? 'tous';
        if ($statut !== 'tous') {
            $query->where('statut', $statut);
        } else {
            $query->whereIn('statut', [
                AssureStatut::Actif,
                AssureStatut::EnAttenteValidation,
                AssureStatut::Desactive,
            ]);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('nom', 'like', "%{$search}%")
                    ->orWhere('prenom', 'like', "%{$search}%")
                    ->orWhere('matricule', 'like', "%{$search}%")
                    ->orWhere('numero_cama', 'like', "%{$search}%");
            });
        }

        return $query;
    }

    public function formatDetail(Assure $assure): array
    {
        $membres = $assure->dossiers()
            ->where('statut', '!=', 'Brouillon')
            ->orderByDesc('date_soumission')
            ->get()
            ->map(fn (Dossier $d) => [
                'id' => $d->id,
                'nom' => $d->beneficiaire,
                'lien' => $d->lien,
                'statut' => $d->statut,
                'ref' => $d->ref,
            ]);

        return [
            'id' => $assure->id,
            'fullName' => $assure->full_name,
            'nom' => $assure->full_name,
            'matricule' => $assure->matricule,
            'numeroCama' => $assure->numero_cama,
            'statut' => $assure->statut->label(),
            'statutValue' => $assure->statut->value,
            'dateCreation' => $assure->created_at->format('d/m/Y'),
            'initiales' => $assure->initiales,
            'journal' => $assure->journal ?? [],
            'membres' => $membres,
            'exportProfile' => $this->exportProfile($assure),
        ];
    }

    public function updateStatut(Assure $assure, string $nouveauStatut, ?string $motif = null): Assure
    {
        $statut = match ($nouveauStatut) {
            'Actif' => AssureStatut::Actif,
            'Désactivé' => AssureStatut::Desactive,
            'En attente de validation' => AssureStatut::EnAttenteValidation,
            default => throw ValidationException::withMessages(['statut' => 'Statut invalide.']),
        };

        if ($statut === AssureStatut::Desactive && ! trim((string) $motif)) {
            throw ValidationException::withMessages(['motif' => 'Motif requis pour désactiver un compte.']);
        }

        $journal = $assure->journal ?? [];
        if ($statut === AssureStatut::Desactive) {
            $journal[] = ['date' => now()->format('d/m/Y H:i'), 'libelle' => "Compte désactivé (motif : {$motif})"];
        } elseif ($statut === AssureStatut::Actif && $assure->statut === AssureStatut::EnAttenteValidation) {
            $journal[] = ['date' => now()->format('d/m/Y H:i'), 'libelle' => 'Compte validé par le gestionnaire'];
        } elseif ($statut === AssureStatut::Actif) {
            $journal[] = ['date' => now()->format('d/m/Y H:i'), 'libelle' => 'Compte réactivé'];
        }

        $assure->update([
            'statut' => $statut,
            'journal' => $journal,
            'motif_refus' => $statut === AssureStatut::Desactive ? $motif : $assure->motif_refus,
        ]);

        return $assure->fresh();
    }

    private function formatListRow(Assure $assure): array
    {
        // Utilise la relation déjà eager-loadée (évite une requête par ligne — N+1).
        $membres = $assure->dossiers
            ->map(fn (Dossier $d) => [
                'id' => $d->id,
                'nom' => $d->beneficiaire,
                'lien' => $d->lien,
                'statut' => $d->statut,
                'ref' => $d->ref,
            ]);

        return [
            'id' => $assure->id,
            'nom' => $assure->full_name,
            'matricule' => $assure->matricule,
            'numeroCama' => $assure->numero_cama,
            'statut' => $assure->statut->label(),
            'statutValue' => $assure->statut->value,
            'dateCreation' => $assure->created_at->format('d/m/Y'),
            'membresCount' => $assure->dossiers_count,
            'membres' => $membres->values()->all(),
            'journal' => $assure->journal ?? [],
            'initiales' => $assure->initiales,
            'exportProfile' => $this->exportProfile($assure),
            'membresExport' => $assure->dossiers()
                ->where('statut', '!=', 'Brouillon')
                ->get()
                ->map(fn (Dossier $d) => app(DossierService::class)->formatMembre($d))
                ->values()
                ->all(),
        ];
    }

    private function exportProfile(Assure $assure): array
    {
        return [
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

    /**
     * @return array<string, mixed>
     */
    public function profileForAdmin(Assure $assure): array
    {
        return array_merge($this->exportProfile($assure), [
            'id' => $assure->id,
            'statut' => $assure->statut->label(),
            'dateCreation' => $assure->created_at?->format('d/m/Y') ?? '',
            'documentsIdentite' => $assure->documents_identite ?? [],
        ]);
    }
}
