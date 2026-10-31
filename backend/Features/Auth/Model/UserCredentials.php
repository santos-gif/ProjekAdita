<?php

declare(strict_types=1);

namespace Features\Auth\Model;

/** A user plus the password hash. Backend only: deliberately has no toArray(), so it cannot reach a response. */
final readonly class UserCredentials
{
    public function __construct(public User $user, public string $passwordHash)
    {
    }
}
