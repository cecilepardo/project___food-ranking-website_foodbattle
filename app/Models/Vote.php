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
        // BUG connu : aucune limite, un même visiteur peut voter plusieurs fois pour le même plat.
        $statement = Database::get()->prepare(
            'INSERT INTO votes (dish_id, visitor_key) VALUES (?, ?)'
        );
        $statement->execute([$dishId, $visitorKey]);
    }
}
