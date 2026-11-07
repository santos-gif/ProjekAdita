<?php

declare(strict_types=1);

namespace Features\Projects\Schema;

use Features\Projects\Model\Project;
use Shared\Helpers\Nullable;

/** The `projects` table: which columns each query selects, and how a row becomes a Project. */
final class ProjectSchema
{
	// The columns of the `projects` row itself.
	private const OWN_COLUMNS = 'p.id, p.owner_id, p.project_name, p.description, p.requirements, p.selection_method, p.status,
		p.thumbnail_url, p.accepting_applications';

	// Dates are converted to unix seconds in SQL.
	private const DATE_COLUMNS = 'UNIX_TIMESTAMP(p.created_at) AS created_at, UNIX_TIMESTAMP(p.updated_at) AS updated_at,
		UNIX_TIMESTAMP(p.published_at) AS published_at';

	// Everything below is a sub-select, so one query returns a whole project.
	private const APPLICANTS_COUNT = "(SELECT COUNT(*) FROM project_applications a
		WHERE a.project_id = p.id AND a.status = 'pending') AS applicants_count";

	private const REJECTION_REASON = '(SELECT r.reason FROM project_reviews r
		WHERE r.project_id = p.id AND r.is_approved = 0 ORDER BY r.id DESC LIMIT 1) AS rejection_reason';

	private const MEMBERS_COUNT = '(SELECT COUNT(*) FROM project_members m WHERE m.project_id = p.id) AS members_count';

	private const TAGS = '(SELECT JSON_ARRAYAGG(t.name ORDER BY t.name) FROM project_tags pt
		JOIN tags t ON t.id = pt.tag_id WHERE pt.project_id = p.id) AS tags';

	/** What every project query selects. Query with the alias "p": FROM projects p. */
	public const COLUMNS = self::OWN_COLUMNS . ', ' . self::DATE_COLUMNS . ', ' . self::APPLICANTS_COUNT . ', '
		. self::REJECTION_REASON . ', ' . self::MEMBERS_COUNT . ', ' . self::TAGS;

	/** @param array<string,mixed> $row */
	public static function project(array $row): Project
	{
		// JSON_ARRAYAGG gives NULL when the project has no tags, else a JSON text like ["AI","IoT"].
		$tags = [];
		if ($row['tags'] !== null) {
			$tags = json_decode((string) $row['tags'], true, 4, JSON_THROW_ON_ERROR);
		}

		return new Project(
			(int) $row['id'],
			(int) $row['owner_id'],
			(string) $row['project_name'],
			(string) $row['description'],
			(string) ($row['requirements'] ?? ''),
			(string) $row['selection_method'],
			(string) $row['status'],
			$tags,
			Nullable::text($row['thumbnail_url']),
			(int) $row['members_count'],
			(int) $row['created_at'],
			(int) $row['updated_at'],
			Nullable::number($row['published_at']),
			Nullable::text($row['rejection_reason']),
			(bool) $row['accepting_applications'],
			(int) $row['applicants_count'],
		);
	}
}
