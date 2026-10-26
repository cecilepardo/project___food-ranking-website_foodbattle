<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Thème d'affichage (sombre ou clair), mémorisé dans un cookie.
 * Le sombre est le thème par défaut : il s'applique tant que l'utilisateur n'a pas choisi le clair.
 * Le layout s'en sert pour écrire data-theme sur <html> et choisir le logo.
 */
class Theme
{
    public const DARK  = 'dark';
    public const LIGHT = 'light';

    private const COOKIE_NAME     = 'theme';
    private const COOKIE_LIFETIME = 60 * 60 * 24 * 365; // 1 an, en secondes

    /**
     * Thème en cours : 'light' si le cookie le demande, sinon 'dark'.
     * Le cookie vient du navigateur et peut contenir n'importe quoi :
     * on ne reconnaît que la valeur exacte 'light'.
     */
    public static function current(): string
    {
        return ($_COOKIE[self::COOKIE_NAME] ?? null) === self::LIGHT ? self::LIGHT : self::DARK;
    }

    public static function isLight(): bool
    {
        return self::current() === self::LIGHT;
    }

    /**
     * Passe à l'autre thème et mémorise ce choix pour un an.
     */
    public static function toggle(): void
    {
        $next = self::isLight() ? self::DARK : self::LIGHT;

        // Mêmes protections que le cookie de session (voir public/index.php).
        // httponly : aucun JavaScript n'a besoin de lire ce cookie.
        setcookie(self::COOKIE_NAME, $next, [
            'expires'  => time() + self::COOKIE_LIFETIME,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        ]);
    }
}
