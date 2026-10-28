<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

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
     * Avis avec le nom du plat (page de modération), en attente en premier.
     * $status (pending, published, rejected) filtre la liste ; null = tous les avis.
     */
    public static function allWithDish(?string $status = null): array
    {
        $where  = $status === null ? '' : 'WHERE r.status = ?';
        $params = $status === null ? [] : [$status];

        $statement = Database::get()->prepare(
            "SELECT r.*,
                    d.name AS dish,
                    u.nickname
             FROM ratings r
             JOIN dishes d ON d.id = r.dish_id
             LEFT JOIN users u ON u.id = r.user_id
             $where
             ORDER BY r.status = 'pending' DESC, r.id DESC"
        );
        $statement->execute($params);
        return $statement->fetchAll();
    }

    /**
     * Nombre d'avis par statut, ex. ['pending' => 6, 'published' => 30]. Un statut sans avis est absent.
     */
    public static function countByStatus(): array
    {
        return Database::get()
            ->query('SELECT status, COUNT(*) FROM ratings GROUP BY status')
            ->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    /**
     * Publie ou refuse un avis. Renvoie false si l'avis n'existe pas.
     */
    public static function setStatus(int $id, string $status): bool
    {
        $statement = Database::get()->prepare('UPDATE ratings SET status = ? WHERE id = ?');
        $statement->execute([$status, $id]);

        return $statement->rowCount() > 0;
    }

    /**
     * Enregistre l'avis d'un compte. Renvoie false s'il avait déjà donné son avis sur ce plat.
     */
    public static function create(int $dishId, int $userId, int $score, string $comment): bool
    {
        $statement = Database::get()->prepare(
            "INSERT INTO ratings (dish_id, user_id, score, comment, status)
             VALUES (?, ?, ?, ?, 'pending')
             ON CONFLICT (dish_id, user_id) DO NOTHING"
        );
        $statement->execute([$dishId, $userId, $score, $comment]);

        return $statement->rowCount() > 0;
    }
}
