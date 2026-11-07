<?php

declare(strict_types=1);

namespace Features\Notifications\Helper;

/** The wording limits of a notification. */
final class NotificationText
{
	/** The width of notifications.message. */
	private const MAX_LENGTH = 255;

	/** Cuts a long message so it fits the column; the end becomes an ellipsis. */
	public static function shorten(string $message): string
	{
		return mb_strimwidth($message, 0, self::MAX_LENGTH, '…');
	}
}
