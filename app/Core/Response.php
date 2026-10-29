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
     * Les caractères de contrôle sont refusés aussi : les navigateurs effacent tabulations et retours
     * à la ligne d'une adresse, donc "/<tabulation>/pirate.com" deviendrait "//pirate.com".
     * À vérifier avant de rediriger vers une adresse fournie par le navigateur (protection contre la redirection ouverte).
     */
    public static function isLocalPath(string $url): bool
    {
        return str_starts_with($url, '/')
            && !str_starts_with($url, '//')
            && !str_starts_with($url, '/\\')
            && preg_match('/[\x00-\x1F\x7F]/', $url) !== 1;
    }

    /** Titre de la page d'erreur selon le code HTTP. */
    private const ERROR_TITLES = [
        400 => 'Requête invalide',
        403 => 'Accès refusé',
        404 => 'Page introuvable',
        405 => 'Action impossible',
        422 => 'Saisie invalide',
        429 => 'Trop de tentatives',
        500 => 'Erreur du serveur',
    ];

    /**
     * Arrête la requête et affiche la page d'erreur du site (avec en-tête et pied de page).
     */
    public static function abort(int $statusCode, string $message): never
    {
        http_response_code($statusCode);
        echo self::errorPage($statusCode, $message);
        exit;
    }

    /**
     * HTML de la page d'erreur. $details (trace technique) n'est passé qu'en dev.
     */
    public static function errorPage(int $statusCode, string $message, ?string $details = null): string
    {
        return View::render('error', [
            'title'   => self::ERROR_TITLES[$statusCode] ?? 'Erreur',
            'code'    => $statusCode,
            'message' => $message,
            'details' => $details,
        ]);
    }
}
