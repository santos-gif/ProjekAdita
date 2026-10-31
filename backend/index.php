<?php

declare(strict_types=1);

use Core\Csrf;
use Core\Db;
use Core\Response;
use Core\Router;
use Features\Auth\AuthController;

// Namespace root -> folder under backend/ (Core\Db => Core/Db.php); no Composer.
spl_autoload_register(static function (string $class): void {
	$file = __DIR__ . '/' . str_replace('\\', '/', $class) . '.php';
	if (preg_match('/^(Core|Features)\\\\/', $class) && is_file($file)) {
		require $file;
	}
});

ini_set('display_errors', '0');
ini_set('zend.exception_ignore_args', '1');
error_reporting(E_ALL);
set_error_handler(static function (int $no, string $msg, string $file, int $line): bool {
	if (!(error_reporting() & $no)) {
		return false;
	}
	throw new ErrorException($msg, 0, $no, $file, $line);
});
set_exception_handler(static function (Throwable $e): void {
	error_log(get_class($e) . ': ' . $e->getMessage());
	if (!headers_sent()) {
		Response::fail(500, 'Terjadi kesalahan pada server.');
	}
});

$auth = new AuthController();
$router = new Router();
$router->add('GET', '/csrf', static fn() => Response::ok(['token' => Csrf::token()]));
$router->add('GET', '/health', static function (): void {
	try {
		Db::pdo()->query('SELECT 1');
	} catch (PDOException $e) {
		error_log(get_class($e) . ': ' . $e->getMessage());
		Response::fail(503, 'Database tidak tersedia.');
	}
	Response::ok(['db' => 'ok']);
});
$router->add('POST', '/auth/register', [$auth, 'register']);
$router->add('POST', '/auth/login', [$auth, 'login']);
$router->add('POST', '/auth/logout', [$auth, 'logout']);
$router->add('GET', '/auth/me', [$auth, 'me']);
$router->add('POST', '/auth/reset', [$auth, 'reset']);



$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'] ?? '/');
