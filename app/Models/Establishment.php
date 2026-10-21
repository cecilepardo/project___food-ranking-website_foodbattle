<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Accès à la table "establishments" (les restaurants).
 */
class Establishment
{
    public static function all(): array
    {
        return Database::get()
            ->query('SELECT * FROM establishments ORDER BY id')
            ->fetchAll();
    }
}
