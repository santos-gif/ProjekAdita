<?php

declare(strict_types=1);

namespace Features\User\Model;

/**
 * A user's profile. The bio holds their CV, skills and availability as text. Two views of it:
 * the public one (what other users see) and the owner's own (adds email, role, ID number and phone).
 */
final readonly class Profile
{
	/** @param list<array<string,mixed>> $projects the published projects this person is on */
	public function __construct(
		public int $id,
		public string $name,
		public string $email,
		public string $role,
		public ?string $identityNumber,
		public ?string $studyProgram,
		public ?int $cohortYear,
		public ?string $phone,
		public ?string $bio,
		public ?string $avatarUrl,
		public int $createdAt,
		public array $projects,
	) {}

	/** @return array<string,mixed> what other users may see: never the email, the phone or the ID number */
	public function toPublicArray(): array
	{
		return [
			'id' => $this->id,
			'name' => $this->name,
			'study_program' => $this->studyProgram,
			'cohort_year' => $this->cohortYear,
			'bio' => $this->bio,
			'avatar' => $this->avatarUrl,
			'member_since' => $this->createdAt,
			'projects' => $this->projects,
		];
	}

	/** @return array<string,mixed> */
	public function toOwnArray(): array
	{
		$data = $this->toPublicArray();
		$data['email'] = $this->email;
		$data['role'] = $this->role;
		$data['identity_number'] = $this->identityNumber;
		$data['phone'] = $this->phone;
		return $data;
	}
}
