<?php

declare(strict_types=1);

namespace Features\Applications\Model;

/**
 * One application. status is the raw column (pending, accepted, rejected, withdrawn); outcome is what it MEANS:
 *   pending, accepted,
 *   declined  = an owner/manager said no (stored as withdrawn): the person may apply again,
 *   banned    = stored as rejected: the person may not apply to this project again,
 *   withdrawn = the applicant took it back themselves.
 */
final readonly class Application
{
	public function __construct(
		public int $id,
		public int $projectId,
		public int $userId,
		public string $desiredTitle,
		public string $message,
		public string $status,
		public string $outcome,
		public int $createdAt,
		public ?int $decidedAt,
	) {}

	/** @return array<string,mixed> */
	public function toArray(): array
	{
		return [
			'id' => $this->id,
			'project_id' => $this->projectId,
			'desired_title' => $this->desiredTitle,
			'message' => $this->message,
			'status' => $this->status,
			'outcome' => $this->outcome,
			'created_at' => $this->createdAt,
			'decided_at' => $this->decidedAt,
		];
	}
}
