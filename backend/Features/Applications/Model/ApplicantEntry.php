<?php

declare(strict_types=1);

namespace Features\Applications\Model;

/** An application as an owner or manager sees it: with who applied. The bio holds their CV, skills and availability. */
final readonly class ApplicantEntry
{
	public function __construct(
		public Application $application,
		public string $name,
		public ?string $studyProgram,
		public ?int $cohortYear,
		public ?string $bio,
	) {}

	/** @return array<string,mixed> */
	public function toArray(): array
	{
		$data = $this->application->toArray();
		$data['applicant'] = [
			'user_id' => $this->application->userId,
			'name' => $this->name,
			'study_program' => $this->studyProgram,
			'cohort_year' => $this->cohortYear,
			'bio' => $this->bio,
		];
		return $data;
	}
}
