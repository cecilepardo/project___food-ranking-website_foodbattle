<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\RateLimitHit;

/**
 * Limiteur de tentatives générique, sur une fenêtre glissante.
 *
 * Une "clé" désigne ce qu'on compte (une IP, un email…). Utilisation :
 *   if (RateLimiter::tooManyAttempts('login:toto@mail.fr', 5, 900)) { … bloquer … }
 *   RateLimiter::hit('login:toto@mail.fr');
 *
 * Les tentatives sont stockées en base et non en session : un attaquant ne peut pas
 * remettre son compteur à zéro en supprimant son cookie.
 */
class RateLimiter
{
    /** Au-delà de cette durée, une tentative ne compte plus pour aucune limite : on peut la supprimer. */
    private const RETENTION_SECONDS = 86400; // 24 h

    /**
     * Vrai si la clé a déjà atteint $maxAttempts tentatives pendant les $windowSeconds dernières secondes.
     */
    public static function tooManyAttempts(string $key, int $maxAttempts, int $windowSeconds): bool
    {
        return RateLimitHit::countSince($key, $windowSeconds) >= $maxAttempts;
    }

    /**
     * Enregistre une tentative pour cette clé, et supprime au passage les tentatives périmées.
     * Renvoie l'id de la tentative (voir forget()).
     */
    public static function hit(string $key): int
    {
        RateLimitHit::deleteOlderThan(self::RETENTION_SECONDS);

        return RateLimitHit::create($key);
    }

    /**
     * Secondes à attendre avant que la clé puisse retenter (pour le message et l'en-tête Retry-After).
     */
    public static function availableIn(string $key, int $windowSeconds): int
    {
        return RateLimitHit::secondsUntilOldestExpires($key, $windowSeconds);
    }

    /**
     * Efface les tentatives d'une clé (ex. après une connexion réussie).
     */
    public static function clear(string $key): void
    {
        RateLimitHit::deleteForKey($key);
    }

    /**
     * Retire des tentatives précises (par id), ex. celles d'une connexion réussie.
     * Contrairement à clear(), les autres tentatives de la clé restent comptées.
     */
    public static function forget(array $hitIds): void
    {
        RateLimitHit::deleteIds($hitIds);
    }
}
