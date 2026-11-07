<?php

declare(strict_types=1);

namespace Features\Applications\Repository;

use Core\Db;
use Features\Applications\Model\ApplicantEntry;
use Features\Applications\Model\Application;
use Features\Applications\Model\ApplyResult;
use Features\Applications\Model\DecisionResult;
use Features\Applications\Model\MyApplicationEntry;
use Features\Applications\Schema\ApplicationSchema;

/** The `project_applications` table. Every change that depends on the current state is checked inside its own write. */
final class ApplicationRepository
{
	public function find(int $id): ?Application
	{
		$st = Db::pdo()->prepare('SELECT ' . ApplicationSchema::COLUMNS . ' FROM project_applications a WHERE a.id = ?');
		$st->execute([$id]);

		$row = $st->fetch();
		if ($row === false) {
			return null;
		}
		return ApplicationSchema::application($row);
	}

	public function findFor(int $projectId, int $userId): ?Application
	{
		$sql = 'SELECT ' . ApplicationSchema::COLUMNS . ' FROM project_applications a WHERE a.project_id = ? AND a.user_id = ?';
		$st = Db::pdo()->prepare($sql);
		$st->execute([$projectId, $userId]);

		$row = $st->fetch();
		if ($row === false) {
			return null;
		}
		return ApplicationSchema::application($row);
	}

	/**
	 * Someone applies. The project row is locked first, so the owner closing the project (or an admin taking it down)
	 * cannot slip in between the checks and the insert.
	 *
	 * A person has one row per project. Applying again after a decline or a withdrawal starts that same row again;
	 * a banned person cannot apply again.
	 */
	public function apply(int $projectId, int $userId, string $desiredTitle, string $message): ApplyResult
	{
		return Db::transaction(function (\PDO $pdo) use ($projectId, $userId, $desiredTitle, $message): ApplyResult {
			$project = $this->lockProject($pdo, $projectId);
			if ($project === null || $project['status'] !== 'published') {
				return ApplyResult::NotAvailable;
			}

			$member = $pdo->prepare('SELECT 1 FROM project_members WHERE project_id = ? AND user_id = ?');
			$member->execute([$projectId, $userId]);
			if ($member->fetchColumn() !== false) {
				return ApplyResult::AlreadyMember;
			}

			$find = $pdo->prepare('SELECT id, status FROM project_applications WHERE project_id = ? AND user_id = ? FOR UPDATE');
			$find->execute([$projectId, $userId]);
			$existing = $find->fetch();

			if ($existing !== false && $existing['status'] === 'rejected') {
				return ApplyResult::Banned;
			}
			if ($existing !== false && $existing['status'] === 'pending') {
				return ApplyResult::AlreadyPending;
			}
			if (!$project['accepting_applications']) {
				return ApplyResult::Closed;
			}

			if ($existing === false) {
				$insert = 'INSERT INTO project_applications (project_id, user_id, desired_title, message) VALUES (?, ?, ?, ?)';
				$pdo->prepare($insert)->execute([$projectId, $userId, $desiredTitle, $message]);
				return ApplyResult::Applied;
			}

			// withdrawn (declined or taken back), or accepted earlier but no longer a member: start again on the same row.
			$restart = "UPDATE project_applications
			            SET desired_title = ?, message = ?, status = 'pending', created_at = NOW(), decided_at = NULL, decided_by = NULL
			            WHERE id = ?";
			$pdo->prepare($restart)->execute([$desiredTitle, $message, (int) $existing['id']]);
			return ApplyResult::Applied;
		});
	}

