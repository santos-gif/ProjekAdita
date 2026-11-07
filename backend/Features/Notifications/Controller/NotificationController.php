<?php

declare(strict_types=1);

namespace Features\Notifications\Controller;

use Core\Auth;
use Core\Response;
use Features\Notifications\Repository\NotificationRepository;
use Shared\Helpers\Fail;
use Shared\Helpers\Query;

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
		$limit = Query::number('limit', self::DEFAULT_LIMIT, 1, self::MAX_LIMIT);
		foreach ($this->notifications->listFor($uid, $unreadOnly, $limit) as $notification) {
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
			Fail::notFound('Notifikasi');
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
}
