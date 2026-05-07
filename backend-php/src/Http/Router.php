<?php

declare(strict_types=1);

namespace App\Http;

final class Router
{
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, callable $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    public function put(string $path, callable $handler): void
    {
        $this->add('PUT', $path, $handler);
    }

    public function patch(string $path, callable $handler): void
    {
        $this->add('PATCH', $path, $handler);
    }

    public function delete(string $path, callable $handler): void
    {
        $this->add('DELETE', $path, $handler);
    }

    public function dispatch(Request $request): void
    {
        $methodRoutes = $this->routes[$request->method()] ?? [];

        foreach ($methodRoutes as $route) {
            if (!preg_match($route['pattern'], $request->path(), $matches)) {
                continue;
            }

            $params = [];
            foreach ($route['params'] as $paramName) {
                $params[$paramName] = $matches[$paramName] ?? null;
            }

            ($route['handler'])($request->withRouteParams($params));
            return;
        }

        throw new HttpException('Route not found.', 404);
    }

    private function add(string $method, string $path, callable $handler): void
    {
        preg_match_all('/\{([A-Za-z_][A-Za-z0-9_]*)\}/', $path, $matches);
        $paramNames = $matches[1];
        $pattern = preg_replace('/\{([A-Za-z_][A-Za-z0-9_]*)\}/', '(?P<$1>[^/]+)', $path);

        $this->routes[$method][] = [
            'pattern' => '#^' . $pattern . '$#',
            'params' => $paramNames,
            'handler' => $handler,
        ];
    }
}
