<?php

declare(strict_types=1);

namespace App\Core;

use App\Controller\ErrorController;

final class Router
{
    /** @var list<array{string, string, array{class-string, string}}> */
    private array $routes = [];

    public function get(string $path, array $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, array $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = rawurldecode(parse_url($uri, PHP_URL_PATH) ?: '/');
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        $pathMatched = false;
        foreach ($this->routes as [$routeMethod, $regex, $handler]) {
            if (!preg_match($regex, $path, $matches)) {
                continue;
            }
            $pathMatched = true;
            if ($routeMethod !== $method) {
                continue;
            }
            [$class, $action] = $handler;
            $params = array_map('intval', array_values(array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY)));
            (new $class())->$action(...$params);
            return;
        }

        $error = new ErrorController();
        $pathMatched ? $error->methodNotAllowed() : $error->notFound();
    }

    private function add(string $method, string $path, array $handler): void
    {
        // {id} n'accepte que des entiers positifs.
        $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[1-9]\d{0,9})', $path) . '$#';
        $this->routes[] = [$method, $regex, $handler];
    }
}
