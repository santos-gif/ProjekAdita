<?php

declare(strict_types=1);

namespace Features\Reports\Model;

/** One report of a project by a user. status: open -> (dismissed | forwarded -> (dismissed | actioned)). */
final readonly class Report
{
	public function __construct(
		public int $id,
		public int $projectId,
		public string $projectTitle,
		public string $projectStatus,
		public int $projectOwnerId,
		public int $reporterId,
		public string $reporterName,
		public string $reason,
		public string $status,
		public ?string $note,
		public ?int $handledById,
		public ?string $handledByName,
		public int $createdAt,
		public ?int $handledAt,
	) {}

	/** @return array<string,mixed> */
	public function toArray(): array
	{
		$handledBy = null;
		if ($this->handledById !== null) {
			$handledBy = ['id' => $this->handledById, 'name' => $this->handledByName];
		}

		return [
			'id' => $this->id,
			'project' => ['id' => $this->projectId, 'title' => $this->projectTitle, 'status' => $this->projectStatus],
			'reporter' => ['id' => $this->reporterId, 'name' => $this->reporterName],
			'reason' => $this->reason,
			'status' => $this->status,
			'note' => $this->note,
			'handled_by' => $handledBy,
			'created_at' => $this->createdAt,
			'handled_at' => $this->handledAt,
		];
	}
}
