<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Accès à la table "dishes" (les plats).
 */
class Dish
{
    public const CATEGORIES = [
        'burger' => 'Burgers',
        'tacos'  => 'Tacos',
        'pizza'  => 'Pizzas',
    ];

    /**
     * Plats de la carte, avec leur restaurant, leur note moyenne et leur nombre de votes.
     * Filtres optionnels : nom du plat et catégorie.
     */
    public static function search(string $name = '', string $category = ''): array
    {
        $sql = "SELECT d.*,
                       e.name AS restaurant,
                       e.city,
                       (SELECT AVG(score) FROM ratings WHERE dish_id = d.id) AS average,
                       (SELECT COUNT(*)   FROM votes   WHERE dish_id = d.id) AS votes
                FROM dishes d
                JOIN establishments e ON e.id = d.establishment_id
                WHERE 1 = 1";
        $params = [];

        if ($name !== '') {
            $sql .= ' AND d.name ILIKE ?';
            $params[] = '%' . $name . '%';
        }

        if ($category !== '') {
            $sql .= ' AND d.category = ?';
            $params[] = $category;
        }

        $sql .= ' ORDER BY d.id';

        $statement = Database::get()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    /**
     * Un plat avec les informations de son restaurant, ou null s'il n'existe pas.
     */
    public static function findWithEstablishment(int $id): ?array
    {
        $statement = Database::get()->prepare(
            'SELECT d.*,
                    e.name AS restaurant,
                    e.city,
                    e.address
             FROM dishes d
             JOIN establishments e ON e.id = d.establishment_id
             WHERE d.id = ?'
        );
        $statement->execute([$id]);

        return $statement->fetch() ?: null;
    }

    /**
     * Un plat seul (sans jointure), ou null s'il n'existe pas.
     */
    public static function find(int $id): ?array
    {
        $statement = Database::get()->prepare('SELECT * FROM dishes WHERE id = ?');
        $statement->execute([$id]);

        return $statement->fetch() ?: null;
    }

    public static function exists(int $id): bool
    {
        return self::find($id) !== null;
    }

    /**
     * Les 10 plats du classement.
     */
    public static function ranking(int $limit = 10): array
    {
        $statement = Database::get()->prepare(
            'SELECT d.id,
                    d.name,
                    d.category,
                    e.name AS restaurant,
                    (SELECT COUNT(*)   FROM votes   WHERE dish_id = d.id) AS votes,
                    (SELECT AVG(score) FROM ratings WHERE dish_id = d.id) AS average
             FROM dishes d
             JOIN establishments e ON e.id = d.establishment_id
             ORDER BY votes DESC, d.id
             LIMIT ?'
        );
        $statement->execute([$limit]);

        return $statement->fetchAll();
    }

    /**
     * Tous les plats avec le nom de leur restaurant (tableau du dashboard).
     */
    public static function allWithEstablishment(): array
    {
        return Database::get()->query(
            'SELECT d.*,
                    e.name AS restaurant
             FROM dishes d
             JOIN establishments e ON e.id = d.establishment_id
             ORDER BY d.id'
        )->fetchAll();
    }

    public static function create(int $establishmentId, string $name, string $category, string $description, float $price): void
    {
        $statement = Database::get()->prepare(
            'INSERT INTO dishes (establishment_id, name, category, description, price)
             VALUES (?, ?, ?, ?, ?)'
        );
        $statement->execute([$establishmentId, $name, $category, $description, $price]);
    }

    public static function update(int $id, string $name, float $price): void
    {
        $statement = Database::get()->prepare('UPDATE dishes SET name = ?, price = ? WHERE id = ?');
        $statement->execute([$name, $price, $id]);
    }
}
