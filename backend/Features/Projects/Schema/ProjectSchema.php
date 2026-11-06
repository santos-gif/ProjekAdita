<?php

declare(strict_types=1);

namespace Features\Projects\Schema;

use Features\Projects\Model\Project;

/** The `projects` table: which columns each query selects, and how a row becomes a Project. */
final class ProjectSchema
{
	/**
	 * The tags and the member count come from sub-selects, so one query returns a whole project.
	 * Dates are converted to unix seconds in SQL. Query with the alias "p": FROM projects p.
	 */
	public const COLUMNS = "p.id, p.owner_id, p.project_name, p.description, p.requirements, p.selection_method, p.status, p.thumbnail_url,
		UNIX_TIMESTAMP(p.created_at) AS created_at, UNIX_TIMESTAMP(p.updated_at) AS updated_at,
		UNIX_TIMESTAMP(p.published_at) AS published_at, p.accepting_applications,
		(SELECT COUNT(*) FROM project_applications a WHERE a.project_id = p.id AND a.status = 'pending') AS applicants_count,
		(SELECT r.reason FROM project_reviews r WHERE r.project_id = p.id AND r.is_approved = 0 ORDER BY r.id DESC LIMIT 1) AS rejection_reason,
		(SELECT COUNT(*) FROM project_members m WHERE m.project_id = p.id) AS members_count,
		(SELECT JSON_ARRAYAGG(t.name ORDER BY t.name) FROM project_tags pt JOIN tags t ON t.id = pt.tag_id WHERE pt.project_id = p.id) AS tags";

	/** @param array<string,mixed> $row */
	public static function project(array $row): Project
	{
		// JSON_ARRAYAGG gives NULL when the project has no tags, else a JSON text like ["AI","IoT"].
		$tags = [];
		if ($row['tags'] !== null) {
			$tags = json_decode((string) $row['tags'], true, 4, JSON_THROW_ON_ERROR);
		}

		$thumbnailUrl = null;
		if ($row['thumbnail_url'] !== null) {
			$thumbnailUrl = (string) $row['thumbnail_url'];
		}

		$publishedAt = null;
		if ($row['published_at'] !== null) {
			$publishedAt = (int) $row['published_at'];
		}

		$rejectionReason = null;
		if ($row['rejection_reason'] !== null) {
			$rejectionReason = (string) $row['rejection_reason'];
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
			$thumbnailUrl,
			(int) $row['members_count'],
			(int) $row['created_at'],
			(int) $row['updated_at'],
			$publishedAt,
			$rejectionReason,
			(bool) $row['accepting_applications'],
			(int) $row['applicants_count'],
		);
	}
}
