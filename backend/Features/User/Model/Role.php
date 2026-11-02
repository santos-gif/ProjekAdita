<?php

declare(strict_types=1);

namespace Features\User\Model;

enum Role: string
{
	case User = 'user';
	case Moderator = 'moderator';
	case Admin = 'admin';
}
