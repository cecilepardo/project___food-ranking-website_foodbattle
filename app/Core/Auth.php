<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\User;

/**
 * Authentification : vérifie les identifiants et mémorise l'utilisateur connecté en session.
 */
class Auth
{
    /** Clé de $_SESSION qui contient l'utilisateur connecté. */
    private const SESSION_KEY = 'user';

    /**
     * Coût bcrypt fixé explicitement : PHP 8.3 utilise 10 par défaut,
     * on garde 12 comme les comptes de seed (gen_salt('bf', 12)).
     */
    private const HASH_OPTIONS = ['cost' => 12];

    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT, self::HASH_OPTIONS);
    }

    /**
     * Renvoie le compte si l'email et le mot de passe sont corrects, sinon null.
     * Ne connecte pas l'utilisateur : c'est le rôle de login().
     */
    public static function attempt(string $email, string $password): ?array
    {
        $user = User::findByEmail($email);

        if ($user === null || !password_verify($password, $user['password_hash'])) {
            return null;
        }

        self::rehashIfNeeded($user['id'], $user['password_hash'], $password);

        return $user;
    }

    /**
     * Connecte l'utilisateur : seules les données utiles à l'affichage et aux droits
     * sont gardées en session (jamais le hash).
     */
    public static function login(array $user): void
    {
        // Nouvel identifiant de session : un identifiant connu d'un attaquant avant
        // la connexion devient inutilisable (protection contre la fixation de session).
        session_regenerate_id(true);

        $_SESSION[self::SESSION_KEY] = [
            'id'       => (int) $user['id'],
            'nickname' => $user['nickname'],
            'role'     => $user['role'],
        ];
    }

    public static function logout(): void
    {
        // Vide toute la session (utilisateur, jeton CSRF…) puis change d'identifiant.
        $_SESSION = [];
        session_regenerate_id(true);
    }

    /**
     * Utilisateur connecté (id, nickname, role), ou null pour un visiteur.
     */
    public static function user(): ?array
    {
        return $_SESSION[self::SESSION_KEY] ?? null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function isAdmin(): bool
    {
        return (self::user()['role'] ?? null) === 'admin';
    }

    /**
     * Recalcule le hash si l'algorithme ou le coût recommandés ont changé
     * (ex. hash $2a$ des comptes de seed => $2y$ produit par PHP).
     */
    private static function rehashIfNeeded(int $userId, string $hash, string $password): void
    {
        if (password_needs_rehash($hash, PASSWORD_DEFAULT, self::HASH_OPTIONS)) {
            User::updatePasswordHash($userId, self::hashPassword($password));
        }
    }
}
