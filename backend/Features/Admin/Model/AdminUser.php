<?php

declare(strict_types=1);

namespace Features\Admin\Model;

/** A user as an admin sees them in the user list: the email is included, the password hash never is. */
final readonly class AdminUser
{
	public function __construct(
		public int $id,
		public string $name,
		public string $email,
		public string $role,
		public ?string $studyProgram,
		public ?int $cohortYear,
		public int $createdAt,
	) {}

	/** @return array<string,mixed> */
	public function toArray(): array
	{
		return [
			'id' => $this->id,
			'name' => $this->name,
			'email' => $this->email,
			'role' => $this->role,
			'study_program' => $this->studyProgram,
			'cohort_year' => $this->cohortYear,
			'created_at' => $this->createdAt,
		];
	}
}
