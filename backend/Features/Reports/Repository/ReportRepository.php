<?php

declare(strict_types=1);

namespace Features\Reports\Repository;

use Core\Db;
use Features\Reports\Model\Report;
use Features\Reports\Model\ReportRefusal;
use Features\Reports\Schema\ReportSchema;

/** The `project_reports` table. */
final class ReportRepository
{
	private const LIST_LIMIT = 100;

	/** @return int|ReportRefusal the new report's id, or the reason it was refused */
	public function create(int $projectId, int $reporterId, string $reason): int|ReportRefusal
	{
		return Db::transaction(function (\PDO $pdo) use ($projectId, $reporterId, $reason): int|ReportRefusal {
			// Lock the project and look at its status now: an admin taking it down at this very moment must not
			// leave a brand-new open report behind on a project that is already gone.
			$project = $pdo->prepare('SELECT status FROM projects WHERE id = ? FOR UPDATE');
			$project->execute([$projectId]);
			if ($project->fetchColumn() !== 'published') {
				return ReportRefusal::ProjectNotAvailable;
			}

			// Lock the reporter's row too: a lock on "no matching report yet" would only be a gap lock, which two
			// simultaneous reports would both pass.
			$pdo->prepare('SELECT id FROM users WHERE id = ? FOR UPDATE')->execute([$reporterId]);

			$check = "SELECT 1 FROM project_reports
			          WHERE project_id = ? AND reporter_id = ? AND status IN ('open', 'forwarded')";
			$st = $pdo->prepare($check);
			$st->execute([$projectId, $reporterId]);
			if ($st->fetchColumn() !== false) {
				return ReportRefusal::Duplicate;
			}

			$insert = 'INSERT INTO project_reports (project_id, reporter_id, reason) VALUES (?, ?, ?)';
			$pdo->prepare($insert)->execute([$projectId, $reporterId, $reason]);
			return (int) $pdo->lastInsertId();
		});
	}

	public function find(int $id): ?Report
	{
		$st = Db::pdo()->prepare(ReportSchema::SELECT . ' WHERE r.id = ?');
		$st->execute([$id]);

		$row = $st->fetch();
		if ($row === false) {
			return null;
		}
		return ReportSchema::report($row);
	}

	/**
	 * @param string|null $status one status, or null for every status
	 * @return list<Report> the oldest first, so nothing waits forever
	 */
	public function listByStatus(?string $status): array
	{
		$sql = ReportSchema::SELECT;
		$params = [];
		if ($status !== null) {
			$sql .= ' WHERE r.status = ?';
			$params[] = $status;
		}
		$sql .= ' ORDER BY r.created_at ASC, r.id ASC LIMIT ' . self::LIST_LIMIT;

		$st = Db::pdo()->prepare($sql);
		$st->execute($params);

		$list = [];
		foreach ($st->fetchAll() as $row) {
			$list[] = ReportSchema::report($row);
		}
		return $list;
	}

	/**
	 * Closes a report, but only if it is still in one of the $from statuses (two people handling it at once: only one wins).
	 * @param list<string> $from
	 * @return bool false when the report was not in any of those statuses
	 */
	public function resolve(int $id, array $from, string $to, int $handlerId, ?string $note): bool
	{
		$marks = implode(', ', array_fill(0, count($from), '?'));
		$sql = 'UPDATE project_reports SET status = ?, handled_by = ?, note = ?, handled_at = NOW()
		        WHERE id = ? AND status IN (' . $marks . ')';

		$st = Db::pdo()->prepare($sql);
		$st->execute(array_merge([$to, $handlerId, $note, $id], $from));
		return $st->rowCount() === 1;
	}

	/** A project was taken down: every report of it that was still being handled is now acted upon. */
	public function markActioned(int $projectId, int $adminId): void
	{
		$sql = "UPDATE project_reports SET status = 'actioned', handled_by = ?, handled_at = NOW()
		        WHERE project_id = ? AND status IN ('open', 'forwarded')";
		Db::pdo()->prepare($sql)->execute([$adminId, $projectId]);
	}
}
