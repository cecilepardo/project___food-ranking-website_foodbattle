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
    /** Plus grand id possible : les colonnes id sont des INTEGER PostgreSQL (2^31 - 1). */
    private const MAX_ID = 2147483647;

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

        // HEAD = GET sans le corps de la page (Apache le retire) : il utilise les routes GET
        $routeMethod = $method === 'HEAD' ? 'GET' : $method;

        // Méthodes acceptées par cette adresse, si elle existe avec une autre méthode
        $allowedMethods = [];

        foreach ($this->routes as $route) {
            if (!preg_match($route['pattern'], $path, $matches)) {
                continue;
            }
            if ($route['method'] !== $routeMethod) {
                $allowedMethods[] = $route['method'];
                continue;
            }

            [$controllerClass, $methodName] = $route['action'];
            $params = $this->extractParams($matches);
            if ($params === null) {
                $this->pageNotFound(); // id trop grand : aucun plat ni avis ne peut l'avoir
                return;
            }

            // Les middlewares passent avant la création du controller : s'ils bloquent, aucun code du controller ne s'exécute.
            $this->runMiddlewares($route['middlewares']);

            $controller = new $controllerClass();
            $controller->$methodName(...$params);
            return;
        }

        if ($allowedMethods !== []) {
            $this->methodNotAllowed($allowedMethods);
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
     * Renvoie null si un id dépasse MAX_ID : PostgreSQL planterait sur la requête.
     */
    private function extractParams(array $matches): ?array
    {
        $params = [];
        foreach ($matches as $key => $value) {
            if (!is_string($key)) {
                continue;
            }
            // (int) sature à PHP_INT_MAX pour les nombres géants, donc la comparaison reste juste.
            $id = (int) $value;
            if ($id > self::MAX_ID) {
                return null;
            }
            $params[] = $id;
        }
        return $params;
    }

    private function pageNotFound(): never
    {
        Response::abort(404, 'Cette adresse ne correspond à aucune page du site.');
    }

    private function methodNotAllowed(array $allowedMethods): never
    {
        // Obligatoire avec un 405 : les méthodes que cette adresse accepte
        header('Allow: ' . implode(', ', array_unique($allowedMethods)));
        Response::abort(405, "Cette adresse s'utilise uniquement depuis un formulaire du site.");
    }
}
