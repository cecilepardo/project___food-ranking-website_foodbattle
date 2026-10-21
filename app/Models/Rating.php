<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Accès à la table "ratings" (les avis et notes sur 5).
 */
class Rating
{
    /**
     * Avis publiés d'un plat, du plus récent au plus ancien.
     */
    public static function publishedForDish(int $dishId): array
    {
        $statement = Database::get()->prepare(
            "SELECT *
             FROM ratings
             WHERE dish_id = ? AND status = 'published'
             ORDER BY id DESC"
        );
        $statement->execute([$dishId]);

        return $statement->fetchAll();
    }

    /**
     * Tous les avis avec le nom du plat (page de modération).
     */
    public static function allWithDish(): array
    {
        return Database::get()->query(
            'SELECT r.*,
                    d.name AS dish
             FROM ratings r
             JOIN dishes d ON d.id = r.dish_id
             ORDER BY r.id DESC'
        )->fetchAll();
    }

    public static function create(int $dishId, string $nickname, int $score, string $comment): void
    {
        // BUG connu : l'avis est publié directement, sans passer par la modération.
        $statement = Database::get()->prepare(
            "INSERT INTO ratings (dish_id, nickname, score, comment, status)
             VALUES (?, ?, ?, ?, 'published')"
        );
        $statement->execute([$dishId, $nickname, $score, $comment]);
    }
}
