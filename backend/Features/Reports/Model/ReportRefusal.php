<?php

declare(strict_types=1);

namespace Features\Reports\Model;

/** Why a report could not be created. */
enum ReportRefusal
{
	case Duplicate;          // this person's earlier report of the project is still being handled
	case ProjectNotAvailable; // the project is not published (any more)
}
