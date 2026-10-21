<?php

/*
 * Liste de toutes les URL du site.
 * Chaque ligne : méthode HTTP, URL, [Controller, méthode].
 * {id} est remplacé par un nombre et transmis à la méthode du controller.
 */

use App\Controllers\Admin\DashboardController;
use App\Controllers\Admin\DishController as AdminDishController;
use App\Controllers\Admin\ModerationController;
use App\Controllers\DishController;
use App\Controllers\HomeController;
use App\Controllers\RankingController;
use App\Core\Router;

return function (Router $router): void {

    // Pages publiques
    $router->get('/',           [HomeController::class, 'index']);       // ancien index.php
    $router->get('/classement', [RankingController::class, 'index']);    // ancien classement.php
    $router->get('/plat/{id}',  [DishController::class, 'show']);        // ancien plat.php

    // Actions des visiteurs (ancien action.php)
    $router->post('/plat/{id}/vote', [DishController::class, 'vote']);
    $router->post('/plat/{id}/avis', [DishController::class, 'rate']);

    // Administration
    $router->get('/dashboard',                        [DashboardController::class, 'index']);  // ancien dashboard.php
    $router->post('/dashboard/plats',                 [DashboardController::class, 'store']);
    $router->get('/dashboard/plats/{id}/modifier',    [AdminDishController::class, 'edit']);   // ancien modifier.php
    $router->post('/dashboard/plats/{id}/modifier',   [AdminDishController::class, 'update']);
    $router->get('/moderation',                       [ModerationController::class, 'index']); // ancien moderation.php
};
