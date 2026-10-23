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

    /** Clé de $_SESSION qui contient la page demandée avant la connexion. */
    private const INTENDED_KEY = 'intended_url';

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

    /**
     * Id de l'utilisateur connecté, ou null pour un visiteur.
     */
    public static function id(): ?int
    {
        return self::user()['id'] ?? null;
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
     * Page d'accueil selon le rôle : le dashboard pour un admin, la carte pour les autres.
     */
    public static function homeUrl(): string
    {
        return self::isAdmin() ? '/dashboard' : '/';
    }

    /**
     * Mémorise la page demandée par un visiteur, pour l'y renvoyer après sa connexion.
     * Seul un chemin du site est accepté (voir isLocalPath()).
     */
    public static function rememberIntendedUrl(string $url): void
    {
        if (self::isLocalPath($url)) {
            $_SESSION[self::INTENDED_KEY] = $url;
        }
    }

    /**
     * Page mémorisée, ou null s'il n'y en a pas. Elle est oubliée aussitôt : elle ne sert qu'une fois.
     */
    public static function pullIntendedUrl(): ?string
    {
        $url = $_SESSION[self::INTENDED_KEY] ?? null;
        unset($_SESSION[self::INTENDED_KEY]);

        return $url;
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

    /**
     * Vrai pour un chemin du site ("/moderation?page=2"), faux pour tout ce qui mènerait ailleurs :
     * les navigateurs lisent "//pirate.com" et "/\pirate.com" comme l'adresse d'un autre site.
     */
    private static function isLocalPath(string $url): bool
    {
        return str_starts_with($url, '/')
            && !str_starts_with($url, '//')
            && !str_starts_with($url, '/\\');
    }
}
