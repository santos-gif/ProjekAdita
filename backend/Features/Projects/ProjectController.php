<?php

declare(strict_types=1);

namespace Features\Projects;

use Core\Request;
use Features\Projects\Model\AddProjectInput;


final class ProjectController
{
	public function addProject()
	{
		$in = AddProjectInput::fromRequest(Request::body());
	}
}
