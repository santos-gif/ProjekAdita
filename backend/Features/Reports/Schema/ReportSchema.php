<?php

declare(strict_types=1);

namespace Features\Reports\Schema;

use Features\Reports\Model\Report;
use Shared\Helpers\Nullable;

/** The `project_reports` table: what a report query selects (aliases r, p, ru, hu), and how a row becomes a Report. */
final class ReportSchema
{
	private const REPORT_COLUMNS = 'r.id, r.project_id, r.reporter_id, r.reason, r.status, r.note, r.handled_by';
	private const PROJECT_COLUMNS = 'p.project_name, p.status AS project_status, p.owner_id AS project_owner_id';
	private const NAME_COLUMNS = 'ru.name AS reporter_name, hu.name AS handled_by_name';
	private const DATE_COLUMNS = 'UNIX_TIMESTAMP(r.created_at) AS created_at, UNIX_TIMESTAMP(r.handled_at) AS handled_at';

	// hu is a LEFT JOIN: a report nobody has handled yet has no handler.
	private const TABLES = 'FROM project_reports r
	                        JOIN projects p ON p.id = r.project_id
	                        JOIN users ru ON ru.id = r.reporter_id
	                        LEFT JOIN users hu ON hu.id = r.handled_by';

	/** A complete SELECT; add the WHERE and ORDER BY after it. */
	public const SELECT = 'SELECT ' . self::REPORT_COLUMNS . ', ' . self::PROJECT_COLUMNS . ', ' . self::NAME_COLUMNS . ', '
		. self::DATE_COLUMNS . ' ' . self::TABLES;

	/** @param array<string,mixed> $row */
	public static function report(array $row): Report
	{
		return new Report(
			(int) $row['id'],
			(int) $row['project_id'],
			(string) $row['project_name'],
			(string) $row['project_status'],
			(int) $row['project_owner_id'],
			(int) $row['reporter_id'],
			(string) $row['reporter_name'],
			(string) $row['reason'],
			(string) $row['status'],
			Nullable::text($row['note']),
			Nullable::number($row['handled_by']),
			Nullable::text($row['handled_by_name']),
			(int) $row['created_at'],
			Nullable::number($row['handled_at']),
		);
	}
}
