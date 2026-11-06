<?php

declare(strict_types=1);

namespace Features\Projects\Model;

/** What someone is inside one project (project_members.role). Not the same thing as their account role. */
enum ProjectRole: string
{
	case Owner = 'owner';
	case Manager = 'manager';
	case Member = 'member';
}
