<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Auth;
use App\Core\Response;

/**
 * Réservé aux utilisateurs connectés : un visiteur est redirigé vers la page de connexion,
 * qui le renverra ensuite vers la page qu'il demandait.
 */
class RequireLogin implements Middleware
{
    public function handle(): void
    {
        if (Auth::check()) {
            return;
        }

        // Seule une page affichée (GET) peut être rouverte après la connexion :
        // un formulaire envoyé (POST) ne se rejoue pas.
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            Auth::rememberIntendedUrl($_SERVER['REQUEST_URI']);
        }

        Response::redirect('/connexion');
    }
}
