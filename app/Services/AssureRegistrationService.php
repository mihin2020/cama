<?php

namespace App\Services;

use App\Enums\AssureStatut;
use App\Mail\AssurePlatformNotificationMail;
use App\Models\Assure;
use App\Models\AssureNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class AssureRegistrationService
{
    public function register(array $data): Assure
    {
        if (Assure::query()->where('email', $data['email'])->exists()) {
            throw ValidationException::withMessages([
                'email' => 'Un compte existe déjà avec cette adresse e-mail.',
            ]);
        }

        if (Assure::query()->where('matricule', $data['matricule'])->exists()) {
            throw ValidationException::withMessages([
                'matricule' => 'Ce matricule militaire est déjà enregistré.',
            ]);
        }

        $now = now()->format('d/m/Y H:i');

        return Assure::query()->create([
            'nom' => $data['nom'],
            'prenom' => $data['prenom'],
            'sexe' => $data['sexe'],
            'matricule' => $data['matricule'],
            'numero_informatique' => $data['numero_informatique'] ?? null,
            'grade' => $data['grade'],
            'categorie' => $data['categorie'],
            'numero_cim' => $data['numero_cim'],
            'numero_cama' => $data['numero_cama'],
            'numero_iup' => $data['numero_iup'] ?? null,
            'armee' => $data['armee'],
            'region' => $data['region'],
            'corps' => $data['corps'] ?? null,
            'service' => $data['service'] ?? null,
            'section' => $data['section'] ?? null,
            'sous_section' => $data['sous_section'] ?? null,
            'telephone' => $data['telephone'],
            'email' => $data['email'],
            'personne_a_prevenir' => $data['personne_a_prevenir'] ?? null,
            'tel_personne_a_prevenir' => $data['tel_personne_a_prevenir'] ?? null,
            'documents_identite' => $data['documents_identite'] ?? [],
            'password' => $data['password'],
            'statut' => AssureStatut::EnAttenteValidation,
            'journal' => [
                ['date' => $now, 'libelle' => 'Demande d\'inscription soumise par l\'assuré'],
            ],
        ]);
    }

    public function updateIdentifiers(Assure $assure, string $numeroCim, string $numeroCama): Assure
    {
        $assure->update([
            'numero_cim' => trim($numeroCim),
            'numero_cama' => trim($numeroCama),
        ]);

        return $assure->fresh();
    }

    public function validate(Assure $assure): Assure
    {
        if (! trim($assure->numero_cim) || ! trim($assure->numero_cama)) {
            throw ValidationException::withMessages([
                'numero_cim' => 'Le N° CIM et le N° Carte CAMA doivent être renseignés avant validation.',
            ]);
        }

        return DB::transaction(function () use ($assure) {
            $now = now()->format('d/m/Y H:i');
            $journal = $assure->journal ?? [];
            $journal[] = [
                'date' => $now,
                'libelle' => "Inscription validée — compte activé (CIM : {$assure->numero_cim}, Carte CAMA : {$assure->numero_cama})",
            ];

            $assure->update([
                'statut' => AssureStatut::Actif,
                'motif_refus' => null,
                'journal' => $journal,
            ]);

            AssureNotification::query()->create([
                'assure_id' => $assure->id,
                'type' => 'validation_compte',
                'titre' => 'Validation administrative',
                'contenu' => 'Votre compte assuré a été validé. Vous pouvez enrôler vos membres de famille.',
                'lu' => false,
            ]);

            return $assure->fresh();
        });

        $this->sendStatusEmail(
            $fresh,
            'Votre compte CAMA est validé',
            "Bonjour {$fresh->full_name},\n\n"
                ."Votre compte assuré a été validé.\n"
                ."N° CIM : {$fresh->numero_cim}\n"
                ."N° Carte CAMA : {$fresh->numero_cama}\n\n"
                ."Vous pouvez dès à présent vous connecter à votre espace assuré et enrôler vos ayants droit.",
        );

        return $fresh;
    }

    public function reject(Assure $assure, string $motif): Assure
    {
        $fresh = DB::transaction(function () use ($assure, $motif) {
            $now = now()->format('d/m/Y H:i');
            $journal = $assure->journal ?? [];
            $journal[] = [
                'date' => $now,
                'libelle' => "Inscription refusée : {$motif}",
            ];

            $assure->update([
                'statut' => AssureStatut::Refuse,
                'motif_refus' => $motif,
                'journal' => $journal,
            ]);

            AssureNotification::query()->create([
                'assure_id' => $assure->id,
                'type' => 'validation_compte',
                'titre' => 'Demande d\'inscription refusée',
                'contenu' => "Votre demande d'inscription a été refusée. Motif : {$motif}",
                'lu' => false,
            ]);

            return $assure->fresh();
        });

        $this->sendStatusEmail(
            $fresh,
            'Votre demande d\'inscription CAMA a été refusée',
            "Bonjour {$fresh->full_name},\n\n"
                ."Votre demande d'inscription à la CAMA n'a pas pu être validée.\n\n"
                ."Motif du refus : {$motif}\n\n"
                ."Vous pouvez corriger votre demande puis la soumettre à nouveau, ou contacter la CAMA pour plus d'informations.",
        );

        return $fresh;
    }

    /**
     * Envoie un e-mail d'information à l'assuré sans bloquer la réponse HTTP
     * (envoi juste après l'envoi de la réponse au navigateur : validation instantanée).
     */
    private function sendStatusEmail(Assure $assure, string $titre, string $contenu): void
    {
        if (! $assure->email) {
            return;
        }

        $email = $assure->email;
        $lien = route('assure.login');
        $mailable = new AssurePlatformNotificationMail($assure, $titre, $contenu, $lien);

        dispatch(function () use ($email, $mailable) {
            try {
                Mail::to($email)->send($mailable);
            } catch (\Throwable $e) {
                Log::warning('E-mail statut inscription échoué : '.$e->getMessage());
            }
        })->afterResponse();
    }

    public function registrationStats(): array
    {
        $counts = Assure::query()
            ->selectRaw('statut, count(*) as c')
            ->groupBy('statut')
            ->pluck('c', 'statut');

        return [
            'pending' => (int) ($counts[AssureStatut::EnAttenteValidation->value] ?? 0),
            'validated' => (int) ($counts[AssureStatut::Actif->value] ?? 0),
            'refused' => (int) ($counts[AssureStatut::Refuse->value] ?? 0),
            'total' => (int) $counts->sum(),
        ];
    }
}
