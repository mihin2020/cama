<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Services\PlatformSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ParametresController extends Controller
{
    public function index(PlatformSettingsService $settings): Response
    {
        return Inertia::render('Admin/Parametres/Index', [
            'settings' => $settings->all(),
        ]);
    }

    public function updateMembres(Request $request, PlatformSettingsService $settings): RedirectResponse
    {
        $data = $request->validate([
            'age_max_enfant' => ['required', 'integer', 'min:1', 'max:'.PlatformSettingsService::AGE_MAX_ENFANT_ABSOLU],
            'certificat_scolarite_actif' => ['sometimes', 'boolean'],
            'certificat_scolarite_label' => ['nullable', 'string', 'max:150'],
        ]);

        $settings->updateMembres(
            $data['age_max_enfant'],
            $request->boolean('certificat_scolarite_actif'),
            $data['certificat_scolarite_label'] ?? null,
        );

        return back()->with('success', 'Paramètres des membres rattachés enregistrés.');
    }

    public function updatePhotos(Request $request, PlatformSettingsService $settings): RedirectResponse
    {
        $data = $request->validate([
            'conjoint_actif' => ['sometimes', 'boolean'],
            'conjoint_required' => ['sometimes', 'boolean'],
            'enfant_actif' => ['sometimes', 'boolean'],
            'enfant_required' => ['sometimes', 'boolean'],
        ]);

        $settings->updateMembrePhoto([
            'conjoint' => [
                'actif' => $request->boolean('conjoint_actif'),
                'required' => $request->boolean('conjoint_required'),
            ],
            'enfant' => [
                'actif' => $request->boolean('enfant_actif'),
                'required' => $request->boolean('enfant_required'),
            ],
        ]);

        return back()->with('success', 'Paramètres photo des membres enregistrés.');
    }

    public function updateFiliations(Request $request, PlatformSettingsService $settings): RedirectResponse
    {
        $data = $request->validate([
            'filiations' => ['required', 'array', 'min:1'],
            'filiations.*.key' => ['required', 'string', 'max:50'],
            'filiations.*.label' => ['required', 'string', 'max:150'],
            'filiations.*.actif' => ['sometimes', 'boolean'],
            'filiations.*.pieces' => ['present', 'array'],
            'filiations.*.pieces.*.key' => ['required', 'string', 'max:80'],
            'filiations.*.pieces.*.label' => ['required', 'string', 'max:150'],
            'filiations.*.pieces.*.required' => ['sometimes', 'boolean'],
        ]);

        $filiations = array_map(function (array $row) {
            return [
                'key' => $row['key'],
                'label' => $row['label'],
                'actif' => (bool) ($row['actif'] ?? false),
                'pieces' => array_map(fn (array $p) => [
                    'key' => $p['key'],
                    'label' => $p['label'],
                    'required' => (bool) ($p['required'] ?? false),
                ], $row['pieces'] ?? []),
            ];
        }, $data['filiations']);

        $settings->updateEnfantFiliations($filiations);

        return back()->with('success', 'Filiations enfants enregistrées.');
    }

    public function updateFif(Request $request, PlatformSettingsService $settings): RedirectResponse
    {
        $request->validate([
            'fif_signee_requise' => ['sometimes', 'boolean'],
        ]);

        $settings->updateFifSigneeRequise($request->boolean('fif_signee_requise'));

        return back()->with('success', $settings->fifSigneeRequise()
            ? 'FIF signée exigée à la soumission.'
            : 'FIF signée désactivée.');
    }

    public function updateRetention(Request $request, PlatformSettingsService $settings): RedirectResponse
    {
        $data = $request->validate([
            'retention_years' => ['required', 'integer', 'min:1', 'max:30'],
        ]);

        $settings->updateRetention($data['retention_years']);

        return back()->with('success', 'Durée de rétention mise à jour.');
    }

    public function updateAffectation(Request $request, PlatformSettingsService $settings): RedirectResponse
    {
        $data = $request->validate([
            'affectation_mode' => ['required', 'in:manuelle,round_robin,charge_min'],
            'validation_2niveaux' => ['sometimes', 'boolean'],
        ]);

        $settings->updateAffectation($data['affectation_mode'], $request->boolean('validation_2niveaux'));

        return back()->with('success', "Règles d'affectation mises à jour.");
    }

    public function updateLibelles(Request $request, PlatformSettingsService $settings): RedirectResponse
    {
        $data = $request->validate([
            'motifs_refus' => ['required', 'array', 'min:1'],
            'motifs_refus.*' => ['required', 'string', 'max:200'],
            'email_validation' => ['required', 'string', 'max:1000'],
            'email_complement' => ['required', 'string', 'max:1000'],
            'email_refus' => ['required', 'string', 'max:1000'],
            'email_message' => ['required', 'string', 'max:1000'],
            'notify_validation_in_app' => ['sometimes', 'boolean'],
            'notify_validation_email' => ['sometimes', 'boolean'],
            'notify_refus_in_app' => ['sometimes', 'boolean'],
            'notify_refus_email' => ['sometimes', 'boolean'],
            'notify_complement_in_app' => ['sometimes', 'boolean'],
            'notify_complement_email' => ['sometimes', 'boolean'],
            'notify_message_in_app' => ['sometimes', 'boolean'],
            'notify_message_email' => ['sometimes', 'boolean'],
        ]);

        $settings->updateLibelles($data['motifs_refus'], [
            'validation' => $data['email_validation'],
            'complement' => $data['email_complement'],
            'refus' => $data['email_refus'],
            'message' => $data['email_message'],
        ], [
            'validation_in_app' => $request->boolean('notify_validation_in_app'),
            'validation_email' => $request->boolean('notify_validation_email'),
            'refus_in_app' => $request->boolean('notify_refus_in_app'),
            'refus_email' => $request->boolean('notify_refus_email'),
            'complement_in_app' => $request->boolean('notify_complement_in_app'),
            'complement_email' => $request->boolean('notify_complement_email'),
            'message_in_app' => $request->boolean('notify_message_in_app'),
            'message_email' => $request->boolean('notify_message_email'),
        ]);

        return back()->with('success', 'Libellés et modèles enregistrés.');
    }

    public function updateStructure(Request $request, PlatformSettingsService $settings): RedirectResponse
    {
        $data = $request->validate([
            'armees' => ['required', 'array'],
            'armees.*' => ['string', 'max:150'],
            'categories' => ['required', 'array'],
            'categories.*' => ['string', 'max:150'],
            'grades' => ['required', 'array'],
            'grades.*' => ['string', 'max:150'],
            'groupesSanguins' => ['required', 'array'],
            'groupesSanguins.*' => ['string', 'max:10'],
            'regions' => ['required', 'array'],
        ]);

        $settings->updateOrgStructure([
            'armees' => $data['armees'],
            'categories' => $data['categories'],
            'grades' => $data['grades'],
            'groupesSanguins' => $data['groupesSanguins'],
            'regions' => $data['regions'],
        ]);

        return back()->with('success', 'Structure militaire enregistrée.');
    }

    public function resetStructure(PlatformSettingsService $settings): RedirectResponse
    {
        PlatformSetting::query()->where('key', 'org_structure')->delete();

        return back()->with('success', 'Structure militaire réinitialisée.');
    }

    public function updateInscriptionDocuments(Request $request, PlatformSettingsService $settings): RedirectResponse
    {
        $data = $request->validate([
            'documents' => ['present', 'array'],
            'documents.*.key' => ['required', 'string', 'max:20'],
            'documents.*.actif' => ['boolean'],
            'documents.*.titre' => ['nullable', 'string', 'max:100'],
        ]);

        foreach ($data['documents'] as $slot) {
            if (($slot['actif'] ?? false) && trim((string) ($slot['titre'] ?? '')) === '') {
                return back()->withErrors([
                    'documents' => 'Chaque pièce activée doit avoir un titre.',
                ]);
            }
        }

        $settings->updateInscriptionDocuments($data['documents']);

        return back()->with('success', "Pièces d'inscription enregistrées.");
    }
}
