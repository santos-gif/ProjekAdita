<?php

declare(strict_types=1);

namespace Features\Access;

use Core\Auth;
use Core\Response;
use Features\User\Model\Role;
use Features\User\Model\User;
use Features\User\UserRepository;

/**
 * Who may call an endpoint, by account role (user, moderator, admin).
 * The session only remembers the user id, so the role is read from the database on every call:
 * when an admin changes someone's role, it applies to their very next request.
 */
final class Access
{
	public function __construct(private UserRepository $users = new UserRepository()) {}

	/**
	 * The logged-in user, provided their account role is one of $roles.
	 * Nobody logged in is a 401; a logged-in user with another role is a 403.
	 */
	public function requireRole(Role ...$roles): User
	{
		$userId = Auth::requireUserId();

		$user = $this->users->findPublicById($userId);
		if ($user === null) {
			// The account was deleted while its session was still alive.
			Response::fail(401, 'Silakan masuk terlebih dahulu.');
		}
		if (!in_array($user->role, $roles, true)) {
			Response::fail(403, 'Anda tidak memiliki akses.');
		}
		return $user;
	}
}
