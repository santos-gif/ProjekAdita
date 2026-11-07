<?php

declare(strict_types=1);

namespace Features\Applications\Helper;

use Features\Applications\Model\Application;
use Features\Notifications\NotificationType;
use Features\Notifications\Repository\NotificationRepository;
use Features\Projects\Model\ProjectRole;
use Features\Projects\Repository\MemberRepository;
use Features\Projects\Repository\ProjectRepository;
use Features\User\Repository\UserRepository;

/** The notifications that come with applications: leaders hear about a new one, the applicant hears the decision. */
final class ApplicationNotifier
{
	public function __construct(
		private ProjectRepository $projects = new ProjectRepository(),
		private UserRepository $users = new UserRepository(),
		private MemberRepository $members = new MemberRepository(),
		private NotificationRepository $notifications = new NotificationRepository(),
	) {}

	/** The owner and the managers hear about every new application. */
	public function tellLeaders(int $projectId, int $applicantId, string $desiredTitle): void
	{
		$project = $this->projects->find($projectId);
		$applicant = $this->users->findPublicById($applicantId);
		if ($project === null || $applicant === null) {
			return;
		}

		$leaders = $this->members->userIdsWithRoles($projectId, [ProjectRole::Owner, ProjectRole::Manager]);
		$this->notifications->addForUsers(
			$leaders,
			NotificationType::APPLICATION_RECEIVED,
			$applicant->name . ' melamar ke proyek "' . $project->title . '" sebagai ' . $desiredTitle . '.',
			$projectId,
		);
	}

	/** Tells the applicant how it went: "Lamaran Anda ke proyek "X" <ending>". */
	public function tellApplicant(Application $application, string $type, string $ending): void
	{
		$project = $this->projects->find($application->projectId);
		if ($project === null) {
			return;
		}

		$message = 'Lamaran Anda ke proyek "' . $project->title . '" ' . $ending;
		$this->notifications->add($application->userId, $type, $message, $application->projectId);
	}
}
