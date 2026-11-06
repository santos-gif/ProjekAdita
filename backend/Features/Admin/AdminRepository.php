<?php

declare(strict_types=1);

namespace Features\Admin;

use Core\Db;
use Features\Admin\Model\AdminUser;
use Features\Admin\Model\RoleChangeResult;
use Features\User\Model\Role;

/** What admins need from the `users` table (list, role changes) and the numbers for the admin dashboard. */
final class AdminRepository
{
	/**
	 * @return array{items: list<AdminUser>, total: int}
	 * @param string|null $role only users with this account role, or null for everyone
	 */
	public function listUsers(string $q, ?string $role, int $limit, int $offset): array
	{
		$where = ['1 = 1'];
		$params = [];
		if ($q !== '') {
			// % and _ typed by the admin are ordinary characters, not wildcards.
			$like = '%' . addcslashes($q, '%_\\') . '%';
			$where[] = '(name LIKE ? OR email LIKE ?)';
			$params[] = $like;
			$params[] = $like;
		}
		if ($role !== null) {
			$where[] = 'role = ?';
			$params[] = $role;
		}
		$whereSql = implode(' AND ', $where);

		$count = Db::pdo()->prepare('SELECT COUNT(*) FROM users WHERE ' . $whereSql);
		$count->execute($params);
		$total = (int) $count->fetchColumn();

		// $limit and $offset are integers made by the controller, so they are safe to put in the text.
		$sql = 'SELECT id, name, email, role, study_program, cohort_year, UNIX_TIMESTAMP(created_at) AS created_at
		        FROM users WHERE ' . $whereSql . ' ORDER BY id ASC LIMIT ' . $limit . ' OFFSET ' . $offset;
		$st = Db::pdo()->prepare($sql);
		$st->execute($params);

		$items = [];
		foreach ($st->fetchAll() as $row) {
			$items[] = $this->toUser($row);
		}
		return ['items' => $items, 'total' => $total];
	}

	public function findUser(int $id): ?AdminUser
	{
		$sql = 'SELECT id, name, email, role, study_program, cohort_year, UNIX_TIMESTAMP(created_at) AS created_at
		        FROM users WHERE id = ?';
		$st = Db::pdo()->prepare($sql);
		$st->execute([$id]);

		$row = $st->fetch();
		if ($row === false) {
			return null;
		}
		return $this->toUser($row);
	}

	/**
	 * Changes an account role. The admins are counted inside the same transaction, so two admins demoting each other
	 * at the same moment cannot leave the platform without an admin.
	 */
	public function changeRole(int $userId, Role $newRole): RoleChangeResult
	{
		return Db::transaction(function (\PDO $pdo) use ($userId, $newRole): RoleChangeResult {
			// Lock every admin row first, always in the same order (by id). Two role changes at the same moment then
			// wait for each other, one after the other, instead of each holding a lock the other one needs (a deadlock).
			$admins = $pdo->query("SELECT id FROM users WHERE role = 'admin' ORDER BY id FOR UPDATE")->fetchAll();

			$st = $pdo->prepare('SELECT role FROM users WHERE id = ? FOR UPDATE');
			$st->execute([$userId]);
			$current = $st->fetchColumn();
			if ($current === false) {
				return RoleChangeResult::NotFound;
			}
			if ($current === $newRole->value) {
				return RoleChangeResult::Unchanged;
			}

			if ($current === 'admin' && count($admins) <= 1) {
				return RoleChangeResult::LastAdmin;
			}

			$pdo->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$newRole->value, $userId]);
			return RoleChangeResult::Done;
		});
	}

	/**
	 * The numbers for the admin overview.
	 * @return array<string,mixed>
	 */
	public function stats(): array
	{
		$pdo = Db::pdo();

		$usersByRole = $this->countBy('SELECT role AS name, COUNT(*) AS total FROM users GROUP BY role', ['user', 'moderator', 'admin']);
		$projectsByStatus = $this->countBy(
			"SELECT status AS name, COUNT(*) AS total FROM projects WHERE status <> 'draft' GROUP BY status",
			['pending_moderation', 'pending_admin', 'published', 'rejected'],
		);
		$reportsByStatus = $this->countBy(
			'SELECT status AS name, COUNT(*) AS total FROM project_reports GROUP BY status',
			['open', 'forwarded', 'dismissed', 'actioned'],
		);

		$pending = $pdo->query("SELECT COUNT(*) FROM project_applications WHERE status = 'pending'")->fetchColumn();

		$programs = [];
		$sql = "SELECT COALESCE(study_program, '') AS program, COUNT(*) AS total FROM users
		        GROUP BY program ORDER BY total DESC, program ASC LIMIT 20";
		foreach ($pdo->query($sql)->fetchAll() as $row) {
			$programs[] = ['study_program' => (string) $row['program'], 'users' => (int) $row['total']];
		}

		return [
			'users_total' => array_sum($usersByRole),
			'users_by_role' => $usersByRole,
			'projects_by_status' => $projectsByStatus,
			'reports_by_status' => $reportsByStatus,
			'applications_pending' => (int) $pending,
			'users_by_study_program' => $programs,
		];
	}

	/**
	 * Runs a "name, total" query and returns name => total, with 0 for every name in $names that has no rows.
	 * @param list<string> $names
	 * @return array<string,int>
	 */
	private function countBy(string $sql, array $names): array
	{
		$counts = [];
		foreach ($names as $name) {
			$counts[$name] = 0;
		}
		foreach (Db::pdo()->query($sql)->fetchAll() as $row) {
			$counts[(string) $row['name']] = (int) $row['total'];
		}
		return $counts;
	}

	/** @param array<string,mixed> $row */
	private function toUser(array $row): AdminUser
	{
		$studyProgram = null;
		if ($row['study_program'] !== null) {
			$studyProgram = (string) $row['study_program'];
		}
		$cohortYear = null;
		if ($row['cohort_year'] !== null) {
			$cohortYear = (int) $row['cohort_year'];
		}

		return new AdminUser(
			(int) $row['id'],
			(string) $row['name'],
			(string) $row['email'],
			(string) $row['role'],
			$studyProgram,
			$cohortYear,
			(int) $row['created_at'],
		);
	}
}
