<?php

declare(strict_types=1);

namespace Features\Reports\Helper;

/** Reading the optional note a handler attaches to a report. */
final class ReportNote
{
	private const MAX_LENGTH = 1000;

	/**
	 * @param array<string,mixed> $body
	 * @return string|null the trimmed note cut to its limit, or null when there is none
	 */
	public static function read(array $body): ?string
	{
		if (!is_string($body['note'] ?? null)) {
			return null;
		}
		$note = trim($body['note']);
		if ($note === '') {
			return null;
		}
		return mb_substr($note, 0, self::MAX_LENGTH);
	}
}
