<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Auth\PasswordResetController as BasePasswordResetController;

class PasswordResetController extends BasePasswordResetController
{
    protected function broker(): string
    {
        return 'admin_users';
    }

    protected function routePrefix(): string
    {
        return 'admin';
    }

    protected function pageDirectory(): string
    {
        return 'Admin/Auth';
    }

    protected function spaceLabel(): string
    {
        return 'le back-office';
    }

    /** Un compte désactivé ne peut ni recevoir de lien ni réinitialiser son mot de passe. */
    protected function accountConditions(): array
    {
        return ['actif' => true];
    }
}
