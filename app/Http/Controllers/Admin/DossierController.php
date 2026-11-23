<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Assure;
use App\Models\Dossier;
use App\Services\AdminDossierService;
use App\Services\PlatformSettingsService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DossierController extends Controller implements HasMiddleware
{
    /**
     * Toute action sur un {dossier} précis vérifie d'abord qu'il est visible par l'agent
     * (une nouvelle action en hérite sans rien ajouter).
     */
    public static function middleware(): array
    {
        return [
            function (Request $request, Closure $next) {
                if ($dossier = $request->route('dossier')) {
                    app(AdminDossierService::class)->authorizeAccess($request->user('admin'), $dossier);
                }

                return $next($request);
            },
        ];
    }

    public function index(AdminDossierService $service, PlatformSettingsService $settings): Response
    {
        $admin = auth('admin')->user();
        $personal = $service->seesOnlyOwnDossiers($admin);
        $dossiers = $service->scopedDossiers($admin);
        // Liste allégée : le détail d'une famille est chargé à l'ouverture (route admin.dossiers.family).
        $families = $service->groupFamilies($dossiers, detail: false);

        return Inertia::render('Admin/Dossiers/Index', [
            'families' => $families,
            'queueCounts' => $service->queueCounts($families),
            'gestionnaires' => $service->gestionnaires(),
            'superviseurs' => $service->superviseurs(),
            'validation2Niveaux' => $settings->validation2Niveaux(),
            'fifRequise' => $settings->fifSigneeRequise(),
            'affectationMode' => $settings->get('affectation_mode') ?? 'manuelle',
            // Vue personnelle : agent sans « Consulter tous les dossiers » (il ne voit que les siens).
            'mesStats' => $personal ? $service->statsPourGestionnaire($admin) : null,
            'adminRole' => $admin->role,
            'gestionnaireNom' => $personal ? $admin->display_name : null,
            'isGestionnaire' => $personal,
            'canSupervise' => $admin->hasPermission('dossiers.supervise'),
            'statutOptions' => config('cama.dossier_statuts'),
            'liensOptions' => config('cama.liens_famille'),
            'piecesRequetables' => array_values(array_unique([
                config('cama.fif_piece_type', 'FIF signée'),
                'Acte de naissance',
                'Copie CNIB',
                'Acte de mariage',
                'Pièce justifiant la garde',
                'Acte de divorce',
            ])),
            'motifsRefus' => $settings->motifsRefus(),
        ]);
    }

    /** Détail complet d'un dossier familial (fiche, membres, pièces, messages), chargé à l'ouverture. */
    public function family(Assure $assure, AdminDossierService $service): JsonResponse
    {
        $family = $service->familyDetail(auth('admin')->user(), $assure);

        // Même réponse qu'une famille inexistante : on ne révèle pas l'existence des dossiers d'un autre agent.
        abort_if($family === null, 404);

        return response()->json(['family' => $family]);
    }

    public function assign(Request $request, AdminDossierService $service): RedirectResponse
    {
        $data = $request->validate([
            'assure_id' => ['required', 'integer', 'exists:assures,id'],
            'gestionnaire' => ['required', 'string', 'max:100'],
        ]);

        $service->assignFamily($data['assure_id'], $data['gestionnaire'], auth('admin')->user());

        return back()->with('success', 'Affectation mise à jour.');
    }

    public function assignSupervisor(Request $request, AdminDossierService $service): RedirectResponse
    {
        $data = $request->validate([
            'assure_id' => ['required', 'integer', 'exists:assures,id'],
            'superviseur' => ['nullable', 'string', 'max:100'],
        ]);

        $service->assignSupervisorToFamily($data['assure_id'], (string) ($data['superviseur'] ?? ''), auth('admin')->user());

        return back()->with('success', 'Superviseur mis à jour.');
    }

    public function batchAssign(Request $request, AdminDossierService $service): RedirectResponse
    {
        $data = $request->validate([
            'assure_ids' => ['required', 'array', 'min:1'],
            'assure_ids.*' => ['integer', 'exists:assures,id'],
            'gestionnaire' => ['required', 'string', 'max:100'],
        ]);

        $count = $service->batchAssign($data['assure_ids'], $data['gestionnaire'], auth('admin')->user());

        return back()->with('success', "{$count} dossier(s) affecté(s).");
    }

    public function validate(Dossier $dossier, AdminDossierService $service): RedirectResponse
    {
        $updated = $service->validate($dossier, auth('admin')->user());

        return back()->with('success', $updated->statut === 'En attente supervision'
            ? 'Validation niveau 1 enregistrée — dossier transmis à la supervision.'
            : 'Dossier validé.');
    }

    public function reject(Request $request, Dossier $dossier, AdminDossierService $service): RedirectResponse
    {
        $data = $request->validate(['motif' => ['required', 'string', 'max:500']]);
        $service->refuse($dossier, $data['motif'], auth('admin')->user());

        return back()->with('success', 'Dossier refusé.');
    }

    public function confirmWithdrawal(Request $request, Dossier $dossier, AdminDossierService $service): RedirectResponse
    {
        $data = $request->validate(['motif' => ['required', 'string', 'max:200']]);
        $service->withdraw($dossier, $data['motif'], auth('admin')->user());

        return back()->with('success', 'Membre retiré — il n\'est plus couvert comme ayant droit.');
    }

    public function complement(Request $request, Dossier $dossier, AdminDossierService $service): RedirectResponse
    {
        $data = $request->validate([
            'pieces' => ['nullable', 'array'],
            'pieces.*' => ['string', 'max:150'],
            'message' => ['nullable', 'string', 'max:500'],
        ]);

        $pieces = array_values(array_filter($data['pieces'] ?? []));
        $message = trim($data['message'] ?? '');

        if ($pieces === [] && $message === '') {
            throw ValidationException::withMessages([
                'complement' => 'Sélectionnez au moins une pièce ou rédigez un message à l\'assuré.',
            ]);
        }

        $service->requestComplement($dossier, $pieces, $message !== '' ? $message : null, auth('admin')->user());

        return back()->with('success', 'Demande de complément envoyée.');
    }

    public function enAttente(Dossier $dossier, AdminDossierService $service): RedirectResponse
    {
        $service->setEnAttente($dossier, auth('admin')->user());

        return back()->with('success', 'Dossier mis en attente de supervision.');
    }

    public function message(Request $request, Dossier $dossier, AdminDossierService $service): RedirectResponse
    {
        $data = $request->validate(['texte' => ['required', 'string', 'max:1000']]);
        $service->sendMessage($dossier, $data['texte']);

        return back()->with('success', 'Message envoyé.');
    }
}
