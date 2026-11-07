<?php

declare(strict_types=1);

namespace Features\Discovery\Schema;

use Features\Applications\Schema\ApplicationSchema;
use Features\Discovery\Model\ProjectView;
use Features\Projects\Model\ProjectRole;
use Features\Projects\Schema\ProjectSchema;
use Shared\Helpers\Nullable;

/** A project as a visitor sees it: the project, its owner's name, and what the visitor is to it (role, application). */
final class ProjectViewSchema
{
	private const MY_PROJECT_ROLE = '(SELECT m.role FROM project_members m WHERE m.project_id = p.id AND m.user_id = ?) AS my_project_role';
	private const MY_APPLICATION = '(SELECT ' . ApplicationSchema::OUTCOME_SQL . ' FROM project_applications a
	                                 WHERE a.project_id = p.id AND a.user_id = ?) AS my_application';

	/**
	 * Query: FROM projects p JOIN users u ON u.id = p.owner_id. It has two "?" for the viewer (their role, then their
	 * application), so the viewer's id is always the first two values of the query's parameters.
	 */
	public const COLUMNS = ProjectSchema::COLUMNS . ', u.name AS owner_name, ' . self::MY_PROJECT_ROLE . ', ' . self::MY_APPLICATION;

	/** @param array<string,mixed> $row */
	public static function view(array $row): ProjectView
	{
		$myProjectRole = null;
		if ($row['my_project_role'] !== null) {
			// ProjectRole::from throws on an unknown value: a corrupt row becomes a generic 500, not a silent default.
			$myProjectRole = ProjectRole::from((string) $row['my_project_role']);
		}

		return new ProjectView(
			ProjectSchema::project($row),
			(string) $row['owner_name'],
			$myProjectRole,
			Nullable::text($row['my_application']),
		);
	}
}
