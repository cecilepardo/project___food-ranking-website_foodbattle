<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Réponses qui terminent la requête : redirection ou erreur.
 * Utilisable partout (controllers, middlewares), contrairement aux méthodes de Controller.
 */
class Response
{
    public static function redirect(string $url): never
    {
        header('Location: ' . $url);
        exit;
    }

    /**
     * Vrai pour un chemin du site ("/moderation?page=2"), faux pour tout ce qui mènerait ailleurs :
     * les navigateurs lisent "//pirate.com" et "/\pirate.com" comme l'adresse d'un autre site.
     * À vérifier avant de rediriger vers une adresse fournie par le navigateur (protection contre la redirection ouverte).
     */
    public static function isLocalPath(string $url): bool
    {
        return str_starts_with($url, '/')
            && !str_starts_with($url, '//')
            && !str_starts_with($url, '/\\');
    }

    /**
     * Arrête la requête avec un simple message texte (comportement de l'ancien code).
     */
    public static function abort(int $statusCode, string $message): never
    {
        http_response_code($statusCode);
        exit($message);
    }
}
