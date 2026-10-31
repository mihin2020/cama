<?php

namespace App\Http\Controllers\Assure\Auth;

use App\Http\Controllers\Auth\PasswordResetController as BasePasswordResetController;

class PasswordResetController extends BasePasswordResetController
{
    protected function broker(): string
    {
        return 'assures';
    }

    protected function routePrefix(): string
    {
        return 'assure';
    }

    protected function pageDirectory(): string
    {
        return 'Assure/Auth';
    }

    protected function spaceLabel(): string
    {
        return "l'espace assuré";
    }
}
