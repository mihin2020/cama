<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AssureStatut;
use App\Http\Controllers\Controller;
use App\Models\Assure;
use App\Services\AssureRegistrationService;
use App\Support\StoredFileResponse;
use App\Support\Pagination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class InscriptionController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        $search = $request->string('search')->trim()->toString();
        $statutFilter = $request->string('statut')->toString() ?: AssureStatut::EnAttenteValidation->value;
        $dateFrom = $request->date('date_from');
        $dateTo = $request->date('date_to');

        $query = Assure::query()->orderByDesc('created_at');

        if ($statutFilter !== 'tous') {
            $query->where('statut', $statutFilter);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('nom', 'like', "%{$search}%")
                    ->orWhere('prenom', 'like', "%{$search}%")
                    ->orWhere('matricule', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($dateFrom !== null) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo !== null) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $paginator = $query->paginate(Pagination::perPage($request))->withQueryString();

        if (Pagination::isBeyondLastPage($paginator)) {
            return redirect()->route('admin.inscriptions', [...$request->query(), 'page' => $paginator->lastPage()]);
        }

        $registrations = collect($paginator->items())->map(fn (Assure $a) => $this->formatRegistration($a));

        $service = app(AssureRegistrationService::class);

        return Inertia::render('Admin/Inscriptions/Index', [
            'registrations' => $registrations->values()->all(),
            'pagination' => Pagination::meta($paginator),
            'stats' => $service->registrationStats(),
            'filters' => [
                'search' => $search,
                'statut' => $statutFilter,
                'date_from' => $dateFrom?->format('Y-m-d'),
                'date_to' => $dateTo?->format('Y-m-d'),
            ],
            'statutOptions' => [
                ['value' => AssureStatut::EnAttenteValidation->value, 'label' => 'En attente de validation'],
                ['value' => AssureStatut::Actif->value, 'label' => 'Validées'],
                ['value' => AssureStatut::Refuse->value, 'label' => 'Refusées'],
                ['value' => 'tous', 'label' => 'Toutes'],
            ],
        ]);
    }

    public function updateIdentifiers(Request $request, Assure $assure, AssureRegistrationService $service): RedirectResponse
    {
        $data = $request->validate([
            'numero_cim' => ['required', 'string', 'max:50'],
            'numero_cama' => ['required', 'string', 'max:50'],
        ]);

        $service->updateIdentifiers($assure, $data['numero_cim'], $data['numero_cama']);

        return back()->with('success', 'Identifiants enregistrés.');
    }

    public function validateRegistration(Assure $assure, AssureRegistrationService $service): RedirectResponse
    {
        $assure = $service->validate($assure);

        return back()->with('success', "Compte validé (CIM : {$assure->numero_cim}, Carte CAMA : {$assure->numero_cama}).");
    }

    public function reject(Request $request, Assure $assure, AssureRegistrationService $service): RedirectResponse
    {
        $data = $request->validate([
            'motif' => ['required', 'string', 'max:500'],
        ]);

        $service->reject($assure, $data['motif']);

        return back()->with('success', 'Demande refusée.');
    }

    public function downloadDocument(Request $request, Assure $assure, string $key): BinaryFileResponse
    {
        // Pièces d'identité : contrôle explicite du droit, en plus de celui de la route.
        abort_unless($request->user('admin')?->hasPermission('inscriptions.view'), 403, 'Accès non autorisé pour vos permissions.');

        $document = collect($assure->documents_identite ?? [])
            ->first(fn ($doc) => ($doc['key'] ?? '') === $key);

        // Le chemin vient de la fiche de l'assuré (jamais de la requête) et reste dans l'espace privé
        // des pièces : « assures/ » (nouveau rangement) ou « inscriptions/ » (anciens dépôts).
        $path = (string) ($document['path'] ?? '');
        abort_unless(
            $path !== '' && (str_starts_with($path, 'assures/') || str_starts_with($path, 'inscriptions/')),
            404,
        );
        abort_unless(Storage::disk('local')->exists($path), 404);

        // « Visualiser » (?inline=1) l'ouvre dans le navigateur ; sinon il est téléchargé sous son nom
        // normalisé (ASS_<N° FIF>_INSCR-…).
        return StoredFileResponse::make(Storage::disk('local')->path($path), $document['filename'] ?? null, $request->boolean('inline'));
    }

    private function formatRegistration(Assure $assure): array
    {
        return [
            'id' => $assure->id,
            'uuid' => $assure->uuid,
            'fullName' => $assure->full_name,
            'nom' => $assure->nom,
            'prenom' => $assure->prenom,
            'sexe' => $assure->sexe,
            'matricule' => $assure->matricule,
            'numeroFif' => $assure->numero_fif,
            'grade' => $assure->grade,
            'categorie' => $assure->categorie,
            'numeroInformatique' => $assure->numero_informatique,
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
            'fifSections' => app(\App\Services\FifSchemaService::class)->display('assure', $assure->fif_donnees),
            'statut' => $assure->statut->value,
            'statutLabel' => $assure->statut->label(),
            'motifRefus' => $assure->motif_refus,
            'dateCreation' => $assure->created_at->format('d/m/Y H:i'),
            'journal' => $assure->journal ?? [],
            'initiales' => $assure->initiales,
            'documents' => collect($assure->documents_identite ?? [])->map(fn ($doc) => [
                'key' => $doc['key'] ?? null,
                'label' => $doc['label'] ?? 'Document',
                'filename' => $doc['filename'] ?? null,
                'url' => ! empty($doc['key']) ? route('admin.inscriptions.document', ['assure' => $assure->uuid, 'key' => $doc['key']]) : null,
            ])->filter(fn ($doc) => $doc['url'])->values()->all(),
        ];
    }
}
