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
 * Note et votes d'un plat : "4,6/5 · 3 votes", ou "— · 1 vote" si personne ne l'a encore noté.
 * En français, 0 et 1 sont au singulier.
 */
function rating_summary(array $dish): string
{
    $votes  = (int) $dish['votes'];
    $rating = $dish['average'] ? score($dish['average']) . '/5' : '—';

    return $rating . ' · ' . $votes . ' ' . ($votes > 1 ? 'votes' : 'vote');
}

/**
 * Champ caché contenant le jeton CSRF, à placer dans chaque formulaire POST.
 */
function csrf_field(): string
{
    return '<input type="hidden" name="token" value="' . e(Csrf::token()) . '">';
}

/**
 * Attributs ARIA d'un champ de formulaire : en erreur, il est signalé invalide et relié au message
 * (<p id="form-error">) ; $hintId relie en plus une aide permanente (ex. « 8 caractères minimum »).
 */
function field_attrs(bool $isInvalid, string $hintId = ''): string
{
    $describedBy = trim(($isInvalid ? 'form-error ' : '') . $hintId);

    return ($isInvalid ? ' aria-invalid="true"' : '')
        . ($describedBy !== '' ? ' aria-describedby="' . e($describedBy) . '"' : '');
}

/**
 * Photo d'un plat : celle envoyée par l'admin, sinon celle du plat de démo, sinon celle de sa catégorie.
 * Voir App\Core\DishPhoto.
 */
function image_for(array $dish): string
{
    return \App\Core\DishPhoto::url($dish);
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
    return current_section_attr($path, []);
}

/**
 * Attribut aria-current pour le lien d'une rubrique qui regroupe plusieurs pages
 * (ex. « Dashboard » pour toute l'administration).
 * - aria-current="page" sur la page du lien elle-même ;
 * - aria-current="true" sur une autre page de la rubrique (adresse qui commence par un des préfixes) ;
 * - rien ailleurs.
 */
function current_section_attr(string $path, array $prefixes): string
{
    $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

    if ($currentPath === $path) {
        return ' aria-current="page"';
    }

    foreach ($prefixes as $prefix) {
        // « /dashboard » ou « /dashboard/… », mais pas « /dashboardx »
        if ($currentPath === $prefix || str_starts_with($currentPath, $prefix . '/')) {
            return ' aria-current="true"';
        }
    }

    return '';
}

function reject_invalid_utf8(mixed $value): void
{
    if (is_string($value) && !mb_check_encoding($value, 'UTF-8')) {
        http_response_code(400);
        exit('Requête invalide : encodage non reconnu.');
    }
}
