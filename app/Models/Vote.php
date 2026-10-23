<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Accès à la table "votes".
 */
class Vote
{
    /**
     * Enregistre le vote d'un compte pour un plat. Renvoie false s'il avait déjà voté :
     * la contrainte UNIQUE (dish_id, user_id) fait ignorer le doublon par la base.
     */
    public static function create(int $dishId, int $userId): bool
    {
        $statement = Database::get()->prepare(
            'INSERT INTO votes (dish_id, user_id) VALUES (?, ?) ON CONFLICT (dish_id, user_id) DO NOTHING'
        );
        $statement->execute([$dishId, $userId]);

        return $statement->rowCount() > 0;
    }
}
