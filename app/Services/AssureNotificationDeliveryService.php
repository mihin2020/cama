<?php

namespace App\Services;

use App\Mail\AssurePlatformNotificationMail;
use App\Models\Assure;
use App\Models\AssureNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AssureNotificationDeliveryService
{
    public function __construct(private PlatformSettingsService $settings) {}

    public function notify(Assure $assure, string $event, string $titre, string $contenu, ?string $lien = null): void
    {
        $channels = $this->settings->assureNotifyChannels();

        if ($channels["{$event}_in_app"] ?? true) {
            AssureNotification::query()->create([
                'assure_id' => $assure->id,
                'type' => $this->mapType($event),
                'titre' => $titre,
                'contenu' => $contenu,
                'lien' => $lien ?? route('assure.membres'),
                'lu' => false,
            ]);
        }

        if (($channels["{$event}_email"] ?? false) && $assure->email) {
            $email = $assure->email;
            $mail = new AssurePlatformNotificationMail($assure, $titre, $contenu, $lien ?? route('assure.membres'));

            // Envoyé après la réponse : l'agent qui traite le dossier n'attend pas le serveur SMTP.
            \App\Support\MailAfterResponse::send("notification assuré ({$email})", fn () => Mail::to($email)->send($mail));
        }
    }

    private function mapType(string $event): string
    {
        return match ($event) {
            'validation', 'refus' => 'validation_refus',
            'complement' => 'piece_complementaire',
            'message' => 'message_gestionnaire',
            default => $event,
        };
    }
}
