<?php

declare(strict_types=1);

namespace Features\Dashboard;

use Core\Response;
use Features\Access\Access;
use Features\User\Model\Role;

/** One endpoint, and what it returns depends on the account role: everyone gets "student", moderators and admins get more. */
final class DashboardController
{
	public function __construct(
		private DashboardRepository $dashboard = new DashboardRepository(),
		private Access $access = new Access(),
	) {}

	/** GET /dashboard */
	public function index(): void
	{
		$user = $this->access->requireRole(Role::User, Role::Moderator, Role::Admin);

		$moderator = null;
		if ($user->role === Role::Moderator || $user->role === Role::Admin) {
			$moderator = $this->dashboard->forModerators($user->id);
		}

		$admin = null;
		if ($user->role === Role::Admin) {
			$admin = $this->dashboard->forAdmins();
		}

		Response::ok([
			'user' => $user->toArray(),
			'student' => $this->dashboard->forEveryone($user->id),
			'moderator' => $moderator,
			'admin' => $admin,
		]);
	}
}
