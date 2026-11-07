<?php

declare(strict_types=1);

namespace Shared\Helpers;

/** Turns a database value into a string or int while keeping NULL as NULL (a plain cast would turn it into "" or 0). */
final class Nullable
{
	public static function text(mixed $value): ?string
	{
		if ($value === null) {
			return null;
		}
		return (string) $value;
	}

	public static function number(mixed $value): ?int
	{
		if ($value === null) {
			return null;
		}
		return (int) $value;
	}
}
