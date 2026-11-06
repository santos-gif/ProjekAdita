<?php

declare(strict_types=1);

namespace Features\Applications\Model;

/** What happened when someone applied to a project. */
enum ApplyResult
{
	case Applied;         // a new application, or an earlier one started again
	case AlreadyPending;  // they applied and nobody has decided yet
	case AlreadyMember;   // they are on the project
	case Banned;          // an owner or manager banned them from this project
	case Closed;          // the owner closed the project for new applications
	case NotAvailable;    // there is no such project, or it is not published
}
