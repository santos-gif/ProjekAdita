<?php

declare(strict_types=1);

namespace Features\Applications\Controller;

use Core\Auth;
use Core\Request;
use Core\Response;
use Features\Access\Access;
use Features\Applications\Helper\ApplicationLookup;
use Features\Applications\Helper\ApplicationNotifier;
use Features\Applications\Helper\ApplicationResults;
use Features\Applications\Model\ApplicationInput;
use Features\Applications\Repository\ApplicationRepository;
use Features\Notifications\NotificationType;
use Features\Projects\Model\ProjectRole;
use Shared\Helpers\Fail;

/**
 * Joining a project. Anyone logged in may apply to a published project that is open; the owner and the managers
 * decide, each on their own. A decline lets the person apply again, a ban does not.
 */
final class ApplicationController
{
	public function __construct(
		private ApplicationRepository $applications = new ApplicationRepository(),
		private Access $access = new Access(),
		private ApplicationLookup $lookup = new ApplicationLookup(),
		private ApplicationNotifier $notifier = new ApplicationNotifier(),
	) {}

	/** POST /projects/{id}/applications {desired_title, message} */
	public function apply(int $projectId): void
	{
		$uid = Auth::requireUserId();

		$in = ApplicationInput::fromRequest(Request::body());
		Fail::invalid($in->errors());

		$result = $this->applications->apply($projectId, $uid, $in->desiredTitle, $in->message);
		ApplicationResults::failUnlessApplied($result);

		$this->notifier->tellLeaders($projectId, $uid, $in->desiredTitle);
		$application = $this->applications->findFor($projectId, $uid);
		Response::ok(['application' => $application?->toArray()], 201, 'Lamaran terkirim.');
	}

	/** GET /projects/{id}/applications?status=pending|all: for the owner and the managers. */
	public function forProject(int $projectId): void
	{
		$uid = Auth::requireUserId();
		$this->access->requireProjectRole($projectId, $uid, ProjectRole::Owner, ProjectRole::Manager);

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
		$application = $this->lookup->findOrFail($id);
		$this->access->requireProjectRole($application->projectId, $uid, ProjectRole::Owner, ProjectRole::Manager);

		$result = $this->applications->accept($id, $uid);
		ApplicationResults::failUnlessDone($result);

		$this->notifier->tellApplicant(
			$application,
			NotificationType::APPLICATION_ACCEPTED,
			'diterima. Anda sekarang anggota proyek.',
		);
		Response::ok(['application' => $this->applications->find($id)?->toArray()], 200, 'Lamaran diterima.');
	}

	/** POST /applications/{id}/reject {ban?}: a plain decline, or a ban from this project. Owner or manager. */
	public function reject(int $id): void
	{
		$uid = Auth::requireUserId();
		$application = $this->lookup->findOrFail($id);
		$this->access->requireProjectRole($application->projectId, $uid, ProjectRole::Owner, ProjectRole::Manager);

		$body = Request::body();
		$ban = ($body['ban'] ?? false) === true;

		$result = $this->applications->decline($id, $uid, $ban);
		ApplicationResults::failUnlessDone($result);

		if ($ban) {
			$this->notifier->tellApplicant(
				$application,
				NotificationType::APPLICATION_BANNED,
				'ditolak, dan Anda tidak dapat melamar lagi ke proyek ini.',
			);
		} else {
			$this->notifier->tellApplicant(
				$application,
				NotificationType::APPLICATION_DECLINED,
				'ditolak. Anda masih boleh melamar lagi.',
			);
		}
		Response::ok(['application' => $this->applications->find($id)?->toArray()], 200, 'Lamaran ditolak.');
	}

	/** DELETE /applications/{id}: the applicant takes a pending application back. */
	public function withdraw(int $id): void
	{
		$uid = Auth::requireUserId();
		$application = $this->lookup->findOrFail($id);
		if ($application->userId !== $uid) {
			Fail::notFound('Lamaran');
		}

		$withdrawn = $this->applications->withdraw($id, $uid);
		if (!$withdrawn) {
			Response::fail(409, 'Hanya lamaran yang masih menunggu yang bisa ditarik.');
		}
		Response::ok(['application' => $this->applications->find($id)?->toArray()], 200, 'Lamaran ditarik.');
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
}
