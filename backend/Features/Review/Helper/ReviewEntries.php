<?php

declare(strict_types=1);

namespace Features\Review\Helper;

use Features\Review\Model\ReviewEntry;
use Features\Review\Schema\ReviewSchema;

/** Turning query rows into review entries. */
final class ReviewEntries
{
	/**
	 * @param list<array<string,mixed>> $rows
	 * @return list<ReviewEntry>
	 */
	public static function fromRows(array $rows): array
	{
		$entries = [];
		foreach ($rows as $row) {
			$entries[] = ReviewSchema::entry($row);
		}
		return $entries;
	}
}
