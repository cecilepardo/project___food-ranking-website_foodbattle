<?php

use App\Controller\Admin\DashboardController;
use App\Controller\Admin\DishController as AdminDishController;
use App\Controller\Admin\ModerationController;
use App\Controller\AuthController;
use App\Controller\DishController;
use App\Controller\HomeController;
use App\Controller\RankingController;
use App\Core\Router;

return static function (Router $router): void {
    // Public
    $router->get('/', [HomeController::class, 'index']);
    $router->get('/classement', [RankingController::class, 'index']);
    $router->get('/plat/{id}', [DishController::class, 'show']);
    $router->post('/plat/{id}/vote', [DishController::class, 'vote']);
    $router->post('/plat/{id}/avis', [DishController::class, 'rate']);

    // Authentification
    $router->get('/admin/login', [AuthController::class, 'loginForm']);
    $router->post('/admin/login', [AuthController::class, 'login']);
    $router->post('/admin/logout', [AuthController::class, 'logout']);

    // Administration (protégée dans chaque contrôleur)
    $router->get('/admin', [DashboardController::class, 'index']);
    $router->get('/admin/plats/nouveau', [AdminDishController::class, 'create']);
    $router->post('/admin/plats', [AdminDishController::class, 'store']);
    $router->get('/admin/plats/{id}/modifier', [AdminDishController::class, 'edit']);
    $router->post('/admin/plats/{id}', [AdminDishController::class, 'update']);
    $router->post('/admin/plats/{id}/supprimer', [AdminDishController::class, 'delete']);
    $router->get('/admin/moderation', [ModerationController::class, 'index']);
    $router->post('/admin/avis/{id}/publier', [ModerationController::class, 'publish']);
    $router->post('/admin/avis/{id}/refuser', [ModerationController::class, 'reject']);
};
