<?php

declare(strict_types=1);

namespace Features\Projects\Schema;

use Features\Projects\Model\Member;
use Features\Projects\Model\ProjectRole;

/** The `project_members` table with the person's name: which columns a query selects (aliases m, u), and how a row becomes a Member. */
final class MemberSchema
{
	public const COLUMNS = 'm.user_id, u.name, m.role, m.title, UNIX_TIMESTAMP(m.joined_at) AS joined_at';

	/** @param array<string,mixed> $row */
	public static function member(array $row): Member
	{
		// ProjectRole::from throws on an unknown value: a corrupt row becomes a generic 500, not a silent default.
		return new Member(
			(int) $row['user_id'],
			(string) $row['name'],
			ProjectRole::from((string) $row['role']),
			(string) $row['title'],
			(int) $row['joined_at'],
		);
	}
}
