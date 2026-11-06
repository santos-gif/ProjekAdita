<?php

declare(strict_types=1);

namespace Features\Admin\Model;

/** What happened when an admin changed someone's account role. */
enum RoleChangeResult
{
	case Done;
	case NotFound;
	case Unchanged;   // they already have that role
	case LastAdmin;   // this would leave the platform without any admin
}
