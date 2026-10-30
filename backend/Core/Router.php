<?php

declare(strict_types=1);

namespace Core;

final class Router
{
    private const PREFIX = '/api/v1';

    /** @var array<string,array<string,callable>> path => method => handler */
    private array $routes = [];

    public function add(string $method, string $path, callable $handler): void
    {
        $this->routes[$path][$method] = $handler;
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = rawurldecode((string) parse_url($uri, PHP_URL_PATH));
        $path = rtrim($path, '/') ?: '/';
        if (!str_starts_with($path, self::PREFIX)) {
            Response::fail(404, 'Endpoint tidak ditemukan.');
        }
        $path = substr($path, strlen(self::PREFIX)) ?: '/';

        $methods = $this->routes[$path] ?? null;
        if ($methods === null) {
            Response::fail(404, 'Endpoint tidak ditemukan.');
        }
        $handler = $methods[$method] ?? ($method === 'HEAD' ? $methods['GET'] ?? null : null);
        if ($handler === null) {
            header('Allow: ' . implode(', ', array_keys($methods)));
            Response::fail(405, 'Metode tidak diizinkan.');
        }
        if (!in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            Csrf::guard();
        }
        $handler();
    }
}
