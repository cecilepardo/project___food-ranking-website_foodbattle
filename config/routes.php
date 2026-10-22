<?php

/*
 * Liste de toutes les URL du site.
 * Chaque ligne : méthode HTTP, URL, [Controller, méthode].
 * {id} est remplacé par un nombre et transmis à la méthode du controller.
 */

use App\Controllers\Admin\DashboardController;
use App\Controllers\Admin\DishController as AdminDishController;
use App\Controllers\Admin\ModerationController;
use App\Controllers\AuthController;
use App\Controllers\DishController;
use App\Controllers\HomeController;
use App\Controllers\RankingController;
use App\Core\Router;
use App\Middlewares\RequireAdmin;
use App\Middlewares\ThrottleAuth;

return function (Router $router): void {

    // Pages publiques
    $router->get('/',           [HomeController::class, 'index']);       // ancien index.php
    $router->get('/classement', [RankingController::class, 'index']);    // ancien classement.php
    $router->get('/plat/{id}',  [DishController::class, 'show']);        // ancien plat.php

    // Actions des visiteurs (ancien action.php)
    $router->post('/plat/{id}/vote', [DishController::class, 'vote']);
    $router->post('/plat/{id}/avis', [DishController::class, 'rate']);

    // Authentification
    // Une seule page en deux étapes : l'email, puis le mot de passe ou l'inscription.
    $router->get('/connexion', [AuthController::class, 'emailForm']); // étape 1

    // Formulaires envoyés : limités par adresse IP (anti brute-force et anti-énumération des emails).
    $router->group([ThrottleAuth::class], function (Router $router): void {
        $router->post('/connexion',              [AuthController::class, 'checkEmail']);  // choisit l'étape 2
        $router->post('/connexion/mot-de-passe', [AuthController::class, 'login']);       // étape 2a
        $router->post('/connexion/inscription',  [AuthController::class, 'register']);    // étape 2b
    });

    $router->post('/deconnexion', [AuthController::class, 'logout']);

    // Administration : chaque route de ce groupe passe d'abord par RequireAdmin.
    // Toute nouvelle page d'administration doit être déclarée DANS ce groupe.
    $router->group([RequireAdmin::class], function (Router $router): void {
        $router->get('/dashboard',                        [DashboardController::class, 'index']);  // ancien dashboard.php
        $router->post('/dashboard/plats',                 [DashboardController::class, 'store']);
        $router->get('/dashboard/plats/{id}/modifier',    [AdminDishController::class, 'edit']);   // ancien modifier.php
        $router->post('/dashboard/plats/{id}/modifier',   [AdminDishController::class, 'update']);
        $router->get('/moderation',                       [ModerationController::class, 'index']); // ancien moderation.php
    });
};
