<?php

declare(strict_types=1);

namespace Features\Auth\Model;

final readonly class LoginInput
{
    public function __construct(public string $email, public string $password, public bool $remember)
    {
    }

    /** @param array<string,mixed> $in decoded JSON body */
    public static function fromRequest(array $in): self
    {
        return new self(
            is_string($in['email'] ?? null) ? strtolower(trim($in['email'])) : '',
            is_string($in['password'] ?? null) ? $in['password'] : '',
            ($in['remember'] ?? false) === true,
        );
    }
}
