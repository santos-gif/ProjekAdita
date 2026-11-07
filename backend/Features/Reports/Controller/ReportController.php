<?php

declare(strict_types=1);

namespace Features\Reports\Controller;

use Core\Auth;
use Core\Request;
use Core\Response;
use Features\Access\Access;
use Features\Notifications\NotificationType;
use Features\Notifications\Repository\NotificationRepository;
use Features\Projects\Repository\ProjectRepository;
use Features\Reports\Helper\ReportClosing;
use Features\Reports\Helper\ReportLookup;
use Features\Reports\Helper\ReportNote;
use Features\Reports\Model\ReportInput;
use Features\Reports\Model\ReportRefusal;
use Features\Reports\Repository\ReportRepository;
use Features\User\Model\Role;
use Shared\Helpers\Fail;

/**
 * Reports of projects. Any logged-in user can report a published project (not their own).
 * The moderators are told and either dismiss the report or forward it to the admins;
 * an admin dismisses it or takes the project down (see ReviewController::takedown).
 * Nobody handles a report about their own project, or a report they filed themselves.
 */
final class ReportController
{
	private const STATUSES = ['open', 'forwarded', 'dismissed', 'actioned'];

	public function __construct(
		private ReportRepository $reports = new ReportRepository(),
		private ProjectRepository $projects = new ProjectRepository(),
		private NotificationRepository $notifications = new NotificationRepository(),
		private Access $access = new Access(),
		private ReportLookup $lookup = new ReportLookup(),
		private ReportClosing $closing = new ReportClosing(),
	) {}

	/** POST /projects/{id}/reports {reason} */
	public function create(int $projectId): void
	{
		$uid = Auth::requireUserId();

		$in = ReportInput::fromRequest(Request::body());
		Fail::invalid($in->errors());

		$project = $this->projects->find($projectId);
		if ($project === null || $project->status !== 'published') {
			Fail::notFound('Proyek');
		}
		if ($project->ownerId === $uid) {
			Response::fail(409, 'Anda tidak dapat melaporkan proyek Anda sendiri.');
		}

		$result = $this->reports->create($projectId, $uid, $in->reason);
		if ($result === ReportRefusal::ProjectNotAvailable) {
			Fail::notFound('Proyek');
		}
		if ($result === ReportRefusal::Duplicate) {
			Response::fail(409, 'Laporan Anda sebelumnya untuk proyek ini masih ditangani.');
		}

		$this->notifications->addForRole(
			Role::Moderator,
			NotificationType::PROJECT_REPORTED,
			'Proyek "' . $project->title . '" dilaporkan: ' . $in->reason,
			$projectId,
		);
		Response::ok(['report' => $this->reports->find($result)?->toArray()], 201, 'Laporan terkirim.');
	}

	/** GET /moderation/reports?status=open|forwarded|dismissed|actioned|all (open by default) */
	public function moderationList(): void
	{
		$this->access->requireAccountRole(Role::Moderator, Role::Admin);
		$this->sendList('open');
	}

	/** POST /moderation/reports/{id}/resolve {action: dismiss|forward, note?}: only for open reports. */
	public function moderationResolve(int $id): void
	{
		$moderator = $this->access->requireAccountRole(Role::Moderator, Role::Admin);
		$report = $this->lookup->findOrFail($id);
		ReportClosing::refuseOwnCase($report, $moderator);

		$body = Request::body();
		$note = ReportNote::read($body);
		$action = $body['action'] ?? '';

		if ($action === 'dismiss') {
			$this->closing->close($id, ['open'], 'dismissed', $moderator->id, $note);
			$this->sendReport($id, 'Laporan ditutup.');
		}

		if ($action === 'forward') {
			$this->closing->close($id, ['open'], 'forwarded', $moderator->id, $note);
			$this->notifications->addForRole(
				Role::Admin,
				NotificationType::REPORT_FORWARDED,
				'Laporan diteruskan: proyek "' . $report->projectTitle . '".',
				$report->projectId,
			);
			$this->sendReport($id, 'Laporan diteruskan ke admin.');
		}

		Response::fail(422, 'Data tidak valid.', ['action' => 'Pilih "dismiss" atau "forward".']);
	}

	/** GET /admin/reports?status=... (forwarded by default) */
	public function adminList(): void
	{
		$this->access->requireAccountRole(Role::Admin);
		$this->sendList('forwarded');
	}

	/** POST /admin/reports/{id}/resolve {action: dismiss, note?}: an admin closes a report without taking the project down. */
	public function adminResolve(int $id): void
	{
		$admin = $this->access->requireAccountRole(Role::Admin);
		$report = $this->lookup->findOrFail($id);
		ReportClosing::refuseOwnCase($report, $admin);

		$body = Request::body();
		$note = ReportNote::read($body);
		if (($body['action'] ?? '') !== 'dismiss') {
			Response::fail(422, 'Data tidak valid.', ['action' => 'Gunakan "dismiss". Untuk menurunkan proyek, pakai takedown.']);
		}

		$this->closing->close($id, ['open', 'forwarded'], 'dismissed', $admin->id, $note);
		$this->sendReport($id, 'Laporan ditutup.');
	}

	// ---------- the answers ----------

	/** The answer after a decision: the report as it is now. */
	private function sendReport(int $id, string $message): never
	{
		Response::ok(['report' => $this->reports->find($id)?->toArray()], 200, $message);
	}

	private function sendList(string $defaultStatus): void
	{
		$status = $_GET['status'] ?? $defaultStatus;
		if ($status === 'all') {
			$status = null;
		} elseif (!in_array($status, self::STATUSES, true)) {
			$status = $defaultStatus;
		}

		$list = [];
		foreach ($this->reports->listByStatus($status) as $report) {
			$list[] = $report->toArray();
		}
		Response::ok(['reports' => $list]);
	}
}
