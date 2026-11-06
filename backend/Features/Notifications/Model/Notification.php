<?php

declare(strict_types=1);

namespace Features\Notifications\Model;

/** One notification for one user. toArray() is the only JSON shape. */
final readonly class Notification
{
	public function __construct(
		public int $id,
		public string $type,
		public ?int $projectId,
		public string $message,
		public bool $isRead,
		public int $createdAt,
	) {}

	/** @return array<string,mixed> */
	public function toArray(): array
	{
		return [
			'id' => $this->id,
			'type' => $this->type,
			'project_id' => $this->projectId,
			'message' => $this->message,
			'read' => $this->isRead,
			'created_at' => $this->createdAt,
		];
	}
}
