<?php

declare(strict_types=1);

namespace Features\Admin\Controller;

use Core\Request;
use Core\Response;
use Features\Access\Access;
use Features\Admin\Model\RoleChangeResult;
use Features\Admin\Repository\AdminRepository;
use Features\Notifications\NotificationType;
use Features\Notifications\Repository\NotificationRepository;
use Features\User\Model\Role;
use Shared\Helpers\Fail;
use Shared\Helpers\Query;

/** The admin's tools for people and the overview. (Project review and reports are in their own controllers.) */
final class AdminController
{
	private const DEFAULT_PER_PAGE = 20;
	private const MAX_PER_PAGE = 100;

	public function __construct(
		private AdminRepository $admin = new AdminRepository(),
		private NotificationRepository $notifications = new NotificationRepository(),
		private Access $access = new Access(),
	) {}

	/** GET /admin/users?q=&role=&page=&per_page= */
	public function users(): void
	{
		$this->access->requireAccountRole(Role::Admin);

		$q = Query::text('q', 100);
		$role = null;
		$wanted = Query::text('role', 20);
		if ($wanted === 'user' || $wanted === 'moderator' || $wanted === 'admin') {
			$role = $wanted;
		}
		$page = Query::number('page', 1, 1, 100000);
		$perPage = Query::number('per_page', self::DEFAULT_PER_PAGE, 1, self::MAX_PER_PAGE);

		$result = $this->admin->listUsers($q, $role, $perPage, ($page - 1) * $perPage);

		$list = [];
		foreach ($result['items'] as $user) {
			$list[] = $user->toArray();
		}
		Response::ok(['users' => $list, 'total' => $result['total'], 'page' => $page, 'per_page' => $perPage]);
	}

	/** PATCH /admin/users/{id}/role {role: user|moderator|admin}: never your own role, never the last admin. */
	public function changeRole(int $id): void
	{
		$me = $this->access->requireAccountRole(Role::Admin);

		$body = Request::body();
		$wanted = '';
		if (is_string($body['role'] ?? null)) {
			$wanted = $body['role'];
		}
		$newRole = Role::tryFrom($wanted);
		if ($newRole === null) {
			Response::fail(422, 'Data tidak valid.', ['role' => 'Peran harus "user", "moderator" atau "admin".']);
		}
		if ($id === $me->id) {
			Response::fail(409, 'Anda tidak dapat mengubah peran Anda sendiri.');
		}

		$result = $this->admin->changeRole($id, $newRole);
		if ($result === RoleChangeResult::NotFound) {
			Fail::notFound('Pengguna');
		}
		if ($result === RoleChangeResult::Unchanged) {
			Response::fail(409, 'Pengguna ini sudah memiliki peran tersebut.');
		}
		if ($result === RoleChangeResult::LastAdmin) {
			Response::fail(409, 'Tidak bisa: platform harus punya setidaknya satu admin.');
		}

		$this->notifications->add(
			$id,
			NotificationType::ACCOUNT_ROLE_CHANGED,
			'Peran akun Anda diubah menjadi: ' . $newRole->value . '.',
		);
		Response::ok(['user' => $this->admin->findUser($id)?->toArray()], 200, 'Peran diubah.');
	}

	/** GET /admin/stats */
	public function stats(): void
	{
		$this->access->requireAccountRole(Role::Admin);
		Response::ok($this->admin->stats());
	}

	// ---------- helpers ----------
}
