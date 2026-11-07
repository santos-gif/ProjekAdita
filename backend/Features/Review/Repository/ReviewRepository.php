<?php

declare(strict_types=1);

namespace Features\Review\Repository;

use Core\Db;
use Features\Review\Helper\ReviewEntries;
use Features\Review\Model\ReviewEntry;
use Features\Review\Model\ReviewItem;
use Features\Review\Schema\ReviewSchema;

/** The review side of `projects` and `project_reviews`: the queues, the history, and the status changes. */
final class ReviewRepository
{
	private const QUEUE_LIMIT = 100;

	/** @return list<ReviewItem> the projects in this status, the one waiting longest first */
	public function queue(string $status): array
	{
		$sql = 'SELECT ' . ReviewSchema::ITEM_COLUMNS . '
		        FROM projects p JOIN users u ON u.id = p.owner_id
		        WHERE p.status = ?
		        ORDER BY p.created_at ASC, p.id ASC
		        LIMIT ' . self::QUEUE_LIMIT;
		$st = Db::pdo()->prepare($sql);
		$st->execute([$status]);

		$items = [];
		foreach ($st->fetchAll() as $row) {
			$items[] = ReviewSchema::item($row);
		}
		return $items;
	}

	/** Any project that has been submitted. A draft is private to its owner, so it is never found here. */
	public function findForReview(int $id): ?ReviewItem
	{
		$sql = 'SELECT ' . ReviewSchema::ITEM_COLUMNS . '
		        FROM projects p JOIN users u ON u.id = p.owner_id
		        WHERE p.id = ? AND p.status <> ?';
		$st = Db::pdo()->prepare($sql);
		$st->execute([$id, 'draft']);

		$row = $st->fetch();
		if ($row === false) {
			return null;
		}
		return ReviewSchema::item($row);
	}

	/** @return list<ReviewEntry> every decision on one project, oldest first */
	public function historyOf(int $projectId): array
	{
		$sql = ReviewSchema::ENTRY_SELECT . ' WHERE r.project_id = ? ORDER BY r.id ASC';
		$st = Db::pdo()->prepare($sql);
		$st->execute([$projectId]);
		return ReviewEntries::fromRows($st->fetchAll());
	}

	/** @return list<ReviewEntry> what one reviewer decided, newest first */
	public function byReviewer(int $reviewerId): array
	{
		$sql = ReviewSchema::ENTRY_SELECT . ' WHERE r.reviewer_id = ? ORDER BY r.id DESC LIMIT ' . self::QUEUE_LIMIT;
		$st = Db::pdo()->prepare($sql);
		$st->execute([$reviewerId]);
		return ReviewEntries::fromRows($st->fetchAll());
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
}
