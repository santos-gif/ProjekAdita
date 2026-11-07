<?php

declare(strict_types=1);

namespace Features\Review\Helper;

use Core\Request;
use Features\Review\Model\ReviewInput;
use Shared\Helpers\Fail;

/** Reading the decision a reviewer sent: approve or reject, and the reason. */
final class ReviewInputReader
{
	public static function read(): ReviewInput
	{
		$in = ReviewInput::fromRequest(Request::body());
		Fail::invalid($in->errors());
		return $in;
	}
}
