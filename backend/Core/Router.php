<?php

declare(strict_types=1);

namespace Core;

use Core\Response;
use Core\Csrf;

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

		$found = $this->find($path);
		if ($found === null) {
			Response::fail(404, 'Endpoint tidak ditemukan.');
		}
		[$methods, $params] = $found;
		$handler = $methods[$method] ?? null;
		if ($handler === null && $method === 'HEAD') {
			// HEAD is answered by the GET handler (Response drops the body).
			$handler = $methods['GET'] ?? null;
		}
		if ($handler === null) {
			$allowed = array_keys($methods);
			sort($allowed); // the same order every time, whatever order the routes were registered in
			header('Allow: ' . implode(', ', $allowed));
			Response::fail(405, 'Metode tidak diizinkan.');
		}
		if (!in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
			Csrf::guard();
		}
		// $params are the {id} values of the path, as ints; handlers without parameters simply get none.
		$handler(...$params);
	}

	/**
	 * The handlers registered for this path. An exact path wins, so /projects/mine is never read as /projects/{id};
	 * after that, a registered path with {name} parts matches digits only (/projects/12 => id 12).
	 * @return array{0:array<string,callable>,1:list<int>}|null the method => handler map and the {name} values
	 */
	private function find(string $path): ?array
	{
		if (isset($this->routes[$path])) {
			return [$this->routes[$path], []];
		}
		foreach ($this->routes as $pattern => $methods) {
			if (!str_contains($pattern, '{')) {
				continue;
			}

			// "/projects/{id}" becomes the regular expression #^/projects/(\d+)$#
			$regex = '#^' . preg_replace('/\{\w+\}/', '(\d+)', $pattern) . '$#';
			if (preg_match($regex, $path, $matches) !== 1) {
				continue;
			}

			array_shift($matches); // the first entry is the whole match, the rest are the {name} values
			$values = [];
			foreach ($matches as $match) {
				$values[] = (int) $match;
			}
			return [$methods, $values];
		}
		return null;
	}
}
