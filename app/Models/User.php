<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Accès à la table "users" (comptes du site, rôles 'user' et 'admin').
 */
class User
{
    /**
     * Compte correspondant à cet email, ou null s'il n'existe pas.
     * L'email doit déjà être normalisé (trim + minuscules) par l'appelant.
     */
    public static function findByEmail(string $email): ?array
    {
        $statement = Database::get()->prepare(
            'SELECT id, email, nickname, password_hash, role
             FROM users
             WHERE email = ?'
        );
        $statement->execute([$email]);

        return $statement->fetch() ?: null;
    }

    public static function emailExists(string $email): bool
    {
        $statement = Database::get()->prepare('SELECT 1 FROM users WHERE email = ?');
        $statement->execute([$email]);

        return $statement->fetchColumn() !== false;
    }

    /**
     * La comparaison ignore la casse, comme l'index unique users_nickname_unique.
     */
    public static function nicknameExists(string $nickname): bool
    {
        $statement = Database::get()->prepare('SELECT 1 FROM users WHERE lower(nickname) = lower(?)');
        $statement->execute([$nickname]);

        return $statement->fetchColumn() !== false;
    }

    /**
     * Crée un compte 'user' et renvoie son id.
     * Le rôle n'est volontairement pas un paramètre : impossible de créer un admin depuis le site.
     */
    public static function create(string $email, string $nickname, string $passwordHash): int
    {
        $statement = Database::get()->prepare(
            'INSERT INTO users (email, nickname, password_hash)
             VALUES (?, ?, ?)
             RETURNING id'
        );
        $statement->execute([$email, $nickname, $passwordHash]);

        return (int) $statement->fetchColumn();
    }

    /**
     * Met à jour le hash (utilisé quand PHP recommande de le recalculer, voir Auth::attempt).
     */
    public static function updatePasswordHash(int $id, string $passwordHash): void
    {
        $statement = Database::get()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $statement->execute([$passwordHash, $id]);
    }
}
