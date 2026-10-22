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
     * Arrête la requête avec un simple message texte (comportement de l'ancien code).
     */
    public static function abort(int $statusCode, string $message): never
    {
        http_response_code($statusCode);
        exit($message);
    }
}
