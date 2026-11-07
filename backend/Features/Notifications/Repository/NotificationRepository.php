<?php

declare(strict_types=1);

namespace Features\Notifications\Repository;

use Core\Db;
use Features\Notifications\Helper\NotificationText;
use Features\Notifications\Model\Notification;
use Features\Notifications\Schema\NotificationSchema;
use Features\User\Model\Role;

/**
 * The `notifications` table. Callers add notifications AFTER their own transaction has committed,
 * so nobody is told about something that was rolled back.
 */
final class NotificationRepository
{
	public function add(int $userId, string $type, string $message, ?int $projectId = null): void
	{
		$sql = 'INSERT INTO notifications (user_id, type, project_id, message) VALUES (?, ?, ?, ?)';
		Db::pdo()->prepare($sql)->execute([$userId, $type, $projectId, NotificationText::shorten($message)]);
	}

	/** @param list<int> $userIds */
	public function addForUsers(array $userIds, string $type, string $message, ?int $projectId = null): void
	{
		foreach ($userIds as $userId) {
			$this->add($userId, $type, $message, $projectId);
		}
	}

	/** Tells every user who has this account role (for example every moderator). */
	public function addForRole(Role $role, string $type, string $message, ?int $projectId = null): void
	{
		$sql = 'INSERT INTO notifications (user_id, type, project_id, message)
		        SELECT id, ?, ?, ? FROM users WHERE role = ?';
		Db::pdo()->prepare($sql)->execute([$type, $projectId, NotificationText::shorten($message), $role->value]);
	}

	/** @return list<Notification> newest first */
	public function listFor(int $userId, bool $unreadOnly, int $limit): array
	{
		$sql = 'SELECT ' . NotificationSchema::COLUMNS . ' FROM notifications WHERE user_id = ?';
		if ($unreadOnly) {
			$sql .= ' AND read_at IS NULL';
		}
		// $limit is an int that the controller has already kept between 1 and 100.
		$sql .= ' ORDER BY id DESC LIMIT ' . $limit;

		$st = Db::pdo()->prepare($sql);
		$st->execute([$userId]);

		$list = [];
		foreach ($st->fetchAll() as $row) {
			$list[] = NotificationSchema::notification($row);
		}
		return $list;
	}

	public function unreadCount(int $userId): int
	{
		$st = Db::pdo()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL');
		$st->execute([$userId]);
		return (int) $st->fetchColumn();
	}

	/** @return bool false when the user has no notification with this id */
	public function markRead(int $id, int $userId): bool
	{
		$st = Db::pdo()->prepare('SELECT 1 FROM notifications WHERE id = ? AND user_id = ?');
		$st->execute([$id, $userId]);
		if ($st->fetchColumn() === false) {
			return false;
		}

		$update = 'UPDATE notifications SET read_at = NOW() WHERE id = ? AND user_id = ? AND read_at IS NULL';
		Db::pdo()->prepare($update)->execute([$id, $userId]);
		return true;
	}

	public function markAllRead(int $userId): void
	{
		$update = 'UPDATE notifications SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL';
		Db::pdo()->prepare($update)->execute([$userId]);
	}
}
