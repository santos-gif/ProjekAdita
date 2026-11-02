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
		if (!stripos($contentType, 'application/json')) {
			Response::fail(415, 'Unsupported Media Type.');
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
}
