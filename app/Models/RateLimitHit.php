<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Accès à la table "rate_limit_hits" (tentatives horodatées, voir App\Core\RateLimiter).
 *
 * Toutes les dates sont calculées par PostgreSQL (NOW()) : une seule horloge,
 * pas de décalage possible entre PHP et la base.
 */
class RateLimitHit
{
    public static function create(string $key): void
    {
        $statement = Database::get()->prepare('INSERT INTO rate_limit_hits (key) VALUES (?)');
        $statement->execute([$key]);
    }

    /**
     * Nombre de tentatives enregistrées pour cette clé pendant les $seconds dernières secondes.
     */
    public static function countSince(string $key, int $seconds): int
    {
        $statement = Database::get()->prepare(
            "SELECT COUNT(*)
             FROM rate_limit_hits
             WHERE key = ? AND hit_at > NOW() - (? * INTERVAL '1 second')"
        );
        $statement->execute([$key, $seconds]);

        return (int) $statement->fetchColumn();
    }

    /**
     * Secondes avant que la plus ancienne tentative de la période n'en sorte (0 s'il n'y en a aucune).
     */
    public static function secondsUntilOldestExpires(string $key, int $seconds): int
    {
        $statement = Database::get()->prepare(
            "SELECT CEIL(EXTRACT(EPOCH FROM MIN(hit_at) + (? * INTERVAL '1 second') - NOW()))
             FROM rate_limit_hits
             WHERE key = ? AND hit_at > NOW() - (? * INTERVAL '1 second')"
        );
        $statement->execute([$seconds, $key, $seconds]);

        return max(0, (int) $statement->fetchColumn());
    }

    public static function deleteForKey(string $key): void
    {
        $statement = Database::get()->prepare('DELETE FROM rate_limit_hits WHERE key = ?');
        $statement->execute([$key]);
    }

    /**
     * Nettoyage : supprime les tentatives trop anciennes pour encore compter.
     */
    public static function deleteOlderThan(int $seconds): void
    {
        $statement = Database::get()->prepare(
            "DELETE FROM rate_limit_hits WHERE hit_at < NOW() - (? * INTERVAL '1 second')"
        );
        $statement->execute([$seconds]);
    }
}
