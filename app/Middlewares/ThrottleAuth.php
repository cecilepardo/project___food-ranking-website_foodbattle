<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Core\RateLimiter;
use App\Core\Response;

/**
 * Limite le nombre de formulaires d'authentification envoyés depuis une même adresse IP
 * (vérification d'email, mot de passe, inscription) : freine le brute-force et
 * l'énumération des emails inscrits depuis une même machine.
 */
class ThrottleAuth implements Middleware
{
    private const MAX_ATTEMPTS   = 20;
    private const WINDOW_SECONDS = 900; // 15 minutes

    /** Tentative enregistrée pour la requête en cours (null si la route n'est pas limitée). */
    private static ?int $currentHitId = null;

    public static function currentHitId(): ?int
    {
        return self::$currentHitId;
    }

    public function handle(): void
    {
        // REMOTE_ADDR est l'adresse vue par le serveur. On ne lit jamais X-Forwarded-For :
        // n'importe quel client peut y écrire ce qu'il veut pour contourner la limite.
        $key = 'auth-ip:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS, self::WINDOW_SECONDS)) {
            $seconds = RateLimiter::availableIn($key, self::WINDOW_SECONDS);
            $minutes = (int) ceil($seconds / 60);

            // En-tête standard : indique aux navigateurs et aux scripts quand réessayer.
            header('Retry-After: ' . $seconds);
            Response::abort(429, "Trop de tentatives de connexion. Réessaie dans $minutes minute(s).");
        }

        self::$currentHitId = RateLimiter::hit($key);
    }
}
