<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AssureStatut;
use App\Http\Controllers\Controller;
use App\Models\Assure;
use App\Services\AssureRegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class InscriptionController extends Controller
{
    public function index(Request $request): Response
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

        $paginator = $query->paginate(20)->withQueryString();
        $registrations = collect($paginator->items())->map(fn (Assure $a) => $this->formatRegistration($a));

        $service = app(AssureRegistrationService::class);

        return Inertia::render('Admin/Inscriptions/Index', [
            'registrations' => $registrations->values()->all(),
            'pagination' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
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

    public function downloadDocument(Assure $assure, string $key): BinaryFileResponse
    {
        $document = collect($assure->documents_identite ?? [])
            ->first(fn ($doc) => ($doc['key'] ?? '') === $key);

        abort_unless($document && ! empty($document['path']) && Storage::disk('local')->exists($document['path']), 404);

        return response()->download(
            Storage::disk('local')->path($document['path']),
            $document['filename'] ?? basename($document['path']),
        );
    }

    private function formatRegistration(Assure $assure): array
    {
        return [
            'id' => $assure->id,
            'fullName' => $assure->full_name,
            'nom' => $assure->nom,
            'prenom' => $assure->prenom,
            'sexe' => $assure->sexe,
            'matricule' => $assure->matricule,
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
                'url' => ! empty($doc['key']) ? route('admin.inscriptions.document', ['assure' => $assure->id, 'key' => $doc['key']]) : null,
            ])->filter(fn ($doc) => $doc['url'])->values()->all(),
        ];
    }
}
