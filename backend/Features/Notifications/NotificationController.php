<?php

declare(strict_types=1);

namespace Features\Notifications;

use Core\Auth;
use Core\Response;

/** The notification centre of the logged-in user. */
final class NotificationController
{
	private const DEFAULT_LIMIT = 50;
	private const MAX_LIMIT = 100;

	public function __construct(private NotificationRepository $notifications = new NotificationRepository()) {}

	/** GET /notifications?unread=1&limit=50: newest first, plus how many are still unread. */
	public function index(): void
	{
		$uid = Auth::requireUserId();
		$unreadOnly = ($_GET['unread'] ?? '') === '1';

		$list = [];
		foreach ($this->notifications->listFor($uid, $unreadOnly, $this->limit()) as $notification) {
			$list[] = $notification->toArray();
		}

		Response::ok([
			'notifications' => $list,
			'unread_count' => $this->notifications->unreadCount($uid),
		]);
	}

	/** POST /notifications/{id}/read */
	public function read(int $id): void
	{
		$uid = Auth::requireUserId();

		$found = $this->notifications->markRead($id, $uid);
		if (!$found) {
			Response::fail(404, 'Notifikasi tidak ditemukan.');
		}
		Response::ok(['unread_count' => $this->notifications->unreadCount($uid)]);
	}

	/** POST /notifications/read-all */
	public function readAll(): void
	{
		$uid = Auth::requireUserId();

		$this->notifications->markAllRead($uid);
		Response::ok(['unread_count' => 0]);
	}

	/** The ?limit= value, kept between 1 and MAX_LIMIT. */
	private function limit(): int
	{
		$limit = (int) ($_GET['limit'] ?? self::DEFAULT_LIMIT);
		if ($limit < 1) {
			return 1;
		}
		if ($limit > self::MAX_LIMIT) {
			return self::MAX_LIMIT;
		}
		return $limit;
	}
}
