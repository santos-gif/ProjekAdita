<?php

declare(strict_types=1);

namespace Features\Auth;

use Core\Db;
use Features\Auth\Model\User;
use Features\Auth\Model\UserCredentials;
use Features\Auth\Schema\UserSchema;
use PDOException;

final class UserRepository
{
    /** @return UserCredentials|null returns UserCredentials provided an email or null if no match*/
    public function findByEmail(string $email): ?UserCredentials
    {
        $st = Db::pdo()->prepare('SELECT ' . UserSchema::CREDENTIAL_COLUMNS . ' FROM users WHERE email = ?');
        $st->execute([$email]);
        $row = $st->fetch();
        return $row ? UserSchema::credentials($row) : null;
    }

    /** @return User|null returns User(The public data of the user) provided an id or null if no match */
    public function findPublicById(int $id): ?User
    {
        $st = Db::pdo()->prepare('SELECT ' . UserSchema::PUBLIC_COLUMNS . ' FROM users WHERE id = ?');
        $st->execute([$id]);
        $row = $st->fetch();
        return $row ? UserSchema::user($row) : null;
    }
    /** @return void returns void, updates the password of the user */
    public function updateHash(int $id, string $hash): void
    {
        Db::pdo()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$hash, $id]);
    }

    /** @return int|null new id, or null when the email is already taken */
    public function create(string $name, string $email, string $passwordHash): ?int
    {
        try {
            $st = Db::pdo()->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)');
            $st->execute([$name, $email, $passwordHash]);
        } catch (PDOException $e) {
            if (($e->errorInfo[1] ?? 0) === 1062) {
                return null;
            }
            throw $e;
        }
        return (int) Db::pdo()->lastInsertId();
    }
}
