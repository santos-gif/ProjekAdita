<?php

declare(strict_types=1);

namespace Features\Projects\Model;

/** One person on a project. Only public details: never an email or a phone number. */
final readonly class Member
{
	public function __construct(
		public int $userId,
		public string $name,
		public ProjectRole $projectRole,
		public string $title,
		public int $joinedAt,
	) {}

	/** @return array<string,mixed> */
	public function toArray(): array
	{
		return [
			'user_id' => $this->userId,
			'name' => $this->name,
			'project_role' => $this->projectRole->value,
			'title' => $this->title,
			'joined_at' => $this->joinedAt,
		];
	}
}
