<?php

declare(strict_types=1);

namespace Features\Admin\Schema;

use Features\Admin\Model\AdminUser;
use Features\User\Model\Role;
use Shared\Helpers\Nullable;

/** The `users` table as the admin's user list sees it: which columns a query selects, and how a row becomes an AdminUser. */
final class AdminUserSchema
{
	public const COLUMNS = 'id, name, email, role, study_program, cohort_year, UNIX_TIMESTAMP(created_at) AS created_at';

	/** @param array<string,mixed> $row */
	public static function user(array $row): AdminUser
	{
		return new AdminUser(
			(int) $row['id'],
			(string) $row['name'],
			(string) $row['email'],
			Role::from((string) $row['role']),
			Nullable::text($row['study_program']),
			Nullable::number($row['cohort_year']),
			(int) $row['created_at'],
		);
	}
}
