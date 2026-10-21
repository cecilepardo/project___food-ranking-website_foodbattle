<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Auth;
use App\Core\Response;

/**
 * Réservé aux admins : un visiteur est envoyé vers la connexion,
 * un utilisateur connecté sans le rôle admin reçoit une erreur 403.
 */
class RequireAdmin implements Middleware
{
    public function handle(): void
    {
        // D'abord être connecté : sinon la réponse serait une 403 au lieu d'une invitation à se connecter.
        (new RequireLogin())->handle();

        if (!Auth::isAdmin()) {
            Response::abort(403, 'Accès réservé aux administrateurs.');
        }
    }
}
