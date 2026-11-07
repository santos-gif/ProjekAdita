<?php

declare(strict_types=1);

namespace Shared\Helpers;

/** Reads values from the query string (?a=b) and cleans them, so controllers do not repeat the checks. */
final class Query
{
	/** A trimmed string cut to $maxLength; an empty string when the key is missing or is not plain text. */
	public static function text(string $key, int $maxLength): string
	{
		$value = $_GET[$key] ?? '';
		if (!is_string($value)) {
			return '';
		}
		return mb_substr(trim($value), 0, $maxLength);
	}

	/** An integer kept between $min and $max; $default when the key is missing or is not a whole number. */
	public static function number(string $key, int $default, int $min, int $max): int
	{
		$value = $_GET[$key] ?? null;
		if (!is_string($value) || !ctype_digit($value)) {
			return $default;
		}

		$number = (int) $value;
		if ($number < $min) {
			return $min;
		}
		if ($number > $max) {
			return $max;
		}
		return $number;
	}
}
