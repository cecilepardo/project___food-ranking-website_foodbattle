<?php

/*
 * Petites fonctions utilisées dans les vues.
 */

use App\Core\Csrf;

/**
 * Échappe un texte avant de l'afficher dans le HTML.
 */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * Formate un prix : 12.5 => "12,50 €"
 */
function euro(float|string $amount): string
{
    return number_format((float) $amount, 2, ',', ' ') . ' €';
}

/**
 * Formate une note moyenne : 3.6667 => "3,7"
 */
function score(float|string|null $average): string
{
    return number_format((float) $average, 1, ',', '');
}

/**
 * Champ caché contenant le jeton CSRF, à placer dans chaque formulaire POST.
 */
function csrf_field(): string
{
    return '<input type="hidden" name="token" value="' . e(Csrf::token()) . '">';
}

/**
 * Photo d'illustration selon la catégorie du plat.
 */
function image_for(string $category): string
{
    $photoIds = [
        'burger' => '1639557',
        'tacos'  => '4958792',
        'pizza'  => '315755',
    ];
    $id = $photoIds[$category] ?? $photoIds['burger'];

    return "https://images.pexels.com/photos/{$id}/pexels-photo-{$id}.jpeg?auto=compress&cs=tinysrgb&w=800";
}

/**
 * URL d'un fichier de public/ avec sa date de modification en paramètre (?v=…).
 * Quand le fichier change, l'URL change : le navigateur ne peut pas réutiliser une ancienne copie en cache.
 * Ex. asset('/assets/css/base.css') => "/assets/css/base.css?v=1793008764"
 */
function asset(string $path): string
{
    $file = APP_ROOT . '/public' . $path;

    return is_file($file) ? $path . '?v=' . filemtime($file) : $path;
}

/**
 * Logo adapté au thème : plus foncé sur fond clair pour rester lisible (contraste).
 */
function logo_url(): string
{
    return asset(\App\Core\Theme::isLight() ? '/assets/img/logo-light.png' : '/assets/img/logo.png');
}

/**
 * Attribut aria-current="page" si $path est la page affichée, sinon rien.
 * Sert à signaler le lien actif du menu (couleur en CSS, annonce par les lecteurs d'écran).
 */
function current_page_attr(string $path): string
{
    $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

    return $currentPath === $path ? ' aria-current="page"' : '';
}

function reject_invalid_utf8(mixed $value): void
{
    if (is_string($value) && !mb_check_encoding($value, 'UTF-8')) {
        http_response_code(400);
        exit('Requête invalide : encodage non reconnu.');
    }
}
