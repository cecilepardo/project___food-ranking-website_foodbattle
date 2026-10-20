<?php

return [
    'env' => getenv('APP_ENV') ?: 'prod',
    'db' => [
        'host' => getenv('DB_HOST') ?: 'localhost',
        'port' => getenv('DB_PORT') ?: '5432',
        'name' => getenv('DB_NAME') ?: 'foodbattle',
        'user' => getenv('DB_USER') ?: 'foodbattle',
        'password' => getenv('DB_PASSWORD') ?: '',
    ],
];
