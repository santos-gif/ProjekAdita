<?php

declare(strict_types=1);

namespace Features\Notifications;

use Core\Db;
use Features\Notifications\Model\Notification;
use Features\User\Model\Role;

/**
 * The `notifications` table. Callers add notifications AFTER their own transaction has committed,
 * so nobody is told about something that was rolled back.
 */
final class NotificationRepository
{
	/** notifications.message is VARCHAR(255). */
	private const MAX_MESSAGE_LENGTH = 255;

	public function add(int $userId, string $type, string $message, ?int $projectId = null): void
	{
		$sql = 'INSERT INTO notifications (user_id, type, project_id, message) VALUES (?, ?, ?, ?)';
		Db::pdo()->prepare($sql)->execute([$userId, $type, $projectId, $this->shorten($message)]);
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
		Db::pdo()->prepare($sql)->execute([$type, $projectId, $this->shorten($message), $role->value]);
	}

	/** @return list<Notification> newest first */
	public function listFor(int $userId, bool $unreadOnly, int $limit): array
	{
		$sql = 'SELECT id, type, project_id, message, read_at IS NOT NULL AS is_read,
		               UNIX_TIMESTAMP(created_at) AS created_at
		        FROM notifications WHERE user_id = ?';
		if ($unreadOnly) {
			$sql .= ' AND read_at IS NULL';
		}
		// $limit is an int that the controller has already kept between 1 and 100.
		$sql .= ' ORDER BY id DESC LIMIT ' . $limit;

		$st = Db::pdo()->prepare($sql);
		$st->execute([$userId]);

		$list = [];
		foreach ($st->fetchAll() as $row) {
			$projectId = null;
			if ($row['project_id'] !== null) {
				$projectId = (int) $row['project_id'];
			}
			$list[] = new Notification(
				(int) $row['id'],
				(string) $row['type'],
				$projectId,
				(string) $row['message'],
				(bool) $row['is_read'],
				(int) $row['created_at'],
			);
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

	/** Cuts a long message so it fits the column; the end becomes an ellipsis. */
	private function shorten(string $message): string
	{
		return mb_strimwidth($message, 0, self::MAX_MESSAGE_LENGTH, '…');
	}
}
