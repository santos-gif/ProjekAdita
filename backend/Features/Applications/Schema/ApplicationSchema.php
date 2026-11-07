<?php

declare(strict_types=1);

namespace Features\Applications\Schema;

use Features\Applications\Model\ApplicantEntry;
use Features\Applications\Model\Application;
use Features\Applications\Model\MyApplicationEntry;
use Shared\Helpers\Nullable;

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

	/** Added to COLUMNS for the project owner's list. Query: JOIN users u ON u.id = a.user_id. */
	public const APPLICANT_COLUMNS = 'u.name AS applicant_name, u.study_program, u.cohort_year, u.bio';

	/** Added to COLUMNS for "my applications". Query: JOIN projects p ON p.id = a.project_id. */
	public const PROJECT_COLUMNS = 'p.project_name, p.thumbnail_url, p.status AS project_status';

	/** @param array<string,mixed> $row */
	public static function applicantEntry(array $row): ApplicantEntry
	{
		return new ApplicantEntry(
			self::application($row),
			(string) $row['applicant_name'],
			Nullable::text($row['study_program']),
			Nullable::number($row['cohort_year']),
			Nullable::text($row['bio']),
		);
	}

	/** @param array<string,mixed> $row */
	public static function myEntry(array $row): MyApplicationEntry
	{
		return new MyApplicationEntry(
			self::application($row),
			(string) $row['project_name'],
			Nullable::text($row['thumbnail_url']),
			(string) $row['project_status'],
		);
	}

	/** @param array<string,mixed> $row */
	public static function application(array $row): Application
	{
		return new Application(
			(int) $row['id'],
			(int) $row['project_id'],
			(int) $row['user_id'],
			(string) $row['desired_title'],
			(string) ($row['message'] ?? ''),
			(string) $row['status'],
			(string) $row['outcome'],
			(int) $row['created_at'],
			Nullable::number($row['decided_at']),
		);
	}
}
