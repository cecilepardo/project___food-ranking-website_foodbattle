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

function reject_invalid_utf8(mixed $value): void
{
    if (is_string($value) && !mb_check_encoding($value, 'UTF-8')) {
        http_response_code(400);
        exit('Requête invalide : encodage non reconnu.');
    }
}
