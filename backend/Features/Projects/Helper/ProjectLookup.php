<?php

declare(strict_types=1);

namespace Features\Projects\Helper;

use Core\Response;
use Features\Projects\Model\Project;
use Features\Projects\Repository\ProjectRepository;
use Shared\Helpers\Fail;

/** Finding the project an endpoint is about, with the 404 or 409 that fits when it cannot be used. */
final class ProjectLookup
{
	public function __construct(private ProjectRepository $projects = new ProjectRepository()) {}

	/** The project, if it exists and belongs to this user. Anything else is a 404: no probing for ids. */
	public function owned(int $id, int $userId): Project
	{
		$project = $this->load($id);
		if ($project->ownerId !== $userId) {
			Fail::notFound('Proyek');
		}
		return $project;
	}

	/** The project, or a 404 when it does not exist. A draft is private to its owner, so it never counts. */
	public function load(int $id): Project
	{
		$project = $this->projects->find($id);
		if ($project === null) {
			Fail::notFound('Proyek');
		}
		if ($project->status === 'draft') {
			Fail::notFound('Proyek');
		}
		return $project;
	}

	/** Team changes only make sense while the project is public. */
	public static function requirePublished(Project $project): void
	{
		if ($project->status !== 'published') {
			Response::fail(409, 'Proyek ini sedang tidak dipublikasikan.');
		}
	}
}
