<?php

declare(strict_types=1);

namespace Features\Tags\Schema;

use Features\Tags\Model\Tag;

/** The `tags` table: which columns a query selects, and how a row becomes a Tag. */
final class TagSchema
{
	public const COLUMNS = 'id, name, category';

	/** @param array<string,mixed> $row */
	public static function tag(array $row): Tag
	{
		return new Tag((int) $row['id'], (string) $row['name'], (string) $row['category']);
	}
}
