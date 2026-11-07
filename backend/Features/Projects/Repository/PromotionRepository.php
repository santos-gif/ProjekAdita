<?php

declare(strict_types=1);

namespace Features\Projects\Repository;

use Core\Db;
use Features\Projects\Model\PromotionRequest;
use Features\Projects\Model\PromotionResult;
use Features\Projects\Schema\PromotionSchema;

/** The `project_promotion_requests` table: a manager asks, the owner decides. */
final class PromotionRepository
{
	/** @return int|null the new request's id, or null when a request for this member is already waiting for the owner */
	public function request(int $projectId, int $memberId, int $requestedBy): ?int
	{
		return Db::transaction(function (\PDO $pdo) use ($projectId, $memberId, $requestedBy): ?int {
			// Lock the member's own row first: locking "no request yet" would only be a gap lock, which two managers
			// asking at the same moment would both pass.
			$lock = $pdo->prepare('SELECT user_id FROM project_members WHERE project_id = ? AND user_id = ? FOR UPDATE');
			$lock->execute([$projectId, $memberId]);

			$check = "SELECT 1 FROM project_promotion_requests WHERE project_id = ? AND member_id = ? AND status = 'pending'";
			$st = $pdo->prepare($check);
			$st->execute([$projectId, $memberId]);
			if ($st->fetchColumn() !== false) {
				return null;
			}

			$insert = 'INSERT INTO project_promotion_requests (project_id, member_id, requested_by) VALUES (?, ?, ?)';
			$pdo->prepare($insert)->execute([$projectId, $memberId, $requestedBy]);
			return (int) $pdo->lastInsertId();
		});
	}

	public function find(int $id): ?PromotionRequest
	{
		$st = Db::pdo()->prepare(PromotionSchema::SELECT . ' WHERE r.id = ?');
		$st->execute([$id]);

		$row = $st->fetch();
		if ($row === false) {
			return null;
		}
		return PromotionSchema::request($row);
	}

	/**
	 * @return list<PromotionRequest> the requests waiting for the owner, oldest first. A request whose manager is no
	 *         longer a manager, or whose member is no longer a plain member, is stale and is not listed.
	 */
	public function pendingFor(int $projectId): array
	{
		$sql = PromotionSchema::SELECT . "
		        JOIN project_members rm ON rm.project_id = r.project_id AND rm.user_id = r.requested_by AND rm.role = 'manager'
		        JOIN project_members tm ON tm.project_id = r.project_id AND tm.user_id = r.member_id AND tm.role = 'member'
		        WHERE r.project_id = ? AND r.status = 'pending' ORDER BY r.id ASC";
		$st = Db::pdo()->prepare($sql);
		$st->execute([$projectId]);

		$list = [];
		foreach ($st->fetchAll() as $row) {
			$list[] = PromotionSchema::request($row);
		}
		return $list;
	}

	/**
	 * The owner approves (the member becomes a manager) or denies. Decided in one transaction, and only once.
	 */
	public function decide(int $id, int $ownerId, bool $approve): PromotionResult
	{
		return Db::transaction(function (\PDO $pdo) use ($id, $ownerId, $approve): PromotionResult {
			$find = 'SELECT project_id, member_id, requested_by, status FROM project_promotion_requests WHERE id = ? FOR UPDATE';
			$st = $pdo->prepare($find);
			$st->execute([$id]);
			$request = $st->fetch();
			if ($request === false || $request['status'] !== 'pending') {
				return PromotionResult::AlreadyDecided;
			}

			$result = PromotionResult::Done;
			$newStatus = 'denied';

			if ($approve) {
				$stillManager = $pdo->prepare("SELECT 1 FROM project_members WHERE project_id = ? AND user_id = ? AND role = 'manager'");
				$stillManager->execute([(int) $request['project_id'], (int) $request['requested_by']]);

				$promote = "UPDATE project_members SET role = 'manager' WHERE project_id = ? AND user_id = ? AND role = 'member'";
				$update = $pdo->prepare($promote);

				if ($stillManager->fetchColumn() === false) {
					// The manager who asked is not a manager any more: nobody vouches for this request.
					$result = PromotionResult::MemberChanged;
				} else {
					$update->execute([(int) $request['project_id'], (int) $request['member_id']]);
					if ($update->rowCount() === 1) {
						$newStatus = 'approved';
					} else {
						// The member left, was removed, or is a manager already.
						$result = PromotionResult::MemberChanged;
					}
				}
			}

			$close = 'UPDATE project_promotion_requests SET status = ?, decided_by = ?, decided_at = NOW() WHERE id = ?';
			$pdo->prepare($close)->execute([$newStatus, $ownerId, $id]);
			return $result;
		});
	}
}
