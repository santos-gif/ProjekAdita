<?php

declare(strict_types=1);

namespace Shared\Helpers;

use Core\Response;

/** The two refusals that many controllers repeat. Both end the request with the standard error body. */
final class Fail
{
	/** 404 "<noun> tidak ditemukan.", for example Fail::notFound('Proyek'). */
	public static function notFound(string $noun): never
	{
		Response::fail(404, $noun . ' tidak ditemukan.');
	}

	/**
	 * 422 with the field errors, but only when there are any; an empty list lets the request go on.
	 * @param array<string,string> $errors field name => message
	 */
	public static function invalid(array $errors): void
	{
		if ($errors !== []) {
			Response::fail(422, 'Data tidak valid.', $errors);
		}
	}
}
