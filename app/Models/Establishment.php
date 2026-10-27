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

    public static function create(string $name, string $city, string $address): void
    {
        $statement = Database::get()->prepare('INSERT INTO establishments (name, city, address) VALUES (?, ?, ?)');
        $statement->execute([$name, $city, $address]);
    }
}
