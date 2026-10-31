<?php

declare(strict_types=1);

namespace Features\Auth\Schema;

use Features\Auth\Model\Role;
use Features\Auth\Model\User;
use Features\Auth\Model\UserCredentials;

/** The `users` table: which columns each query selects, and how a row becomes a Model. */
final class UserSchema
{
    public const PUBLIC_COLUMNS = 'id, name, email, role';
    public const CREDENTIAL_COLUMNS = 'id, name, email, role, password_hash';

    /** @param array<string,mixed> $row */
    public static function user(array $row): User
    {
        // Role::from throws on an unknown value: a corrupt row becomes a generic 500, not a silent default.
        return new User((int) $row['id'], (string) $row['name'], (string) $row['email'], Role::from((string) $row['role']));
    }

    /** @param array<string,mixed> $row */
    public static function credentials(array $row): UserCredentials
    {
        return new UserCredentials(self::user($row), (string) $row['password_hash']);
    }
}
