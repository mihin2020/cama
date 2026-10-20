<?php

/*
 * Routes exposées au JavaScript (helper route()) : chaque page ne reçoit que celles de
 * son espace. Le site public ne dévoile ainsi aucune adresse du back-office, et chaque
 * page est plus légère. Le groupe est choisi dans resources/views/app.blade.php.
 */
return [
    'groups' => [
        // Visiteurs et assurés.
        'site' => ['home', 'public.*', 'registration.*', 'assure.*'],

        // Pages de connexion / invitation du back-office (agent non connecté).
        'admin-guest' => ['home', 'public.*', 'admin.login*', 'admin.invitation.*'],

        // Agent connecté : back-office complet (le page builder affiche aussi des pages publiques).
        'admin' => ['*', '!sanctum.*', '!storage.*'],
    ],
];
