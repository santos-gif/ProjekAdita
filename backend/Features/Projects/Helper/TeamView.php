<?php

declare(strict_types=1);

namespace Features\Projects\Helper;

use Features\Projects\Repository\MemberRepository;
use Features\User\Repository\UserRepository;

/** The team as the endpoints show it, and the names they put into notifications. */
final class TeamView
{
	public function __construct(
		private MemberRepository $members = new MemberRepository(),
		private UserRepository $users = new UserRepository(),
	) {}

	/** @return list<array<string,mixed>> */
	public function memberList(int $projectId): array
	{
		$list = [];
		foreach ($this->members->listFor($projectId) as $member) {
			$list[] = $member->toArray();
		}
		return $list;
	}

	/** A person's name for a notification; "Seseorang" when the account is gone. */
	public function nameOf(int $userId): string
	{
		$user = $this->users->findPublicById($userId);
		if ($user === null) {
			return 'Seseorang';
		}
		return $user->name;
	}
}
