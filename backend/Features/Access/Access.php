<?php

declare(strict_types=1);

namespace Features\Access;

use Core\Auth;
use Core\Response;
use Features\Projects\Model\ProjectRole;
use Features\Projects\Repository\MemberRepository;
use Features\User\Model\Role;
use Features\User\Model\User;
use Features\User\Repository\UserRepository;
use Shared\Helpers\Fail;

/**
 * Who may call an endpoint. Two kinds of role, kept apart:
 * the account role (user, moderator, admin) and the project role (owner, manager, member) on one project.
 * The session only remembers the user id, so both are read from the database on every call:
 * when a role changes, it applies to the person's very next request.
 */
final class Access
{
	public function __construct(
		private UserRepository $users = new UserRepository(),
		private MemberRepository $members = new MemberRepository(),
	) {}

	/**
	 * The logged-in user, provided their account role is one of $roles.
	 * Nobody logged in is a 401; a logged-in user with another role is a 403.
	 */
	public function requireAccountRole(Role ...$roles): User
	{
		$userId = Auth::requireUserId();

		$user = $this->users->findPublicById($userId);
		if ($user === null) {
			// The account was deleted while its session was still alive.
			Response::fail(401, 'Silakan masuk terlebih dahulu.');
		}
		if (!in_array($user->accountRole, $roles, true)) {
			Response::fail(403, 'Anda tidak memiliki akses.');
		}
		return $user;
	}

	/**
	 * The role the user has on the project, provided it is one of $allowed.
	 * Someone who is not on the project learns nothing (404); someone with the wrong role gets a 403.
	 */
	public function requireProjectRole(int $projectId, int $userId, ProjectRole ...$allowed): ProjectRole
	{
		$projectRole = $this->members->roleOf($projectId, $userId);
		if ($projectRole === null) {
			Fail::notFound('Proyek');
		}
		if (!in_array($projectRole, $allowed, true)) {
			Response::fail(403, 'Anda tidak memiliki wewenang untuk tindakan ini.');
		}
		return $projectRole;
	}
}
