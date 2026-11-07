<?php

declare(strict_types=1);

namespace Features\Projects\Helper;

use Features\Projects\Model\ProjectRole;
use Shared\Helpers\Fail;

/** Reading the team fields of a request body; a wrong value is a 422. */
final class TeamInput
{
	private const MIN_TITLE = 2;
	private const MAX_TITLE = 60;

	/**
	 * @param array<string,mixed> $body
	 * @return ProjectRole|null the role asked for (manager or member), null when the key is absent
	 */
	public static function role(array $body): ?ProjectRole
	{
		if (!array_key_exists('role', $body)) {
			return null;
		}
		if ($body['role'] === 'manager') {
			return ProjectRole::Manager;
		}
		if ($body['role'] === 'member') {
			return ProjectRole::Member;
		}
		Fail::invalid(['role' => 'Peran harus "manager" atau "member".']);
		return null;
	}

	/**
	 * @param array<string,mixed> $body
	 * @return string|null the new position title, null when the key is absent
	 */
	public static function title(array $body): ?string
	{
		if (!array_key_exists('title', $body)) {
			return null;
		}

		$title = '';
		if (is_string($body['title'])) {
			$title = trim($body['title']);
		}
		$length = mb_strlen($title);
		if ($length < self::MIN_TITLE || $length > self::MAX_TITLE) {
			Fail::invalid(['title' => 'Posisi harus 2-60 karakter.']);
		}
		return $title;
	}
}
