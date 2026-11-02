<?php

declare(strict_types=1);

namespace Core;

final class Response
{
	public static function json(int $status, array $body): never
	{
		http_response_code($status);
		header('Content-Type: application/json; charset=utf-8');
		header('X-Content-Type-Options: nosniff');
		header('Cache-Control: no-store');
		$json = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
		if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'HEAD') {
			echo $json;
		}
		exit;
	}

	public static function ok(mixed $data = null, int $status = 200, ?string $message = null): never
	{
		$body = ['success' => true];
		if ($message !== null) {
			$body['message'] = $message;
		}
		if ($data !== null) {
			$body['data'] = $data;
		}
		self::json($status, $body);
	}

	public static function fail(int $status, string $message, ?array $errors = null): never
	{
		$body = ['success' => false, 'message' => $message];
		if ($errors !== null) {
			$body['errors'] = $errors;
		}
		self::json($status, $body);
	}
}
