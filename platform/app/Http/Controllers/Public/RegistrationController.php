<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterAssureRequest;
use App\Services\AssureEmailVerificationService;
use App\Services\AssureRegistrationService;
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
        ]);
    }

    public function store(
        RegisterAssureRequest $request,
        AssureRegistrationService $registration,
        AssureEmailVerificationService $verification,
        PlatformSettingsService $settings,
    ): RedirectResponse {
        $data = $request->validated();
        $data['documents_identite'] = $this->storeIdentityDocuments($request, $settings);

        $assure = $registration->register($data);

        Auth::guard('assure')->login($assure);
        $request->session()->regenerate();

        try {
            $verification->sendCode($assure);
        } catch (\Throwable) {
            // L'inscription reste valide même si l'envoi échoue : l'assuré pourra
            // demander un renvoi depuis l'écran de vérification.
        }

        return redirect()->route('assure.verification.notice')
            ->with('success', "Votre demande d'inscription a été enregistrée. Un code de vérification à 4 chiffres a été envoyé à {$assure->email}.");
    }

    /**
     * Stocke les pièces d'identité téléversées et retourne les métadonnées à persister.
     *
     * @return array<int, array<string, mixed>>
     */
    private function storeIdentityDocuments(RegisterAssureRequest $request, PlatformSettingsService $settings): array
    {
        $slots = $settings->activeInscriptionDocuments();
        $stored = [];

        foreach ($slots as $slot) {
            $field = "documents.{$slot['key']}";
            if (! $request->hasFile($field)) {
                continue;
            }

            $file = $request->file($field);
            $matricule = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $request->input('matricule')) ?: 'assure';
            $path = $file->store("inscriptions/{$matricule}", 'local');

            $stored[] = [
                'key' => $slot['key'],
                'label' => $slot['titre'],
                'path' => $path,
                'filename' => $file->getClientOriginalName(),
                'mime' => $file->getMimeType(),
                'size' => $file->getSize(),
            ];
        }

        return $stored;
    }
}
