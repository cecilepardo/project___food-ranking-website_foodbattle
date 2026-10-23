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
            "SELECT r.id, r.score, r.comment, r.created_at, u.nickname
             FROM ratings r
             LEFT JOIN users u ON u.id = r.user_id
             WHERE r.dish_id = ? AND r.status = 'published'
             ORDER BY r.id DESC"
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
                    d.name AS dish,
                    u.nickname
             FROM ratings r
             JOIN dishes d ON d.id = r.dish_id
             LEFT JOIN users u ON u.id = r.user_id
             ORDER BY r.id DESC'
        )->fetchAll();
    }

    /**
     * Enregistre l'avis d'un compte. Renvoie false s'il avait déjà donné son avis sur ce plat.
     */
    public static function create(int $dishId, int $userId, int $score, string $comment): bool
    {
        // BUG connu : l'avis est publié directement, sans passer par la modération.
        $statement = Database::get()->prepare(
            "INSERT INTO ratings (dish_id, user_id, score, comment, status)
             VALUES (?, ?, ?, ?, 'published')
             ON CONFLICT (dish_id, user_id) DO NOTHING"
        );
        $statement->execute([$dishId, $userId, $score, $comment]);

        return $statement->rowCount() > 0;
    }
}
