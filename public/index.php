<?php

declare(strict_types=1);

use App\Core\Router;
use App\Core\Session;

define('ROOT_PATH', dirname(__DIR__));

spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'App\\')) {
        $file = ROOT_PATH . '/src/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});
require ROOT_PATH . '/src/helpers.php';

$config = require ROOT_PATH . '/config/config.php';
ini_set('display_errors', $config['env'] === 'dev' ? '1' : '0');

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data: https://images.pexels.com; style-src 'self' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; script-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'");

Session::start();

$router = new Router();
(require ROOT_PATH . '/config/routes.php')($router);
$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
