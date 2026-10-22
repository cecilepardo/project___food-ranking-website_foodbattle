<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Accès à la table "votes".
 */
class Vote
{
    public static function create(int $dishId, string $visitorKey): void
    {
        $statement = Database::get()->prepare(
            'INSERT INTO votes (dish_id, visitor_key) VALUES (?, ?) ON CONFLICT DO NOTHING'
        );
        $statement->execute([$dishId, $visitorKey]);
    }
}
