<?php

/*
 * Point d'entrée unique : toutes les requêtes passent par ce fichier.
 * Il charge les classes, démarre la session puis confie la requête au routeur.
 */

declare(strict_types=1);

use App\Core\ErrorHandler;
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

$config = require APP_ROOT . '/config/config.php';

// En production, une erreur ne doit jamais montrer au visiteur des chemins ou du code.
// Elle reste visible dans les logs : docker compose logs app
ini_set('display_errors', $config['env'] === 'dev' ? '1' : '0');

// Erreur non prévue => page 500 du site (avec le détail technique en dev seulement).
ErrorHandler::register($config['env'] === 'dev');

// Interdit d'afficher le site dans une <iframe> d'un autre site (clickjacking sur les boutons admin),
// et empêche le navigateur de deviner un autre type de contenu que celui annoncé.
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
// Politique de sécurité du contenu : le navigateur ne charge que nos propres fichiers
// (photos comprises). Un script injecté malgré l'échappement ne s'exécuterait pas.
header("Content-Security-Policy: default-src 'self'; "
    . "object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
// Les autres sites ne voient pas d'où viennent nos visiteurs (ex. /dashboard/plats/3/modifier).
header('Referrer-Policy: same-origin');

array_walk_recursive($_GET, 'reject_invalid_utf8');
array_walk_recursive($_POST, 'reject_invalid_utf8');

// Cookie de session : illisible par JavaScript (httponly), pas envoyé par les autres sites
// lors d'un POST (samesite), et transmis uniquement en HTTPS quand le site est en HTTPS (secure).
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);
// Refuse un identifiant de session inventé par le navigateur (complète session_regenerate_id).
ini_set('session.use_strict_mode', '1');
session_start();

$router = new Router();
$registerRoutes = require APP_ROOT . '/config/routes.php';
$registerRoutes($router);

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
