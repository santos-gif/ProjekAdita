<?php

declare(strict_types=1);

namespace Features\Auth\Model;

enum Role: string
{
    case User = 'user';
    case Admin = 'admin';
}
