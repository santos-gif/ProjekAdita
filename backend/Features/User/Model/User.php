<?php

declare(strict_types=1);

namespace Features\User\Model;

use Features\User\Model\Role;

/** The public user. toArray() is the only JSON shape; frontend/features/auth/auth.types.js mirrors it. */
final readonly class User
{
	public function __construct(
		public int $id,
		public string $name,
		public string $email,
		public Role $accountRole,
	) {}

	/** @return array{id:int,name:string,email:string,role:string} "role" is the account role (user, moderator, admin) */
	public function toArray(): array
	{
		return ['id' => $this->id, 'name' => $this->name, 'email' => $this->email, 'role' => $this->accountRole->value];
	}
}
