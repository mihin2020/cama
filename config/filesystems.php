<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Fichiers du site public (images, documents, médiathèque, logo)
    |--------------------------------------------------------------------------
    |
    | Disque qui reçoit les fichiers envoyés depuis le studio de contenu. Ils ne sont PAS rangés
    | dans « public/ » (remplacé à chaque déploiement) mais sur le stockage de Laravel, conservé
    | entre deux déploiements. « public » = storage/app/public (servi à /storage) ; « s3 » = un
    | stockage distant, pour un hébergement sans disque persistant (conteneurs, plateformes
    | éphémères). Voir App\Support\SiteUploads.
    |
    */

    'site_uploads_disk' => env('SITE_UPLOADS_DISK', 'public'),

    // Dossier des anciens fichiers du site (public/ si vide) : sert à la reprise « uploads:move-to-storage ».
    'legacy_uploads_root' => env('LEGACY_UPLOADS_ROOT'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            // Pièces des assurés : aucune adresse directe, elles ne sortent que par les contrôleurs
            // qui vérifient les droits (voir DossierPieceController, InscriptionController).
            'serve' => false,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            // Servi par Laravel tant que le lien « public/storage » n'existe pas (php artisan storage:link) :
            // le site fonctionne aussi là où les liens symboliques sont impossibles (Windows, hébergement mutualisé).
            'serve' => true,
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
