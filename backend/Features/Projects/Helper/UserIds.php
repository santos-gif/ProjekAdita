<?php

declare(strict_types=1);

namespace Features\Projects\Helper;

/** Picking the user ids out of member rows. */
final class UserIds
{
	/**
	 * @param list<array<string,mixed>> $rows each with a "user_id"
	 * @return list<int>
	 */
	public static function fromRows(array $rows): array
	{
		$ids = [];
		foreach ($rows as $row) {
			$ids[] = (int) $row['user_id'];
		}
		return $ids;
	}
}
