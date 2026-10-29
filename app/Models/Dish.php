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

    /** Prix maximum accepté par les formulaires (la colonne NUMERIC(8,2) planterait au-delà de 999 999,99). */
    public const MAX_PRICE = 999.99;

    /** Tris possibles du tableau du dashboard (valeur de ?tri=…). */
    public const SORTS = ['lieu', 'prix', 'categorie'];

    /** Colonnes calculées d'un plat « d » : note moyenne (avis publiés seulement) et nombre de votes. */
    private const STATS_COLUMNS = "(SELECT AVG(score) FROM ratings WHERE dish_id = d.id AND status = 'published') AS average,
                                   (SELECT COUNT(*)   FROM votes   WHERE dish_id = d.id) AS votes";

    /**
     * Plats de la carte, avec leur restaurant, leur note moyenne et leur nombre de votes.
     * Filtres optionnels : texte recherché (nom du plat ou de l'établissement) et catégorie.
     */
    public static function search(string $search = '', string $category = ''): array
    {
        $sql = 'SELECT d.*,
                       e.name AS restaurant,
                       e.city,
                       ' . self::STATS_COLUMNS . '
                FROM dishes d
                JOIN establishments e ON e.id = d.establishment_id
                WHERE 1 = 1';
        $params = [];

        if ($search !== '') {
            // Le même motif sert pour les deux colonnes : on le passe deux fois (un « ? » par valeur).
            $sql .= ' AND (d.name ILIKE ? OR e.name ILIKE ?)';
            $pattern  = '%' . self::escapeLike($search) . '%';
            $params[] = $pattern;
            $params[] = $pattern;
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
     * Un plat avec les informations de son restaurant, sa note moyenne et son nombre de votes,
     * ou null s'il n'existe pas.
     */
    public static function findWithEstablishment(int $id): ?array
    {
        $statement = Database::get()->prepare(
            'SELECT d.*,
                    e.name AS restaurant,
                    e.city,
                    e.address,
                    ' . self::STATS_COLUMNS . '
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
     * Les 10 plats du classement : meilleure note moyenne d'abord, puis le plus voté en cas d'égalité.
     * Les plats sans avis publié (moyenne NULL) passent en dernier.
     */
    public static function ranking(int $limit = 10): array
    {
        $statement = Database::get()->prepare(
            'SELECT d.id,
                    d.name,
                    d.category,
                    e.name AS restaurant,
                    ' . self::STATS_COLUMNS . '
             FROM dishes d
             JOIN establishments e ON e.id = d.establishment_id
             ORDER BY average DESC NULLS LAST, votes DESC, d.id
             LIMIT ?'
        );
        $statement->execute([$limit]);

        return $statement->fetchAll();
    }

    /**
     * Tous les plats avec leur restaurant et sa ville (tableau du dashboard).
     * Tri optionnel : voir SORTS ('' = ordre d'ajout).
     */
    public static function allWithEstablishment(string $sort = '', bool $descending = false): array
    {
        $direction = $descending ? 'DESC' : 'ASC';

        // Liste blanche : un nom de colonne ne peut pas passer par un « ? » de requête préparée,
        // on n'insère donc jamais la valeur reçue dans le SQL, seulement une de ces chaînes fixes.
        // d.id en dernier départage les égalités (même prix…) : l'ordre reste stable d'un affichage à l'autre.
        $orderBy = match ($sort) {
            'lieu'      => "e.city $direction, e.name $direction, d.id",
            'prix'      => "d.price $direction, d.id",
            'categorie' => "d.category $direction, d.id",
            default     => 'd.id',
        };

        return Database::get()->query(
            "SELECT d.*,
                    e.name AS restaurant,
                    e.city
             FROM dishes d
             JOIN establishments e ON e.id = d.establishment_id
             ORDER BY $orderBy"
        )->fetchAll();
    }

    /**
     * Crée un plat et renvoie son id (sert à nommer sa photo).
     */
    public static function create(int $establishmentId, string $name, string $category, string $description, float $price): int
    {
        $statement = Database::get()->prepare(
            'INSERT INTO dishes (establishment_id, name, category, description, price)
             VALUES (?, ?, ?, ?, ?)
             RETURNING id'
        );
        $statement->execute([$establishmentId, $name, $category, $description, $price]);

        return (int) $statement->fetchColumn();
    }

    public static function update(int $id, string $name, float $price): void
    {
        $statement = Database::get()->prepare('UPDATE dishes SET name = ?, price = ? WHERE id = ?');
        $statement->execute([$name, $price, $id]);
    }

    /**
     * Supprime un plat. Ses avis et ses votes partent avec lui (ON DELETE CASCADE).
     */
    public static function delete(int $id): void
    {
        $statement = Database::get()->prepare('DELETE FROM dishes WHERE id = ?');
        $statement->execute([$id]);
    }

    /**
     * Un plat tiré au sort (filtré par catégorie si $category n'est pas null), ou null s'il n'y en a aucun.
     */
    public static function random(?string $category = null): ?array
    {
        return self::randomMany(1, $category)[0] ?? null;
    }

    /**
     * $limit plats différents, dans un ordre tiré au sort par PostgreSQL (moins s'il n'y en a pas assez).
     * Juste ce qu'il faut pour les afficher sur la roue : nom, catégorie (photo par défaut) et restaurant.
     */
    public static function randomMany(int $limit, ?string $category = null): array
    {
        $where  = $category === null ? '' : 'WHERE d.category = ?';
        $params = $category === null ? [$limit] : [$category, $limit];

        $statement = Database::get()->prepare(
            "SELECT d.id, d.name, d.category, e.name AS restaurant
             FROM dishes d
             JOIN establishments e ON e.id = d.establishment_id
             $where
             ORDER BY random()
             LIMIT ?"
        );
        $statement->execute($params);

        return $statement->fetchAll();
    }

    /**
     * Dans un LIKE, « % » et « _ » sont des jokers : on les échappe pour qu'une recherche
     * de « 100% » cherche vraiment le texte « 100% ». PostgreSQL utilise « \ » comme caractère d'échappement.
     */
    private static function escapeLike(string $text): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $text);
    }
}
