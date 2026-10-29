<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Jeton anti-CSRF : chaque formulaire POST doit renvoyer le jeton stocké en session.
 */
class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['token'])) {
            $_SESSION['token'] = bin2hex(random_bytes(24));
        }
        return $_SESSION['token'];
    }

    public static function isValid(string $token): bool
    {
        return hash_equals(self::token(), $token);
    }

    /**
     * Oublie le jeton actuel : le prochain formulaire en recevra un nouveau.
     * Appelé à la connexion, pour qu'un jeton obtenu avant ne serve plus ensuite.
     */
    public static function regenerate(): void
    {
        unset($_SESSION['token']);
    }
}
