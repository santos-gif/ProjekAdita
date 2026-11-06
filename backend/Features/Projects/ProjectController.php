<?php

declare(strict_types=1);

namespace Features\Projects;

use Core\Request;

final class ProjectController
{
	public function addProject()
	{
		$in = AddProjectInput::fromRequest(Request::body());
	}
}
