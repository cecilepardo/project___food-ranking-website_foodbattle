<?php

declare(strict_types=1);

namespace App\Core;

use App\Middlewares\Middleware;
use LogicException;

/**
 * Associe une URL à une méthode de controller.
 */
class Router
{
    /** @var array<int, array{method: string, pattern: string, action: array, middlewares: array<string>}> */
    private array $routes = [];

    /** Middlewares du groupe en cours de déclaration (voir group()). */
    private array $groupMiddlewares = [];

    public function get(string $path, array $action): void
    {
        $this->addRoute('GET', $path, $action);
    }

    public function post(string $path, array $action): void
    {
        $this->addRoute('POST', $path, $action);
    }

    /**
     * Déclare des routes qui passent toutes par les mêmes middlewares.
     *
     * Exemple : $router->group([RequireAdmin::class], function (Router $router) {
     *     $router->get('/dashboard', [DashboardController::class, 'index']);
     * });
     */
    public function group(array $middlewares, callable $defineRoutes): void
    {
        $previousMiddlewares = $this->groupMiddlewares;
        $this->groupMiddlewares = [...$previousMiddlewares, ...$middlewares];

        $defineRoutes($this);

        // Les routes déclarées après le groupe ne doivent plus hériter de ses middlewares.
        $this->groupMiddlewares = $previousMiddlewares;
    }

    /**
     * Cherche la route qui correspond à la requête et appelle son controller.
     */
    public function dispatch(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            if (!preg_match($route['pattern'], $path, $matches)) {
                continue;
            }

            [$controllerClass, $methodName] = $route['action'];
            $params = $this->extractParams($matches);

            // Les middlewares passent avant la création du controller : s'ils bloquent, aucun code du controller ne s'exécute.
            $this->runMiddlewares($route['middlewares']);

            $controller = new $controllerClass();
            $controller->$methodName(...$params);
            return;
        }

        $this->pageNotFound();
    }

    private function addRoute(string $method, string $path, array $action): void
    {
        // "/plat/{id}" devient l'expression régulière "#^/plat/(?P<id>\d+)$#"
        $pattern = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>\d+)', $path) . '$#';

        $this->routes[] = [
            'method'      => $method,
            'pattern'     => $pattern,
            'action'      => $action,
            'middlewares' => $this->groupMiddlewares,
        ];
    }

    /**
     * Exécute les middlewares dans l'ordre de déclaration.
     * Chacun peut interrompre la requête (redirection, 403) ; sinon on passe au suivant.
     */
    private function runMiddlewares(array $middlewareClasses): void
    {
        foreach ($middlewareClasses as $middlewareClass) {
            $middleware = new $middlewareClass();

            if (!$middleware instanceof Middleware) {
                throw new LogicException($middlewareClass . ' doit implémenter ' . Middleware::class);
            }

            $middleware->handle();
        }
    }

    /**
     * Garde uniquement les paramètres nommés ({id}) et les convertit en entiers.
     */
    private function extractParams(array $matches): array
    {
        $params = [];
        foreach ($matches as $key => $value) {
            if (is_string($key)) {
                $params[] = (int) $value;
            }
        }
        return $params;
    }

    private function pageNotFound(): void
    {
        http_response_code(404);
        echo View::render('error-404', ['title' => 'Page introuvable']);
    }
}
