<?php

declare(strict_types=1);

namespace Features\Discovery\Model;

use Features\Projects\Model\Project;
use Features\Projects\Model\ProjectRole;

/**
 * A project as one particular viewer sees it: the project, who made it, what the viewer is on it
 * (my_project_role) and what became of their application (my_application), and whether they may apply now.
 */
final readonly class ProjectView
{
	/**
	 * @param ProjectRole|null $myProjectRole the viewer's role on THIS project (owner, manager, member), or null when they are not on it; not their account role
	 * @param string|null $myApplication      the outcome of the viewer's application (pending, accepted, declined, banned, withdrawn), or null when they never applied
	 */
	public function __construct(
		public Project $project,
		public string $ownerName,
		public ?ProjectRole $myProjectRole,
		public ?string $myApplication,
	) {}

	public function canApply(): bool
	{
		if ($this->project->status !== 'published') {
			return false;
		}
		if (!$this->project->acceptingApplications) {
			return false;
		}
		if ($this->myProjectRole !== null) {
			return false;
		}
		if ($this->myApplication === 'pending') {
			return false;
		}
		if ($this->myApplication === 'banned') {
			return false;
		}
		return true;
	}

	/** @return array<string,mixed> */
	public function toArray(): array
	{
		$data = $this->project->toArray();
		$data['owner'] = ['id' => $this->project->ownerId, 'name' => $this->ownerName];
		$data['my_project_role'] = $this->myProjectRole?->value;
		$data['my_application'] = $this->myApplication;
		$data['can_apply'] = $this->canApply();
		return $data;
	}
}
