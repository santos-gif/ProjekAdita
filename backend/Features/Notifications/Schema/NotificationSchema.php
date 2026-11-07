<?php

declare(strict_types=1);

namespace Features\Notifications\Schema;

use Features\Notifications\Model\Notification;
use Shared\Helpers\Nullable;

/** The `notifications` table: which columns a query selects, and how a row becomes a Notification. */
final class NotificationSchema
{
	public const COLUMNS = 'id, type, project_id, message, read_at IS NOT NULL AS is_read, UNIX_TIMESTAMP(created_at) AS created_at';

	/** @param array<string,mixed> $row */
	public static function notification(array $row): Notification
	{
		return new Notification(
			(int) $row['id'],
			(string) $row['type'],
			Nullable::number($row['project_id']),
			(string) $row['message'],
			(bool) $row['is_read'],
			(int) $row['created_at'],
		);
	}
}
