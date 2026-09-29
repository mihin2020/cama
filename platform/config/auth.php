<?php

use App\Models\AdminUser;
use App\Models\Assure;

return [

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'assures'),
    ],

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
        'assure' => [
            'driver' => 'session',
            'provider' => 'assures',
        ],
        'admin' => [
            'driver' => 'session',
            'provider' => 'admin_users',
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
