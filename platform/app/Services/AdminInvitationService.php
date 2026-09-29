<?php

namespace App\Services;

use App\Mail\AdminUserInvitationMail;
use App\Models\AdminUser;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;

class AdminInvitationService
{
    public function send(AdminUser $user, AdminUser $invitedBy): bool
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

            return true;
        } catch (\Throwable $e) {
            Log::warning('Échec envoi invitation compte interne CAMA', [
                'error' => $e->getMessage(),
                'admin_user_id' => $user->id,
                'email' => $user->email,
            ]);

            return false;
        }
    }
}
