<?php

declare(strict_types=1);

namespace Features\Applications\Model;

/** An application as the applicant sees it: with the project it is for. */
final readonly class MyApplicationEntry
{
	public function __construct(
		public Application $application,
		public string $projectTitle,
		public ?string $projectThumbnail,
		public string $projectStatus,
	) {}

	/** @return array<string,mixed> */
	public function toArray(): array
	{
		$data = $this->application->toArray();
		$data['project'] = [
			'id' => $this->application->projectId,
			'title' => $this->projectTitle,
			'thumbnail' => $this->projectThumbnail,
			'status' => $this->projectStatus,
		];
		return $data;
	}
}
