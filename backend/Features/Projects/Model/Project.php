<?php

declare(strict_types=1);

namespace Features\Projects\Model;

/**
 * A project as the owner sees it. toArray() is the only JSON shape; the names are the frontend's own
 * (title, thumbnail), so the pages keep working. Dates are unix seconds, like sessions.exp.
 * ownerId is for the controller's ownership check and is never sent out.
 */
final readonly class Project
{
	/** @param list<string> $tags */
	public function __construct(
		public int $id,
		public int $ownerId,
		public string $title,
		public string $description,
		public string $requirements,
		public string $selectionMethod,
		public string $status,
		public array $tags,
		public ?string $thumbnailUrl,
		public int $membersCount,
		public int $createdAt,
		public int $updatedAt,
		public ?int $publishedAt,
		public ?string $rejectionReason,
		public bool $acceptingApplications,
		public int $applicantsCount,
	) {}

	/** @return array<string,mixed> */
	public function toArray(): array
	{
		return [
			'id' => $this->id,
			'title' => $this->title,
			'description' => $this->description,
			'requirements' => $this->requirements,
			'selection_method' => $this->selectionMethod,
			'status' => $this->status,
			'tags' => $this->tags,
			'thumbnail' => $this->thumbnailUrl,
			'members_count' => $this->membersCount,
			'created_at' => $this->createdAt,
			'updated_at' => $this->updatedAt,
			'published_at' => $this->publishedAt,
			'rejection_reason' => $this->rejectionReason,
			'applications_open' => $this->acceptingApplications,
			'applicants_count' => $this->applicantsCount,
		];
	}
}
