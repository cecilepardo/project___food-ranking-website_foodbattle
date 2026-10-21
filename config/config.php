<?php

/*
 * Configuration de l'application.
 * Les valeurs viennent des variables d'environnement (voir compose.yaml).
 */

return [
    // 'dev' affiche les erreurs PHP dans la page. Valeur sûre par défaut : 'prod'.
    'env' => getenv('APP_ENV') ?: 'prod',

    'db' => [
        'host'     => getenv('DB_HOST') ?: 'localhost',
        'port'     => getenv('DB_PORT') ?: '5432',
        'name'     => getenv('DB_NAME') ?: 'foodbattle',
        'user'     => getenv('DB_USER') ?: 'foodbattle',
        'password' => getenv('DB_PASSWORD') ?: 'foodbattle_local',
    ],
];
