<?php

declare(strict_types=1);

namespace Features\Auth\Repository;

use Core\Db;

/** The emailed reset code in `password_resets` (one row per user, code stored hashed). */
final class PasswordResetRepository
{
	private const MAX_ATTEMPTS = 5;
	private const VALID_MINUTES = 10;

	/** True when a code was made for this user in the last $seconds (resend cooldown). */
	public function createdWithin(int $userId, int $seconds): bool
	{
		$st = Db::pdo()->prepare(
			'SELECT 1 FROM password_resets WHERE user_id = ? AND created_at > NOW() - INTERVAL ' . $seconds . ' SECOND'
		);
		$st->execute([$userId]);
		return $st->fetchColumn() !== false;
	}

	/** Stores a new code; an older one for the same user is replaced and its failed tries start over. */
	public function save(int $userId, string $codeHash): void
	{
		Db::pdo()->prepare(
			'INSERT INTO password_resets (user_id, code_hash, expires_at)
			 VALUES (?, ?, NOW() + INTERVAL ' . self::VALID_MINUTES . ' MINUTE)
			 ON DUPLICATE KEY UPDATE code_hash = VALUES(code_hash), attempts = 0,
			   expires_at = VALUES(expires_at), created_at = NOW()'
		)->execute([$userId, $codeHash]);
	}

	/**
	 * Counts one try and returns the stored hash, or null when there is no usable code (none, expired, or
	 * too many tries). The try is counted before the caller compares, so parallel guesses still add up.
	 */
	public function consumeAttempt(int $userId): ?string
	{
		$pdo = Db::pdo();
		$st = $pdo->prepare(
			'UPDATE password_resets SET attempts = attempts + 1
			 WHERE user_id = ? AND expires_at > NOW() AND attempts < ' . self::MAX_ATTEMPTS
		);
		$st->execute([$userId]);
		if ($st->rowCount() !== 1) {
			return null;
		}
		$st = $pdo->prepare('SELECT code_hash FROM password_resets WHERE user_id = ?');
		$st->execute([$userId]);
		$hash = $st->fetchColumn();
		return $hash === false ? null : (string) $hash;
	}

	public function delete(int $userId): void
	{
		Db::pdo()->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([$userId]);
	}
}
