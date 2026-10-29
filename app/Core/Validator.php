<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\Dish;

/**
 * Règles de format partagées par les controllers : ce qu'un texte saisi a le droit de contenir.
 * Les longueurs maximales propres à chaque formulaire sont passées par les controllers.
 */
class Validator
{
    public const NICKNAME_MIN_LENGTH = 3;
    public const NICKNAME_MAX_LENGTH = 30;

    /**
     * Lettres latines (accents compris) et chiffres ; ". _ -" et des espaces simples à l'intérieur.
     * Commence et finit par une lettre ou un chiffre. N'accepter que l'écriture latine
     * empêche « Аdmin » écrit avec un A cyrillique, identique à l'œil.
     */
    private const NICKNAME_PATTERN = '/^[\p{Latin}\p{N}](?:[\p{Latin}\p{N}._-]| (?! ))*[\p{Latin}\p{N}]$/u';

    /** Pseudos qui imiteraient l'équipe ou un compte supprimé (comparés en minuscules). */
    private const RESERVED_NICKNAMES = [
        'admin', 'administrateur', 'moderateur', 'modérateur', 'foodbattle', 'compte désactivé', 'compte desactive',
    ];

    /**
     * Prix d'un plat (voir Controller::postPrice()) : un nombre entre 0 et Dish::MAX_PRICE.
     */
    public static function isPrice(float|false|null $price): bool
    {
        return is_float($price) && $price >= 0 && $price <= Dish::MAX_PRICE;
    }

    /**
     * Nom d'un plat : 120 caractères maximum, comme la colonne dishes.name (VARCHAR(120)).
     */
    public static function isDishName(string $name): bool
    {
        return self::isCleanText($name, 120);
    }

    public static function isNickname(string $nickname): bool
    {
        $length = mb_strlen($nickname);

        return $length >= self::NICKNAME_MIN_LENGTH
            && $length <= self::NICKNAME_MAX_LENGTH
            && preg_match(self::NICKNAME_PATTERN, $nickname) === 1;
    }

    public static function isReservedNickname(string $nickname): bool
    {
        return in_array(mb_strtolower($nickname), self::RESERVED_NICKNAMES, true);
    }

    /**
     * Texte libre (un avis, un nom…) : $maxLength caractères au plus, au moins un caractère visible,
     * et aucun caractère invisible piégeant. Refusés : les caractères de contrôle (\p{Cc}) sauf tabulation et retours à la ligne,
     * et les caractères de format invisibles (\p{Cf}, ex. l'espace sans largeur U+200B), sauf U+200D.
     */
    public static function isCleanText(string $text, int $maxLength): bool
    {
        // U+200D (« liant sans largeur ») est permis : il colle les emojis composés (👨‍👩‍👧, ❤️‍🔥)
        $hasForbiddenChar = preg_match('/(?![\t\n\r])\p{Cc}|(?!\x{200D})\p{Cf}/u', $text) === 1;
        $hasVisibleChar   = preg_match('/[^\s\p{Cf}]/u', $text) === 1;

        return mb_strlen($text) <= $maxLength && !$hasForbiddenChar && $hasVisibleChar;
    }
}
