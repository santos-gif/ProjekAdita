<?php

declare(strict_types=1);

namespace Features\Applications\Schema;

use Features\Applications\Model\Application;

/** The `project_applications` table: which columns a query selects (alias "a"), and how a row becomes an Application. */
final class ApplicationSchema
{
	/**
	 * What an application MEANS, worked out from status and decided_by (see the Application model).
	 * Use it inside a query that names the table "a".
	 */
	public const OUTCOME_SQL = "CASE a.status
		WHEN 'withdrawn' THEN IF(a.decided_by IS NULL, 'withdrawn', 'declined')
		WHEN 'rejected' THEN 'banned'
		ELSE a.status END";

	public const COLUMNS = 'a.id, a.project_id, a.user_id, a.desired_title, a.message, a.status, '
		. self::OUTCOME_SQL . ' AS outcome, UNIX_TIMESTAMP(a.created_at) AS created_at, UNIX_TIMESTAMP(a.decided_at) AS decided_at';

	/** @param array<string,mixed> $row */
	public static function application(array $row): Application
	{
		$decidedAt = null;
		if ($row['decided_at'] !== null) {
			$decidedAt = (int) $row['decided_at'];
		}

		return new Application(
			(int) $row['id'],
			(int) $row['project_id'],
			(int) $row['user_id'],
			(string) $row['desired_title'],
			(string) ($row['message'] ?? ''),
			(string) $row['status'],
			(string) $row['outcome'],
			(int) $row['created_at'],
			$decidedAt,
		);
	}
}
