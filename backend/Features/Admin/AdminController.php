<?php

declare(strict_types=1);

namespace Features\Admin;

use Core\Request;
use Core\Response;
use Features\Access\Access;
use Features\Admin\Model\AdminUser;
use Features\Admin\Model\RoleChangeResult;
use Features\Notifications\NotificationRepository;
use Features\Notifications\NotificationType;
use Features\User\Model\Role;

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
		$this->access->requireRole(Role::Admin);

		$q = $this->queryText('q', 100);
		$role = null;
		$wanted = $this->queryText('role', 20);
		if ($wanted === 'user' || $wanted === 'moderator' || $wanted === 'admin') {
			$role = $wanted;
		}
		$page = $this->queryNumber('page', 1, 1, 100000);
		$perPage = $this->queryNumber('per_page', self::DEFAULT_PER_PAGE, 1, self::MAX_PER_PAGE);

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
		$me = $this->access->requireRole(Role::Admin);

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
			Response::fail(404, 'Pengguna tidak ditemukan.');
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
		Response::ok(['user' => $this->asArray($this->admin->findUser($id))], 200, 'Peran diubah.');
	}

	/** GET /admin/stats */
	public function stats(): void
	{
		$this->access->requireRole(Role::Admin);
		Response::ok($this->admin->stats());
	}

	// ---------- helpers ----------

	private function queryText(string $key, int $maxLength): string
	{
		$value = $_GET[$key] ?? '';
		if (!is_string($value)) {
			return '';
		}
		return mb_substr(trim($value), 0, $maxLength);
	}

	/** An integer from the query string, kept between $min and $max; $default when it is missing or not a number. */
	private function queryNumber(string $key, int $default, int $min, int $max): int
	{
		$value = $_GET[$key] ?? null;
		if (!is_string($value) || !ctype_digit($value)) {
			return $default;
		}

		$number = (int) $value;
		if ($number < $min) {
			return $min;
		}
		if ($number > $max) {
			return $max;
		}
		return $number;
	}

	/** @return array<string,mixed>|null */
	private function asArray(?AdminUser $user): ?array
	{
		if ($user === null) {
			return null;
		}
		return $user->toArray();
	}
}
