<?php

declare(strict_types=1);

namespace Features\Review;

use Core\Db;
use Features\Projects\Schema\ProjectSchema;
use Features\Review\Model\ReviewEntry;
use Features\Review\Model\ReviewItem;

/** The review side of `projects` and `project_reviews`: the queues, the history, and the status changes. */
final class ReviewRepository
{
	private const QUEUE_LIMIT = 100;

	/** @return list<ReviewItem> the projects in this status, the one waiting longest first */
	public function queue(string $status): array
	{
		$sql = 'SELECT ' . ProjectSchema::COLUMNS . ', u.name AS owner_name
		        FROM projects p JOIN users u ON u.id = p.owner_id
		        WHERE p.status = ?
		        ORDER BY p.created_at ASC, p.id ASC
		        LIMIT ' . self::QUEUE_LIMIT;
		$st = Db::pdo()->prepare($sql);
		$st->execute([$status]);

		$items = [];
		foreach ($st->fetchAll() as $row) {
			$items[] = new ReviewItem(ProjectSchema::project($row), (string) $row['owner_name']);
		}
		return $items;
	}

	/** Any project that has been submitted. A draft is private to its owner, so it is never found here. */
	public function findForReview(int $id): ?ReviewItem
	{
		$sql = 'SELECT ' . ProjectSchema::COLUMNS . ', u.name AS owner_name
		        FROM projects p JOIN users u ON u.id = p.owner_id
		        WHERE p.id = ? AND p.status <> ?';
		$st = Db::pdo()->prepare($sql);
		$st->execute([$id, 'draft']);

		$row = $st->fetch();
		if ($row === false) {
			return null;
		}
		return new ReviewItem(ProjectSchema::project($row), (string) $row['owner_name']);
	}

	/** @return list<ReviewEntry> every decision on one project, oldest first */
	public function historyOf(int $projectId): array
	{
		$sql = $this->entrySql() . ' WHERE r.project_id = ? ORDER BY r.id ASC';
		$st = Db::pdo()->prepare($sql);
		$st->execute([$projectId]);
		return $this->entries($st->fetchAll());
	}

	/** @return list<ReviewEntry> what one reviewer decided, newest first */
	public function byReviewer(int $reviewerId): array
	{
		$sql = $this->entrySql() . ' WHERE r.reviewer_id = ? ORDER BY r.id DESC LIMIT ' . self::QUEUE_LIMIT;
		$st = Db::pdo()->prepare($sql);
		$st->execute([$reviewerId]);
		return $this->entries($st->fetchAll());
	}

	/**
	 * Moves a project from one status to another and records the decision, both or neither.
	 * The status is part of the UPDATE itself, so when two reviewers act at the same moment only the first one
	 * succeeds; the second finds the project already moved and gets false.
	 *
	 * @return bool false when the project was no longer in $from
	 */
	public function transition(
		int $projectId,
		string $from,
		string $to,
		int $reviewerId,
		string $stage,
		bool $approved,
		?string $reason,
	): bool {
		return Db::transaction(function (\PDO $pdo) use ($projectId, $from, $to, $reviewerId, $stage, $approved, $reason): bool {
			$moved = $this->moveStatus($pdo, $projectId, $from, $to);
			if (!$moved) {
				return false;
			}

			$sql = 'INSERT INTO project_reviews (project_id, reviewer_id, stage, is_approved, reason) VALUES (?, ?, ?, ?, ?)';
			$pdo->prepare($sql)->execute([$projectId, $reviewerId, $stage, (int) $approved, $reason]);
			return true;
		});
	}

	private function moveStatus(\PDO $pdo, int $projectId, string $from, string $to): bool
	{
		$sql = 'UPDATE projects SET status = ? WHERE id = ? AND status = ?';
		if ($to === 'published') {
			$sql = 'UPDATE projects SET status = ?, published_at = NOW() WHERE id = ? AND status = ?';
		}

		$st = $pdo->prepare($sql);
		$st->execute([$to, $projectId, $from]);
		// The status really changes, so a matching row is always counted as changed.
		return $st->rowCount() === 1;
	}

	private function entrySql(): string
	{
		return 'SELECT r.id, r.project_id, p.project_name, r.reviewer_id, u.name AS reviewer_name, r.stage,
		               r.is_approved, r.reason, UNIX_TIMESTAMP(r.created_at) AS created_at
		        FROM project_reviews r
		        JOIN users u ON u.id = r.reviewer_id
		        JOIN projects p ON p.id = r.project_id';
	}

	/**
	 * @param list<array<string,mixed>> $rows
	 * @return list<ReviewEntry>
	 */
	private function entries(array $rows): array
	{
		$entries = [];
		foreach ($rows as $row) {
			$reason = null;
			if ($row['reason'] !== null) {
				$reason = (string) $row['reason'];
			}
			$entries[] = new ReviewEntry(
				(int) $row['id'],
				(int) $row['project_id'],
				(string) $row['project_name'],
				(int) $row['reviewer_id'],
				(string) $row['reviewer_name'],
				(string) $row['stage'],
				(bool) $row['is_approved'],
				$reason,
				(int) $row['created_at'],
			);
		}
		return $entries;
	}
}
