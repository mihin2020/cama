<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Dossier;
use App\Services\AdminDossierService;
use App\Services\PlatformSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DossierController extends Controller
{
    public function index(AdminDossierService $service, PlatformSettingsService $settings): Response
    {
        $admin = auth('admin')->user();
        $personal = $service->seesOnlyOwnDossiers($admin);
        $dossiers = $service->scopedDossiers($admin);
        $families = $service->groupFamilies($dossiers);

        return Inertia::render('Admin/Dossiers/Index', [
            'families' => $families,
            'queueCounts' => $service->queueCounts($families),
            'gestionnaires' => $service->gestionnaires(),
            'validation2Niveaux' => $settings->validation2Niveaux(),
            'fifRequise' => $settings->fifSigneeRequise(),
            'affectationMode' => $settings->get('affectation_mode') ?? 'manuelle',
            // Vue personnelle : agent sans « Consulter tous les dossiers » (il ne voit que les siens).
            'mesStats' => $personal ? $service->statsPourGestionnaire($admin) : null,
            'adminRole' => $admin->role->value,
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

    public function assign(Request $request, AdminDossierService $service): RedirectResponse
    {
        $data = $request->validate([
            'assure_id' => ['required', 'integer', 'exists:assures,id'],
            'gestionnaire' => ['required', 'string', 'max:100'],
        ]);

        $service->assignFamily($data['assure_id'], $data['gestionnaire'], auth('admin')->user());

        return back()->with('success', 'Affectation mise à jour.');
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
