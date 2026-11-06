<?php

declare(strict_types=1);

namespace Features\Applications;

use Core\Auth;
use Core\Request;
use Core\Response;
use Features\Applications\Model\Application;
use Features\Applications\Model\ApplicationInput;
use Features\Applications\Model\ApplyResult;
use Features\Applications\Model\DecisionResult;
use Features\Notifications\NotificationRepository;
use Features\Notifications\NotificationType;
use Features\Projects\MemberRepository;
use Features\Projects\Model\ProjectRole;
use Features\Projects\ProjectRepository;
use Features\User\UserRepository;

/**
 * Joining a project. Anyone logged in may apply to a published project that is open; the owner and the managers
 * decide, each on their own. A decline lets the person apply again, a ban does not.
 */
final class ApplicationController
{
	public function __construct(
		private ApplicationRepository $applications = new ApplicationRepository(),
		private ProjectRepository $projects = new ProjectRepository(),
		private MemberRepository $members = new MemberRepository(),
		private UserRepository $users = new UserRepository(),
		private NotificationRepository $notifications = new NotificationRepository(),
	) {}

	/** POST /projects/{id}/applications {desired_title, message} */
	public function apply(int $projectId): void
	{
		$uid = Auth::requireUserId();

		$in = ApplicationInput::fromRequest(Request::body());
		$errors = $in->errors();
		if ($errors !== []) {
			Response::fail(422, 'Data tidak valid.', $errors);
		}

		$result = $this->applications->apply($projectId, $uid, $in->desiredTitle, $in->message);
		$this->failUnlessApplied($result);

		$this->tellLeadersAboutApplication($projectId, $uid, $in->desiredTitle);
		$application = $this->applications->findFor($projectId, $uid);
		Response::ok(['application' => $this->asArray($application)], 201, 'Lamaran terkirim.');
	}

	/** GET /projects/{id}/applications?status=pending|all: for the owner and the managers. */
	public function forProject(int $projectId): void
	{
		$uid = Auth::requireUserId();
		$this->requireLeader($projectId, $uid);

		$pendingOnly = ($_GET['status'] ?? 'pending') !== 'all';

		$list = [];
		foreach ($this->applications->listForProject($projectId, $pendingOnly) as $entry) {
			$list[] = $entry->toArray();
		}
		Response::ok(['applications' => $list]);
	}

	/** POST /applications/{id}/accept: the applicant becomes a member at once. Owner or manager, no one else needed. */
	public function accept(int $id): void
	{
		$uid = Auth::requireUserId();
		$application = $this->findOrFail($id);
		$this->requireLeader($application->projectId, $uid);

		$result = $this->applications->accept($id, $uid);
		$this->failUnlessDone($result);

		$this->tellApplicant(
			$application,
			NotificationType::APPLICATION_ACCEPTED,
			'diterima. Anda sekarang anggota proyek.',
		);
		Response::ok(['application' => $this->asArray($this->applications->find($id))], 200, 'Lamaran diterima.');
	}

	/** POST /applications/{id}/reject {ban?}: a plain decline, or a ban from this project. Owner or manager. */
	public function reject(int $id): void
	{
		$uid = Auth::requireUserId();
		$application = $this->findOrFail($id);
		$this->requireLeader($application->projectId, $uid);

		$body = Request::body();
		$ban = ($body['ban'] ?? false) === true;

		$result = $this->applications->decline($id, $uid, $ban);
		$this->failUnlessDone($result);

		if ($ban) {
			$this->tellApplicant(
				$application,
				NotificationType::APPLICATION_BANNED,
				'ditolak, dan Anda tidak dapat melamar lagi ke proyek ini.',
			);
		} else {
			$this->tellApplicant(
				$application,
				NotificationType::APPLICATION_DECLINED,
				'ditolak. Anda masih boleh melamar lagi.',
			);
		}
		Response::ok(['application' => $this->asArray($this->applications->find($id))], 200, 'Lamaran ditolak.');
	}

	/** DELETE /applications/{id}: the applicant takes a pending application back. */
	public function withdraw(int $id): void
	{
		$uid = Auth::requireUserId();
		$application = $this->findOrFail($id);
		if ($application->userId !== $uid) {
			$this->notFound();
		}

		$withdrawn = $this->applications->withdraw($id, $uid);
		if (!$withdrawn) {
			Response::fail(409, 'Hanya lamaran yang masih menunggu yang bisa ditarik.');
		}
		Response::ok(['application' => $this->asArray($this->applications->find($id))], 200, 'Lamaran ditarik.');
	}

	/** GET /applications/mine */
	public function mine(): void
	{
		$uid = Auth::requireUserId();

		$list = [];
		foreach ($this->applications->listForUser($uid) as $entry) {
			$list[] = $entry->toArray();
		}
		Response::ok(['applications' => $list]);
	}

	// ---------- helpers ----------

	/** Answers with the error that fits when the application did not go through. */
	private function failUnlessApplied(ApplyResult $result): void
	{
		if ($result === ApplyResult::AlreadyMember) {
			Response::fail(409, 'Anda sudah menjadi anggota proyek ini.');
		}
		if ($result === ApplyResult::AlreadyPending) {
			Response::fail(409, 'Lamaran Anda masih menunggu keputusan.');
		}
		if ($result === ApplyResult::Banned) {
			Response::fail(403, 'Anda tidak dapat melamar ke proyek ini.');
		}
		if ($result === ApplyResult::Closed) {
			Response::fail(409, 'Proyek ini sedang tidak menerima lamaran.');
		}
		if ($result === ApplyResult::NotAvailable) {
			Response::fail(404, 'Proyek tidak ditemukan.');
		}
	}

	private function failUnlessDone(DecisionResult $result): void
	{
		if ($result === DecisionResult::AlreadyDecided) {
			Response::fail(409, 'Lamaran ini sudah diputuskan atau ditarik.');
		}
		if ($result === DecisionResult::NotAvailable) {
			Response::fail(409, 'Proyek tidak sedang dipublikasikan.');
		}
	}

	/** Stops unless the user is the owner or a manager of the project. Someone with no role there learns nothing: a 404. */
	private function requireLeader(int $projectId, int $userId): void
	{
		$role = $this->members->roleOf($projectId, $userId);
		if ($role === null) {
			$this->notFound();
		}
		if ($role === ProjectRole::Member) {
			Response::fail(403, 'Hanya pemilik atau manajer proyek yang dapat mengelola lamaran.');
		}
	}

	private function findOrFail(int $id): Application
	{
		$application = $this->applications->find($id);
		if ($application === null) {
			$this->notFound();
		}
		return $application;
	}

	private function notFound(): never
	{
		Response::fail(404, 'Lamaran tidak ditemukan.');
	}

	/** @return array<string,mixed>|null */
	private function asArray(?Application $application): ?array
	{
		if ($application === null) {
			return null;
		}
		return $application->toArray();
	}

	/** The owner and the managers hear about every new application. */
	private function tellLeadersAboutApplication(int $projectId, int $applicantId, string $desiredTitle): void
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
	private function tellApplicant(Application $application, string $type, string $ending): void
	{
		$project = $this->projects->find($application->projectId);
		if ($project === null) {
			return;
		}

		$message = 'Lamaran Anda ke proyek "' . $project->title . '" ' . $ending;
		$this->notifications->add($application->userId, $type, $message, $application->projectId);
	}
}
