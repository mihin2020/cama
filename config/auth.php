<?php

use App\Models\AdminUser;
use App\Models\Assure;

return [

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'assures'),
    ],

    // Back-office : déconnexion après N minutes sans activité, pour les admins qui
    // ont activé l'option dans leur profil (désactivée par défaut). Hors appareil
    // mémorisé par « Se souvenir de moi », borné par la durée « remember » ci-dessous.
    'admin_idle_timeout' => (int) env('ADMIN_IDLE_TIMEOUT_MINUTES', 15),

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
        // « remember » = durée de vie (minutes) du cookie « Se souvenir de moi ».
        // Par défaut Laravel le garde 400 jours : beaucoup trop pour un back-office.
        'assure' => [
            'driver' => 'session',
            'provider' => 'assures',
            'remember' => (int) env('AUTH_ASSURE_REMEMBER_MINUTES', 60 * 24 * 1),
        ],
        'admin' => [
            'driver' => 'session',
            'provider' => 'admin_users',
            'remember' => (int) env('AUTH_ADMIN_REMEMBER_MINUTES', 60 * 24 * 3),
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', App\Models\User::class),
        ],
        'assures' => [
            'driver' => 'eloquent',
            'model' => Assure::class,
        ],
        'admin_users' => [
            'driver' => 'eloquent',
            'model' => AdminUser::class,
        ],
    ],

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
        'assures' => [
            'provider' => 'assures',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
        'admin_users' => [
            'provider' => 'admin_users',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
