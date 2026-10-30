<?php

declare(strict_types=1);

namespace Features\Auth;

use Core\Db;
use PDOException;

final class UserRepository
{
    /** @return array<string,mixed>|null full row including password_hash */
    public function findByEmail(string $email): ?array
    {
        $st = Db::pdo()->prepare('SELECT id, name, email, role, password_hash FROM users WHERE email = ?');
        $st->execute([$email]);
        return $st->fetch() ?: null;
    }

    /** @return array<string,mixed>|null public columns only */
    public function findPublicById(int $id): ?array
    {
        $st = Db::pdo()->prepare('SELECT id, name, email, role FROM users WHERE id = ?');
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

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
