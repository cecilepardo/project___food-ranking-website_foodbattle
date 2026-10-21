<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Classe de base de tous les controllers.
 */
abstract class Controller
{
    /**
     * Affiche une vue dans le layout.
     */
    protected function render(string $view, array $data = [], int $statusCode = 200): void
    {
        http_response_code($statusCode);
        echo View::render($view, $data);
    }

    protected function redirect(string $url): never
    {
        Response::redirect($url);
    }

    /**
     * Arrête la requête avec un simple message texte (comportement de l'ancien code).
     */
    protected function abort(int $statusCode, string $message): never
    {
        Response::abort($statusCode, $message);
    }

    /**
     * Vérifie qu'un formulaire POST contient le bon jeton CSRF.
     */
    protected function checkCsrf(): void
    {
        if (!Csrf::isValid($_POST['token'] ?? '')) {
            $this->abort(403, 'Requête refusée.');
        }
    }
}
