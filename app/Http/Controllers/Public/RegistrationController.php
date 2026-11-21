<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterAssureRequest;
use App\Services\AssureEmailVerificationService;
use App\Services\AssureRegistrationService;
use App\Services\DossierPieceStorageService;
use App\Services\PlatformSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class RegistrationController extends Controller
{
    public function create(PlatformSettingsService $settings): Response
    {
        return Inertia::render('Public/Registration', [
            'orgStructure' => $settings->orgStructure(),
            'inscriptionDocuments' => $settings->activeInscriptionDocuments(),
            'inscriptionFields' => $settings->inscriptionFieldsMap(),
        ]);
    }

    public function store(
        RegisterAssureRequest $request,
        AssureRegistrationService $registration,
        AssureEmailVerificationService $verification,
        PlatformSettingsService $settings,
        DossierPieceStorageService $storage,
    ): RedirectResponse {
        $data = $request->validated();

        $assure = $registration->register($data);

        // Les pièces sont rangées après la création du compte : leur nom contient son N° FIF.
        $assure->update(['documents_identite' => $this->storeIdentityDocuments($request, $settings, $storage, $assure)]);

        Auth::guard('assure')->login($assure);
        $request->session()->regenerate();

        try {
            $verification->sendCode($assure);
        } catch (\Throwable) {
            // L'inscription reste valide même si l'envoi échoue : l'assuré pourra
            // demander un renvoi depuis l'écran de vérification.
        }

        return redirect()->route('assure.verification.notice')
            ->with('success', "Votre demande d'inscription a été enregistrée. Un code de vérification de 6 caractères (chiffres et lettres) a été envoyé à {$assure->email}.");
    }

    /**
     * Stocke les pièces d'identité téléversées et retourne les métadonnées à persister.
     *
     * @return array<int, array<string, mixed>>
     */
    private function storeIdentityDocuments(
        RegisterAssureRequest $request,
        PlatformSettingsService $settings,
        DossierPieceStorageService $storage,
        \App\Models\Assure $assure,
    ): array {
        $slots = $settings->activeInscriptionDocuments();
        $stored = [];

        foreach ($slots as $slot) {
            $field = "documents.{$slot['key']}";
            if (! $request->hasFile($field)) {
                continue;
            }

            $stored[] = $storage->storeIdentityDocument($assure, $request->file($field), $slot['key'], $slot['titre']);
        }

        return $stored;
    }
}
