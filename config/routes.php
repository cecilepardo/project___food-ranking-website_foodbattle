<?php

/*
 * Liste de toutes les URL du site.
 * Chaque ligne : méthode HTTP, URL, [Controller, méthode].
 * {id} est remplacé par un nombre et transmis à la méthode du controller.
 */

use App\Controllers\Admin\DashboardController;
use App\Controllers\Admin\DishController as AdminDishController;
use App\Controllers\Admin\EstablishmentController;
use App\Controllers\Admin\ModerationController;
use App\Controllers\AuthController;
use App\Controllers\DishController;
use App\Controllers\HomeController;
use App\Controllers\RankingController;
use App\Controllers\ThemeController;
use App\Core\Router;
use App\Middlewares\RequireAdmin;
use App\Middlewares\RequireGuest;
use App\Middlewares\RequireLogin;
use App\Middlewares\ThrottleAuth;

return function (Router $router): void {

    // Pages publiques
    $router->get('/',           [HomeController::class, 'index']);       // ancien index.php
    $router->get('/classement', [RankingController::class, 'index']);    // ancien classement.php
    $router->get('/plat/{id}',  [DishController::class, 'show']);        // ancien plat.php

    // Préférence d'affichage : ouverte à tous, connecté ou non (cookie, pas de compte nécessaire).
    $router->post('/theme', [ThemeController::class, 'toggle']);

    // Participer (voter, donner son avis) : réservé aux utilisateurs connectés.
    // /participer est un lien GET : RequireLogin le mémorise, on revient donc sur le plat après la connexion.
    $router->group([RequireLogin::class], function (Router $router): void {
        $router->get('/plat/{id}/participer', [DishController::class, 'participate']);
        $router->post('/plat/{id}/vote',      [DishController::class, 'vote']);
        $router->post('/plat/{id}/avis',      [DishController::class, 'rate']);
    });

    // Authentification
    // Une seule page en deux étapes : l'email, puis le mot de passe ou l'inscription.
    // Réservée aux visiteurs non connectés : un utilisateur connecté est renvoyé vers son accueil.
    $router->group([RequireGuest::class], function (Router $router): void {
        $router->get('/connexion', [AuthController::class, 'emailForm']); // étape 1

        // Formulaires envoyés : limités par adresse IP (anti brute-force et anti-énumération des emails).
        $router->group([ThrottleAuth::class], function (Router $router): void {
            $router->post('/connexion',              [AuthController::class, 'checkEmail']);  // choisit l'étape 2
            $router->post('/connexion/mot-de-passe', [AuthController::class, 'login']);       // étape 2a
            $router->post('/connexion/inscription',  [AuthController::class, 'register']);    // étape 2b
        });
    });

    $router->post('/deconnexion', [AuthController::class, 'logout']);

    // Administration : chaque route de ce groupe passe d'abord par RequireAdmin.
    // Toute nouvelle page d'administration doit être déclarée DANS ce groupe.
    $router->group([RequireAdmin::class], function (Router $router): void {
        $router->get('/dashboard',                        [DashboardController::class, 'index']);  // ancien dashboard.php
        $router->get('/dashboard/etablissements',         [EstablishmentController::class, 'index']);
        $router->post('/dashboard/etablissements',        [EstablishmentController::class, 'store']);
        $router->post('/dashboard/plats',                 [DashboardController::class, 'store']);
        $router->get('/dashboard/plats/{id}/modifier',    [AdminDishController::class, 'edit']);   // ancien modifier.php
        $router->post('/dashboard/plats/{id}/modifier',   [AdminDishController::class, 'update']);
        $router->post('/dashboard/plats/{id}/supprimer',  [AdminDishController::class, 'delete']);
        $router->get('/moderation',                       [ModerationController::class, 'index']); // ancien moderation.php
        $router->post('/moderation/{id}/publier',         [ModerationController::class, 'publish']);
        $router->post('/moderation/{id}/refuser',         [ModerationController::class, 'reject']);
    });
};
