<?php

namespace App\Services;

use App\Mail\AdminUserInvitationMail;
use App\Models\AdminUser;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class AdminInvitationService
{
    /**
     * Envoie l'invitation. Renvoie null si l'e-mail est parti, sinon la raison de l'échec, exacte :
     * une panne du serveur d'e-mails (SMTP) n'est pas confondue avec une erreur de l'application.
     */
    public function send(AdminUser $user, AdminUser $invitedBy): ?string
    {
        $token = Password::broker('admin_users')->createToken($user);

        $setupUrl = URL::route('admin.invitation.show', [
            'token' => $token,
            'email' => $user->email,
        ]);

        try {
            Mail::to($user->email)->send(new AdminUserInvitationMail(
                user: $user,
                setupUrl: $setupUrl,
                invitedBy: $invitedBy->display_name_with_grade,
            ));

            return null;
        } catch (TransportExceptionInterface $e) {
            Log::warning('Échec envoi invitation compte interne CAMA (serveur SMTP)', [
                'error' => $e->getMessage(),
                'admin_user_id' => $user->id,
                'email' => $user->email,
            ]);

            return "le serveur d'envoi d'e-mails (SMTP) a refusé ou n'a pas pu recevoir le message. Vérifiez la configuration SMTP.";
        } catch (\Throwable $e) {
            // Erreur de l'application (ex. dans le modèle de l'e-mail) : signalée comme une erreur, pas comme un problème SMTP.
            report($e);

            return "une erreur interne a empêché la préparation de l'e-mail (détail enregistré dans le journal de l'application).";
        }
    }
}
