<?php

declare(strict_types=1);

namespace Features\Auth\Model;

/** The public user. toArray() is the only JSON shape; frontend/features/auth/auth.types.js mirrors it. */
final readonly class User
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public Role $role,
    ) {
    }

    /** @return array{id:int,name:string,email:string,role:string} */
    public function toArray(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'email' => $this->email, 'role' => $this->role->value];
    }
}