	/** An owner or manager accepts: the applicant becomes a member in the same transaction. */
	public function accept(int $applicationId, int $deciderId): DecisionResult
	{
		return Db::transaction(function (\PDO $pdo) use ($applicationId, $deciderId): DecisionResult {
			// Lock the project first and the application second: apply() takes the locks in the same order,
			// so an accept and a re-apply at the same moment wait for each other instead of deadlocking.
			$peek = $pdo->prepare('SELECT project_id FROM project_applications WHERE id = ?');
			$peek->execute([$applicationId]);
			$projectId = $peek->fetchColumn();
			if ($projectId === false) {
				return DecisionResult::AlreadyDecided;
			}

			$project = $this->lockProject($pdo, (int) $projectId);
			if ($project === null || $project['status'] !== 'published') {
				return DecisionResult::NotAvailable;
			}

			$find = 'SELECT project_id, user_id, desired_title, status FROM project_applications WHERE id = ? FOR UPDATE';
			$st = $pdo->prepare($find);
			$st->execute([$applicationId]);
			$application = $st->fetch();
			if ($application === false || $application['status'] !== 'pending') {
				return DecisionResult::AlreadyDecided;
			}

			$accept = "UPDATE project_applications SET status = 'accepted', decided_by = ?, decided_at = NOW() WHERE id = ?";
			$pdo->prepare($accept)->execute([$deciderId, $applicationId]);

			// ON DUPLICATE KEY: someone who is somehow on the project already keeps their role.
			$join = "INSERT INTO project_members (project_id, user_id, role, title) VALUES (?, ?, 'member', ?)
			         ON DUPLICATE KEY UPDATE project_id = project_id";
			$pdo->prepare($join)->execute([(int) $application['project_id'], (int) $application['user_id'], $application['desired_title']]);
			return DecisionResult::Done;
		});
	}

	/**
	 * An owner or manager says no. A plain decline is stored as 'withdrawn' (the person may apply again);
	 * a ban is stored as 'rejected' (the person may not apply to this project again).
	 */
	public function decline(int $applicationId, int $deciderId, bool $ban): DecisionResult
	{
		$status = 'withdrawn';
		if ($ban) {
			$status = 'rejected';
		}

		$sql = "UPDATE project_applications SET status = ?, decided_by = ?, decided_at = NOW() WHERE id = ? AND status = 'pending'";
		$st = Db::pdo()->prepare($sql);
		$st->execute([$status, $deciderId, $applicationId]);

		if ($st->rowCount() !== 1) {
			return DecisionResult::AlreadyDecided;
		}
		return DecisionResult::Done;
	}

	/** The applicant takes their own pending application back. decided_by stays empty: that is how "withdrawn" differs from "declined". */
	public function withdraw(int $applicationId, int $userId): bool
	{
		$sql = "UPDATE project_applications SET status = 'withdrawn', decided_at = NOW() WHERE id = ? AND user_id = ? AND status = 'pending'";
		$st = Db::pdo()->prepare($sql);
		$st->execute([$applicationId, $userId]);
		return $st->rowCount() === 1;
	}

	/** @return list<ApplicantEntry> the project's applications, newest first; the pending ones only, or all of them */
	public function listForProject(int $projectId, bool $pendingOnly): array
	{
		$sql = 'SELECT ' . ApplicationSchema::COLUMNS . ', ' . ApplicationSchema::APPLICANT_COLUMNS . '
		        FROM project_applications a JOIN users u ON u.id = a.user_id
		        WHERE a.project_id = ?';
		if ($pendingOnly) {
			$sql .= " AND a.status = 'pending'";
		}
		$sql .= ' ORDER BY a.created_at DESC, a.id DESC';

		$st = Db::pdo()->prepare($sql);
		$st->execute([$projectId]);

		$list = [];
		foreach ($st->fetchAll() as $row) {
			$list[] = ApplicationSchema::applicantEntry($row);
		}
		return $list;
	}

	/** @return list<MyApplicationEntry> everything I applied for, newest first */
	public function listForUser(int $userId): array
	{
		$sql = 'SELECT ' . ApplicationSchema::COLUMNS . ', ' . ApplicationSchema::PROJECT_COLUMNS . '
		        FROM project_applications a JOIN projects p ON p.id = a.project_id
		        WHERE a.user_id = ?
		        ORDER BY a.created_at DESC, a.id DESC';
		$st = Db::pdo()->prepare($sql);
		$st->execute([$userId]);

		$list = [];
		foreach ($st->fetchAll() as $row) {
			$list[] = ApplicationSchema::myEntry($row);
		}
		return $list;
	}

	/** @return array{status:string,accepting_applications:bool}|null the project's state, with its row locked until the transaction ends */
	private function lockProject(\PDO $pdo, int $projectId): ?array
	{
		$st = $pdo->prepare('SELECT status, accepting_applications FROM projects WHERE id = ? FOR UPDATE');
		$st->execute([$projectId]);

		$row = $st->fetch();
		if ($row === false) {
			return null;
		}
		return [
			'status' => (string) $row['status'],
			'accepting_applications' => (bool) $row['accepting_applications'],
		];
	}
}
