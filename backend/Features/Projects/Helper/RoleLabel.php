<?php

declare(strict_types=1);

namespace Features\Projects\Helper;

use Features\Projects\Model\ProjectRole;

/** A project role as it is written in a notification sentence. */
final class RoleLabel
{
	public static function of(ProjectRole $role): string
	{
		if ($role === ProjectRole::Owner) {
			return 'pemilik';
		}
		if ($role === ProjectRole::Manager) {
			return 'manajer';
		}
		return 'anggota';
	}
}
