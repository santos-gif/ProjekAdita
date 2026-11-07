<?php

declare(strict_types=1);

namespace Features\Projects\Repository;

use Core\Db;
use Features\Projects\Helper\UserIds;
use Features\Projects\Model\Member;
use Features\Projects\Model\ProjectRole;
use Features\Projects\Schema\MemberSchema;

/** The `project_members` table: who is on which project, and as what. */
final class MemberRepository
{
	/** @return ProjectRole|null null when the user is not on the project */
	public function roleOf(int $projectId, int $userId): ?ProjectRole
	{
		$st = Db::pdo()->prepare('SELECT role FROM project_members WHERE project_id = ? AND user_id = ?');
		$st->execute([$projectId, $userId]);

		$role = $st->fetchColumn();
		if ($role === false) {
			return null;
		}
		return ProjectRole::from((string) $role);
	}

	/** @return list<Member> the owner first, then the managers, then the members by join date */
	public function listFor(int $projectId): array
	{
		$sql = 'SELECT ' . MemberSchema::COLUMNS . "
		        FROM project_members m JOIN users u ON u.id = m.user_id
		        WHERE m.project_id = ?
		        ORDER BY FIELD(m.role, 'owner', 'manager', 'member'), m.joined_at ASC, m.user_id ASC";
		$st = Db::pdo()->prepare($sql);
		$st->execute([$projectId]);

		$members = [];
		foreach ($st->fetchAll() as $row) {
			$members[] = MemberSchema::member($row);
		}
		return $members;
	}

	/** @return list<int> everyone on the project, the owner included */
	public function userIdsOf(int $projectId): array
	{
		$st = Db::pdo()->prepare('SELECT user_id FROM project_members WHERE project_id = ?');
		$st->execute([$projectId]);
		return UserIds::fromRows($st->fetchAll());
	}

	/**
	 * @param list<ProjectRole> $roles
	 * @return list<int> the users who have one of these roles on the project (for example the owner and the managers)
	 */
	public function userIdsWithRoles(int $projectId, array $roles): array
	{
		$names = [];
		foreach ($roles as $role) {
			$names[] = $role->value;
		}
		$marks = implode(', ', array_fill(0, count($names), '?'));

		$st = Db::pdo()->prepare('SELECT user_id FROM project_members WHERE project_id = ? AND role IN (' . $marks . ')');
		$st->execute(array_merge([$projectId], $names));
		return UserIds::fromRows($st->fetchAll());
	}

	/**
	 * Changes a role, but only if the person still has the role we expect (so two people acting at once cannot undo each other).
	 * @return bool false when the person is not on the project or no longer has the $from role
	 */
	public function changeRole(int $projectId, int $userId, ProjectRole $from, ProjectRole $to): bool
	{
		$sql = 'UPDATE project_members SET role = ? WHERE project_id = ? AND user_id = ? AND role = ?';
		$st = Db::pdo()->prepare($sql);
		$st->execute([$to->value, $projectId, $userId, $from->value]);
		return $st->rowCount() === 1;
	}

	/** @return bool false when the person is not on the project */
	public function changeTitle(int $projectId, int $userId, string $title): bool
	{
		$sql = 'UPDATE project_members SET title = ? WHERE project_id = ? AND user_id = ?';
		$st = Db::pdo()->prepare($sql);
		$st->execute([$title, $projectId, $userId]);

		// A title that did not change counts as 0 rows, so ask whether the person is there instead.
		return $this->roleOf($projectId, $userId) !== null;
	}

	/**
	 * Removes a person from the project, but only if their role is one of $allowed (the caller decides who may remove whom).
	 * @param list<ProjectRole> $allowed
	 * @return bool false when nobody was removed
	 */
	public function remove(int $projectId, int $userId, array $allowed): bool
	{
		$role = $this->roleOf($projectId, $userId);
		if ($role === null || !in_array($role, $allowed, true)) {
			return false;
		}

		$sql = 'DELETE FROM project_members WHERE project_id = ? AND user_id = ? AND role = ?';
		$st = Db::pdo()->prepare($sql);
		$st->execute([$projectId, $userId, $role->value]);
		return $st->rowCount() === 1;
	}

	/**
	 * The owner hands the project to someone who is already on it; the old owner becomes a manager.
	 * Owner row, new owner row and projects.owner_id change together, so there is always exactly one owner.
	 * @return bool false when $fromOwnerId is not the owner (any more) or $toUserId is not on the project
	 */
	public function transferOwnership(int $projectId, int $fromOwnerId, int $toUserId): bool
	{
		return Db::transaction(function (\PDO $pdo) use ($projectId, $fromOwnerId, $toUserId): bool {
			// Lock both rows, then look at the roles as they are right now.
			$st = $pdo->prepare('SELECT user_id, role, title FROM project_members WHERE project_id = ? AND user_id IN (?, ?) FOR UPDATE');
			$st->execute([$projectId, $fromOwnerId, $toUserId]);

			$roles = [];
			$titles = [];
			foreach ($st->fetchAll() as $row) {
				$roles[(int) $row['user_id']] = (string) $row['role'];
				$titles[(int) $row['user_id']] = (string) $row['title'];
			}

			if (($roles[$fromOwnerId] ?? '') !== 'owner') {
				return false;
			}
			if (!isset($roles[$toUserId])) {
				return false;
			}

			// The two people swap positions too, so nobody is left as a manager whose title says "Owner".
			$change = $pdo->prepare('UPDATE project_members SET role = ?, title = ? WHERE project_id = ? AND user_id = ?');
			$change->execute(['manager', $titles[$toUserId], $projectId, $fromOwnerId]);
			$change->execute(['owner', $titles[$fromOwnerId], $projectId, $toUserId]);
			$pdo->prepare('UPDATE projects SET owner_id = ? WHERE id = ?')->execute([$toUserId, $projectId]);
			return true;
		});
	}

	/** Opens or closes the project for NEW applications. */
	public function setAcceptingApplications(int $projectId, bool $open): void
	{
		$sql = 'UPDATE projects SET accepting_applications = ? WHERE id = ?';
		Db::pdo()->prepare($sql)->execute([(int) $open, $projectId]);
	}
}
