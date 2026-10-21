<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Auth;
use App\Core\Response;

/**
 * Réservé aux utilisateurs connectés : un visiteur est redirigé vers la page de connexion.
 */
class RequireLogin implements Middleware
{
    public function handle(): void
    {
        if (!Auth::check()) {
            Response::redirect('/connexion');
        }
    }
}
