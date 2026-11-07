<?php

declare(strict_types=1);

namespace Features\Review\Schema;

use Features\Projects\Schema\ProjectSchema;
use Features\Review\Model\ReviewEntry;
use Features\Review\Model\ReviewItem;
use Shared\Helpers\Nullable;

/** The `project_reviews` table and the projects waiting for review: what the queries select, and how rows become models. */
final class ReviewSchema
{
	/** A project plus its owner's name. Query: FROM projects p JOIN users u ON u.id = p.owner_id. */
	public const ITEM_COLUMNS = ProjectSchema::COLUMNS . ', u.name AS owner_name';

	/** A complete SELECT of review decisions (aliases r, u, p); add the WHERE and ORDER BY after it. */
	public const ENTRY_SELECT = 'SELECT r.id, r.project_id, p.project_name, r.reviewer_id, u.name AS reviewer_name, r.stage,
	                                    r.is_approved, r.reason, UNIX_TIMESTAMP(r.created_at) AS created_at
	                             FROM project_reviews r
	                             JOIN users u ON u.id = r.reviewer_id
	                             JOIN projects p ON p.id = r.project_id';

	/** @param array<string,mixed> $row */
	public static function item(array $row): ReviewItem
	{
		return new ReviewItem(ProjectSchema::project($row), (string) $row['owner_name']);
	}

	/** @param array<string,mixed> $row */
	public static function entry(array $row): ReviewEntry
	{
		return new ReviewEntry(
			(int) $row['id'],
			(int) $row['project_id'],
			(string) $row['project_name'],
			(int) $row['reviewer_id'],
			(string) $row['reviewer_name'],
			(string) $row['stage'],
			(bool) $row['is_approved'],
			Nullable::text($row['reason']),
			(int) $row['created_at'],
		);
	}
}
