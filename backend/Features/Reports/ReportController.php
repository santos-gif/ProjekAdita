<?php

declare(strict_types=1);

namespace Features\Reports;

use Core\Auth;
use Core\Request;
use Core\Response;
use Features\Access\Access;
use Features\Notifications\NotificationRepository;
use Features\Notifications\NotificationType;
use Features\Projects\ProjectRepository;
use Features\Reports\Model\Report;
use Features\Reports\Model\ReportInput;
use Features\Reports\Model\ReportRefusal;
use Features\User\Model\Role;
use Features\User\Model\User;

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
	) {}

	/** POST /projects/{id}/reports {reason} */
	public function create(int $projectId): void
	{
		$uid = Auth::requireUserId();

		$in = ReportInput::fromRequest(Request::body());
		$errors = $in->errors();
		if ($errors !== []) {
			Response::fail(422, 'Data tidak valid.', $errors);
		}

		$project = $this->projects->find($projectId);
		if ($project === null || $project->status !== 'published') {
			Response::fail(404, 'Proyek tidak ditemukan.');
		}
		if ($project->ownerId === $uid) {
			Response::fail(409, 'Anda tidak dapat melaporkan proyek Anda sendiri.');
		}

		$result = $this->reports->create($projectId, $uid, $in->reason);
		if ($result === ReportRefusal::ProjectNotAvailable) {
			Response::fail(404, 'Proyek tidak ditemukan.');
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
		Response::ok(['report' => $this->asArray($this->reports->find($result))], 201, 'Laporan terkirim.');
	}

	/** GET /moderation/reports?status=open|forwarded|dismissed|actioned|all (open by default) */
	public function moderationList(): void
	{
		$this->access->requireRole(Role::Moderator, Role::Admin);
		$this->sendList('open');
	}

	/** POST /moderation/reports/{id}/resolve {action: dismiss|forward, note?}: only for open reports. */
	public function moderationResolve(int $id): void
	{
		$moderator = $this->access->requireRole(Role::Moderator, Role::Admin);
		$report = $this->findOrFail($id);
		$this->refuseOwnCase($report, $moderator);

		$body = Request::body();
		$note = $this->readNote($body);
		$action = $body['action'] ?? '';

		if ($action === 'dismiss') {
			$this->close($id, ['open'], 'dismissed', $moderator->id, $note);
			$this->sendReport($id, 'Laporan ditutup.');
		}

		if ($action === 'forward') {
			$this->close($id, ['open'], 'forwarded', $moderator->id, $note);
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
		$this->access->requireRole(Role::Admin);
		$this->sendList('forwarded');
	}

	/** POST /admin/reports/{id}/resolve {action: dismiss, note?}: an admin closes a report without taking the project down. */
	public function adminResolve(int $id): void
	{
		$admin = $this->access->requireRole(Role::Admin);
		$report = $this->findOrFail($id);
		$this->refuseOwnCase($report, $admin);

		$body = Request::body();
		$note = $this->readNote($body);
		if (($body['action'] ?? '') !== 'dismiss') {
			Response::fail(422, 'Data tidak valid.', ['action' => 'Gunakan "dismiss". Untuk menurunkan proyek, pakai takedown.']);
		}

		$this->close($id, ['open', 'forwarded'], 'dismissed', $admin->id, $note);
		$this->sendReport($id, 'Laporan ditutup.');
	}

	// ---------- helpers ----------

	/** Nobody judges their own case: not a report about their own project, and not a report they filed. */
	private function refuseOwnCase(Report $report, User $handler): void
	{
		if ($report->projectOwnerId === $handler->id) {
			Response::fail(403, 'Anda tidak dapat menangani laporan tentang proyek Anda sendiri.');
		}
		if ($report->reporterId === $handler->id) {
			Response::fail(403, 'Anda tidak dapat menangani laporan yang Anda buat sendiri.');
		}
	}

	/**
	 * Closes the report, but only if it is still in one of the $from statuses.
	 * When someone else got there first it is a 409; otherwise this method simply returns.
	 * @param list<string> $from
	 */
	private function close(int $id, array $from, string $to, int $handlerId, ?string $note): void
	{
		$closed = $this->reports->resolve($id, $from, $to, $handlerId, $note);
		if (!$closed) {
			Response::fail(409, 'Laporan ini sudah ditangani atau tidak berada pada tahap yang sesuai.');
		}
	}

	/** The answer after a decision: the report as it is now. */
	private function sendReport(int $id, string $message): never
	{
		Response::ok(['report' => $this->asArray($this->reports->find($id))], 200, $message);
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

	private function findOrFail(int $id): Report
	{
		$report = $this->reports->find($id);
		if ($report === null) {
			Response::fail(404, 'Laporan tidak ditemukan.');
		}
		return $report;
	}

	/** @param array<string,mixed> $body */
	private function readNote(array $body): ?string
	{
		if (!is_string($body['note'] ?? null)) {
			return null;
		}
		$note = trim($body['note']);
		if ($note === '') {
			return null;
		}
		return mb_substr($note, 0, 1000);
	}

	/** @return array<string,mixed>|null */
	private function asArray(?Report $report): ?array
	{
		if ($report === null) {
			return null;
		}
		return $report->toArray();
	}
}
