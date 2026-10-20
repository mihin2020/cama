<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Assure;
use App\Services\AdminAssureService;
use App\Support\Pagination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AssureController extends Controller
{
    public function index(Request $request, AdminAssureService $service): Response|RedirectResponse
    {
        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'statut' => $request->string('statut')->toString() ?: 'tous',
        ];

        $paginator = $service->paginate($filters, Pagination::perPage($request));

        if (Pagination::isBeyondLastPage($paginator)) {
            return redirect()->route('admin.assures', [...$request->query(), 'page' => $paginator->lastPage()]);
        }

        return Inertia::render('Admin/Assures/Index', [
            'assures' => $paginator->items(),
            'pagination' => Pagination::meta($paginator),
            'filters' => $filters,
            'statutOptions' => [
                ['value' => 'tous', 'label' => 'Tous les statuts'],
                ['value' => 'actif', 'label' => 'Actif'],
                ['value' => 'en_attente_validation', 'label' => 'En attente de validation'],
                ['value' => 'desactive', 'label' => 'Désactivé'],
            ],
        ]);
    }

    public function updateStatut(Request $request, Assure $assure, AdminAssureService $service): RedirectResponse
    {
        $data = $request->validate([
            'statut' => ['required', 'string'],
            'motif' => ['nullable', 'string', 'max:500'],
        ]);

        $service->updateStatut($assure, $data['statut'], $data['motif'] ?? null);

        return back()->with('success', 'Statut du compte mis à jour.');
    }
}
