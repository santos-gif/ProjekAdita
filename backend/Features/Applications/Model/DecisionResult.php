<?php

declare(strict_types=1);

namespace Features\Applications\Model;

/** What happened when an owner or manager decided on an application. */
enum DecisionResult
{
	case Done;
	case AlreadyDecided;  // it was not pending any more (someone else decided first, or the applicant withdrew)
	case NotAvailable;    // the project is not published any more
}
