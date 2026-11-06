<?php

declare(strict_types=1);

namespace Features\Review\Model;

/** One row of project_reviews: who decided what, at which stage, and why. */
final readonly class ReviewEntry
{
	public function __construct(
		public int $id,
		public int $projectId,
		public string $projectTitle,
		public int $reviewerId,
		public string $reviewerName,
		public string $stage,
		public bool $approved,
		public ?string $reason,
		public int $createdAt,
	) {}

	/** @return array<string,mixed> */
	public function toArray(): array
	{
		return [
			'id' => $this->id,
			'project_id' => $this->projectId,
			'project_title' => $this->projectTitle,
			'reviewer' => ['id' => $this->reviewerId, 'name' => $this->reviewerName],
			'stage' => $this->stage,
			'approved' => $this->approved,
			'reason' => $this->reason,
			'created_at' => $this->createdAt,
		];
	}
}
