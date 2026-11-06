<?php

declare(strict_types=1);

namespace Features\Projects\Model;

/** A manager's request to make a member a manager. The owner decides. */
final readonly class PromotionRequest
{
	public function __construct(
		public int $id,
		public int $projectId,
		public int $memberId,
		public string $memberName,
		public int $requestedBy,
		public string $requestedByName,
		public string $status,
		public int $createdAt,
	) {}

	/** @return array<string,mixed> */
	public function toArray(): array
	{
		return [
			'id' => $this->id,
			'project_id' => $this->projectId,
			'member' => ['user_id' => $this->memberId, 'name' => $this->memberName],
			'requested_by' => ['user_id' => $this->requestedBy, 'name' => $this->requestedByName],
			'status' => $this->status,
			'created_at' => $this->createdAt,
		];
	}
}
