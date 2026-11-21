<?php

namespace App\Services;

use App\Mail\AdminUserInvitationMail;
use App\Models\AdminNotification;
use App\Models\AdminUser;
use App\Support\MailAfterResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Throwable;

class AdminInvitationService
{
    /**
     * Prépare l'invitation tout de suite, puis l'envoie après la réponse (le serveur SMTP est lent) :
     * la page s'affiche sans attendre.
     *
     * Renvoie null si l'invitation est prête et part en arrière-plan, sinon la raison de l'échec
     * (erreur de l'application, détectée avant l'envoi). Si c'est l'envoi SMTP qui échoue ensuite,
     * l'administrateur qui a invité est prévenu dans ses notifications, avec la cause exacte.
     */
    public function send(AdminUser $user, AdminUser $invitedBy): ?string
    {
        try {
            $token = Password::broker('admin_users')->createToken($user);

            $mail = new AdminUserInvitationMail(
                user: $user,
                setupUrl: URL::route('admin.invitation.show', ['token' => $token, 'email' => $user->email]),
                invitedBy: $invitedBy->display_name_with_grade,
            );

            // Le contenu est généré maintenant : une erreur dans le modèle de l'e-mail est signalée
            // tout de suite, sans être confondue avec un problème SMTP.
            $mail->render();
        } catch (Throwable $e) {
            report($e);

            return "une erreur interne a empêché la préparation de l'e-mail (détail enregistré dans le journal de l'application).";
        }

        $email = $user->email;
        $inviterId = $invitedBy->id;

        MailAfterResponse::send(
            "invitation du compte interne {$email}",
            fn () => Mail::to($email)->send($mail),
            fn (Throwable $e) => AdminNotification::query()->create([
                'admin_user_id' => $inviterId,
                'type' => 'compte',
                'titre' => "Invitation non envoyée à {$email}",
                'contenu' => $e instanceof TransportExceptionInterface
                    ? "Le serveur d'envoi d'e-mails (SMTP) a refusé ou n'a pas pu recevoir l'invitation. Vérifiez la configuration SMTP, puis renvoyez l'invitation depuis « Comptes internes »."
                    : "Une erreur interne a empêché l'envoi de l'invitation (détail dans le journal de l'application). Renvoyez-la depuis « Comptes internes ».",
                'lien' => route('admin.utilisateurs'),
                'lu' => false,
            ]),
        );

        return null;
    }
}
