<?php

declare(strict_types=1);

namespace Core;

use Core\Response;

final class Request
{
	/** Parsed JSON object body; exits 413/400 on oversize or malformed input. */
	public static function body(int $limit = 65536): array
	{
		$contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
		// stripos returns 0 (falsy) when the type starts with application/json, so compare with === false.
		if (stripos($contentType, 'application/json') === false) {
			Response::fail(415, 'Tipe media tidak didukung.');
		}
		$raw = (string) stream_get_contents(fopen('php://input', 'rb'), $limit + 1);
		if (strlen($raw) > $limit) {
			Response::fail(413, 'Ukuran permintaan terlalu besar.');
		}
		if (trim($raw) === '') {
			return [];
		}
		try {
			$data = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
		} catch (\JsonException) {
			Response::fail(400, 'Format JSON tidak valid.');
		}
		if (is_array($data) && ($data === [] || !array_is_list($data))) {
			return $data;
		}
		Response::fail(400, 'Format JSON tidak valid.');
	}

	/**
	 * Address of the client. The frontend's Apache drops any X-Forwarded-For the visitor sent and adds the real peer,
	 * so it names the visitor (REMOTE_ADDR would always be the frontend container).
	 * shortcut: trusts the header; when the API is reachable without the frontend (dev API_PORT) a client can spoof it.
	 */
	public static function ip(): string
	{
		$forwarded = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '')[0]);
		return filter_var($forwarded, FILTER_VALIDATE_IP) ? $forwarded : ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
	}
}
