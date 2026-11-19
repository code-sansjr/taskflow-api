<?php

declare(strict_types=1);

namespace TaskFlow\Core;

final class Router
{
    /**
     * @var array<int, array{method: string, pattern: string, handler: callable}>
     */
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

    public function dispatch(Request $request): Response
    {
        $path = $this->normalizePath($request->path);
        $method = strtoupper($request->method);
        $allowedMethods = [];

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                if (preg_match($route['pattern'], $path)) {
                    $allowedMethods[$route['method']] = true;
                }

                continue;
            }

            if (preg_match($route['pattern'], $path, $matches)) {
                $params = [];
                foreach ($matches as $key => $value) {
                    if (is_string($key)) {
                        $params[$key] = $value;
                    }
                }

                return ($route['handler'])($request, $params);
            }
        }

        if ($allowedMethods !== []) {
            throw new HttpException(
                405,
                'Method not allowed.',
                'method_not_allowed',
                ['allowed' => array_keys($allowedMethods)]
            );
        }

        throw new HttpException(404, 'Route not found.', 'route_not_found');
    }

    private function add(string $method, string $path, callable $handler): void
    {
        $normalized = $this->normalizePath($path);
        $pattern = '#^' . preg_replace(
            '#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#',
            '(?P<$1>[^/]+)',
            $normalized
        ) . '$#';

        $this->routes[] = [
            'method' => $method,
            'pattern' => $pattern,
            'handler' => $handler,
        ];
    }

    private function normalizePath(string $path): string
    {
        $normalized = '/' . trim($path, '/');

        return $normalized === '/' ? '/' : rtrim($normalized, '/');
    }
}
