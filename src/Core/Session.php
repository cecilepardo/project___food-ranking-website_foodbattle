<?php

declare(strict_types=1);

namespace App\Core;

final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        session_name('foodbattle');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        ]);
        ini_set('session.use_strict_mode', '1');
        session_start();
    }

    /** Stocke une valeur lue une seule fois (anciennes saisies, erreurs de formulaire). */
    public static function flashValue(string $key, mixed $value): void
    {
        $_SESSION['_once'][$key] = $value;
    }

    public static function pullValue(string $key, mixed $default = null): mixed
    {
        $value = $_SESSION['_once'][$key] ?? $default;
        unset($_SESSION['_once'][$key]);
        return $value;
    }
}
