<?php

/*
 * Hachage des mots de passe : bcrypt pour tous les comptes (administrateurs et assurés).
 * Les mots de passe sont hachés automatiquement (cast « hashed » des modèles). Un mot de passe
 * encore stocké avec un autre algorithme ou un coût plus faible est re-haché en bcrypt à la
 * connexion suivante (rehash_on_login).
 */
return [

    'driver' => 'bcrypt',

    'bcrypt' => [
        'rounds' => (int) env('BCRYPT_ROUNDS', 12),
        // Refuse de vérifier un mot de passe qui ne serait pas un hachage bcrypt.
        'verify' => (bool) env('HASH_VERIFY', true),
        'limit' => null,
    ],

    'argon' => [
        'memory' => 65536,
        'threads' => 1,
        'time' => 4,
        'verify' => true,
    ],

    'rehash_on_login' => true,

];
