<?php

/*
 * Point d'entrée unique : toutes les requêtes passent par ce fichier.
 * Il charge les classes, démarre la session puis confie la requête au routeur.
 */

declare(strict_types=1);

use App\Core\Router;

define('APP_ROOT', dirname(__DIR__));

// Chargement automatique : App\Models\Dish => app/Models/Dish.php
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relativePath = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = APP_ROOT . '/app/' . $relativePath . '.php';

    if (is_file($file)) {
        require $file;
    }
});

require APP_ROOT . '/app/Core/helpers.php';

session_start();

$router = new Router();
$registerRoutes = require APP_ROOT . '/config/routes.php';
$registerRoutes($router);

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
