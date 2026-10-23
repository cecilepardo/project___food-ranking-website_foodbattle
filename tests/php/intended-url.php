<?php
// Vérifie quels chemins Auth::rememberIntendedUrl() accepte (protection contre la redirection ouverte).
// Lancé par tests/auth.sh : docker compose exec -T app php < tests/php/intended-url.php
declare(strict_types=1);

define('APP_ROOT', '/var/www/html');
spl_autoload_register(function (string $class): void {
    $file = APP_ROOT . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

$_SESSION = [];
$cases = ['/moderation?page=2', '//pirate.com', '/\\pirate.com', 'https://pirate.com', 'javascript:alert(1)', ''];

foreach ($cases as $url) {
    \App\Core\Auth::rememberIntendedUrl($url);
    $kept = \App\Core\Auth::pullIntendedUrl();
    echo str_pad($url === '' ? '(vide)' : $url, 22), ' => ', $kept === null ? 'refusé' : "accepté ($kept)", PHP_EOL;
}
