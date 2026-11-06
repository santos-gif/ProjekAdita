<?php

declare(strict_types=1);

namespace Features\Projects\Model;

/** What happened to a promotion request when the owner decided on it. */
enum PromotionResult
{
	case Done;
	case AlreadyDecided;   // someone decided it first, or it never existed
	case MemberChanged;    // the member left, was removed or is a manager already, or the manager who asked is no longer a manager
}
