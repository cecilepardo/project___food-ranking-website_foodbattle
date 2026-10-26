<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Response;
use App\Core\Theme;

/**
 * Bascule entre le thème sombre et le thème clair (bouton de l'en-tête).
 */
class ThemeController extends Controller
{
    /**
     * POST /theme
     * Change de thème, puis renvoie l'utilisateur sur la page où il a cliqué.
     */
    public function toggle(): void
    {
        $this->checkCsrf();

        Theme::toggle();

        // "back" vient d'un champ caché du formulaire, donc modifiable par le navigateur :
        // on ne redirige que vers un chemin du site, sinon vers l'accueil.
        $back = $this->postString('back');
        $this->redirect(Response::isLocalPath($back) ? $back : '/');
    }
}
