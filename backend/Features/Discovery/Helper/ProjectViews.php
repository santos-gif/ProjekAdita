<?php

declare(strict_types=1);

namespace Features\Discovery\Helper;

use Features\Discovery\Model\ProjectView;
use Features\Discovery\Schema\ProjectViewSchema;

/** Turning a page of query rows into project views. */
final class ProjectViews
{
	/**
	 * @param list<array<string,mixed>> $rows
	 * @return list<ProjectView>
	 */
	public static function fromRows(array $rows): array
	{
		$views = [];
		foreach ($rows as $row) {
			$views[] = ProjectViewSchema::view($row);
		}
		return $views;
	}
}
