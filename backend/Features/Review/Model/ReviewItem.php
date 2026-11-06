<?php

declare(strict_types=1);

namespace Features\Review\Model;

use Features\Projects\Model\Project;

/** A project as a reviewer sees it: the project plus who submitted it. */
final readonly class ReviewItem
{
	public function __construct(
		public Project $project,
		public string $ownerName,
	) {}

	/** @return array<string,mixed> */
	public function toArray(): array
	{
		$data = $this->project->toArray();
		$data['owner'] = ['id' => $this->project->ownerId, 'name' => $this->ownerName];
		return $data;
	}
}
