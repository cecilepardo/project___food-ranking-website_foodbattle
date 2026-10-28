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
     * Arrête la requête et affiche la page d'erreur du site, ex. abort(404, 'Plat introuvable.').
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
        if (!Csrf::isValid($this->postString('token'))) {
            $this->abort(403, 'Requête refusée.');
        }
    }

    /**
     * Valeur texte d'un champ de formulaire (POST), ou '' s'il est absent ou n'est pas du texte.
     * Un champ envoyé sous forme de tableau ("name[]=x") ferait planter trim() et les fonctions typées string.
     */
    protected function postString(string $key): string
    {
        return self::stringOrEmpty($_POST[$key] ?? '');
    }

    /**
     * Même chose pour un paramètre d'URL (GET), ex. "?q=burger".
     */
    protected function queryString(string $key): string
    {
        return self::stringOrEmpty($_GET[$key] ?? '');
    }

    private static function stringOrEmpty(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
