<?php

declare(strict_types=1);

namespace Features\Dashboard\Repository;

use Core\Db;

/** The numbers for the dashboards. Each one is a plain count; what a person may see is decided by DashboardController. */
final class DashboardRepository
{
	/**
	 * What every logged-in person gets: their own projects, applications and things waiting for them.
	 * @return array<string,mixed>
	 */
	public function forEveryone(int $userId): array
	{
		$unread = 'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL';
		$drafts = "SELECT COUNT(*) FROM projects WHERE owner_id = ? AND status = 'draft'";
		$joined = "SELECT COUNT(*) FROM project_members WHERE user_id = ? AND role <> 'owner'";
		$applied = "SELECT COUNT(*) FROM project_applications WHERE user_id = ? AND status = 'pending'";

		return [
			'unread_notifications' => $this->count($unread, [$userId]),
			'my_projects' => $this->myProjectsByStatus($userId),
			'has_draft' => $this->count($drafts, [$userId]) > 0,
			'joined_projects' => $this->count($joined, [$userId]),
			'my_applications_pending' => $this->count($applied, [$userId]),
			'applications_to_review' => $this->applicationsToReview($userId),
			'promotion_requests_to_decide' => $this->promotionRequestsToDecide($userId),
		];
	}

	/** @return array<string,int> */
	public function forModerators(int $userId): array
	{
		return [
			'moderation_queue' => $this->count("SELECT COUNT(*) FROM projects WHERE status = 'pending_moderation'"),
			'reports_open' => $this->count("SELECT COUNT(*) FROM project_reports WHERE status = 'open'"),
			'my_reviews' => $this->count('SELECT COUNT(*) FROM project_reviews WHERE reviewer_id = ?', [$userId]),
		];
	}

	/** @return array<string,int> */
	public function forAdmins(): array
	{
		return [
			'admin_queue' => $this->count("SELECT COUNT(*) FROM projects WHERE status = 'pending_admin'"),
			'reports_forwarded' => $this->count("SELECT COUNT(*) FROM project_reports WHERE status = 'forwarded'"),
			'users_total' => $this->count('SELECT COUNT(*) FROM users'),
		];
	}

	/** @return array<string,int> the person's own (submitted) projects by status, 0 where there are none */
	private function myProjectsByStatus(int $userId): array
	{
		$counts = [];
		foreach (['pending_moderation', 'pending_admin', 'published', 'rejected'] as $status) {
			$counts[$status] = 0;
		}

		$sql = "SELECT status, COUNT(*) AS total FROM projects WHERE owner_id = ? AND status <> 'draft' GROUP BY status";
		$st = Db::pdo()->prepare($sql);
		$st->execute([$userId]);
		foreach ($st->fetchAll() as $row) {
			$counts[(string) $row['status']] = (int) $row['total'];
		}
		return $counts;
	}

	/** Pending applications on the projects where this person is the owner or a manager. */
	private function applicationsToReview(int $userId): int
	{
		$sql = "SELECT COUNT(*) FROM project_applications a
		        JOIN project_members m ON m.project_id = a.project_id AND m.user_id = ? AND m.role IN ('owner', 'manager')
		        WHERE a.status = 'pending'";
		return $this->count($sql, [$userId]);
	}

	/** Promotion requests waiting for this person, as the owner of the project. */
	private function promotionRequestsToDecide(int $userId): int
	{
		$sql = "SELECT COUNT(*) FROM project_promotion_requests r
		        JOIN project_members m ON m.project_id = r.project_id AND m.user_id = ? AND m.role = 'owner'
		        WHERE r.status = 'pending'";
		return $this->count($sql, [$userId]);
	}

	/** @param list<int> $params */
	private function count(string $sql, array $params = []): int
	{
		$st = Db::pdo()->prepare($sql);
		$st->execute($params);
		return (int) $st->fetchColumn();
	}
}
