<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Auth;
use App\Core\Response;

/**
 * Réservé aux visiteurs non connectés (pages de connexion et d'inscription) :
 * un utilisateur déjà connecté est renvoyé vers son accueil.
 */
class RequireGuest implements Middleware
{
    public function handle(): void
    {
        if (Auth::check()) {
            Response::redirect(Auth::homeUrl());
        }
    }
}
