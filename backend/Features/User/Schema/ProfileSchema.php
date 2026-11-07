<?php

declare(strict_types=1);

namespace Features\User\Schema;

use Features\User\Model\Profile;
use Features\User\Model\Role;
use Shared\Helpers\Nullable;

/** The profile columns of `users`: which columns a query selects, and how a row becomes a Profile. */
final class ProfileSchema
{
	public const COLUMNS = 'id, name, email, role, identity_number, study_program, cohort_year, phone, bio, avatar_url,
	                        UNIX_TIMESTAMP(created_at) AS created_at';

	/**
	 * @param array<string,mixed> $row
	 * @param list<array<string,mixed>> $projects the published projects the person is on (they come from another table)
	 */
	public static function profile(array $row, array $projects): Profile
	{
		return new Profile(
			(int) $row['id'],
			(string) $row['name'],
			(string) $row['email'],
			Role::from((string) $row['role']),
			Nullable::text($row['identity_number']),
			Nullable::text($row['study_program']),
			Nullable::number($row['cohort_year']),
			Nullable::text($row['phone']),
			Nullable::text($row['bio']),
			Nullable::text($row['avatar_url']),
			(int) $row['created_at'],
			$projects,
		);
	}
}
