<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Associe une URL à une méthode de controller.
 */
class Router
{
    /** @var array<int, array{method: string, pattern: string, action: array}> */
    private array $routes = [];

    public function get(string $path, array $action): void
    {
        $this->addRoute('GET', $path, $action);
    }

    public function post(string $path, array $action): void
    {
        $this->addRoute('POST', $path, $action);
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
            'method'  => $method,
            'pattern' => $pattern,
            'action'  => $action,
        ];
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
        echo View::render('errors/404', ['title' => 'Page introuvable']);
    }
}
