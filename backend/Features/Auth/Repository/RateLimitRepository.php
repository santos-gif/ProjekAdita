<?php

declare(strict_types=1);

namespace Features\Auth\Repository;

use Core\Db;

/**
 * Attempt counters in `rate_limits`, one row per (scope, ip, email). The scope names the action ('login', 'reset');
 * the caller supplies the limit ($max attempts within $window seconds) so each action can have its own.
 */
final class RateLimitRepository
{
	/**
	 * Lets one request at a time through for this key (waits up to 5 s), so parallel requests cannot all pass
	 * lockedFor() before any hit() is counted. MariaDB drops the lock when the connection closes at the end of the request.
	 */
	public function lock(string $key): bool
	{
		$st = Db::pdo()->prepare('SELECT GET_LOCK(?, 5)');
		$st->execute(['rl:' . md5($key)]);
		return (int) $st->fetchColumn() === 1;
	}

	/** Searches for the rows where the rate limiting hasn't expired yet
	 *  but the attempts exceeded the max which means that its the current lock
	 */
	/** @return int|null seconds of lockout left, or null when this visitor may try */
	public function lockedFor(string $scope, string $ip, string $email, int $max_attempts): ?int
	{
		$now = time();
		$st = Db::pdo()->prepare(
			'SELECT exp - ? FROM rate_limits
			WHERE scope = ? AND ip_address = INET6_ATON(?) AND email = ?
			AND attempts >= ? AND exp > ?'
		);
		$st->execute([$now, $scope, $ip, $email, $max_attempts, $now]);
		$left = $st->fetchColumn();
		return $left === false ? null : max(1, (int) $left);
	}

	/** A failed attempt, this inserts a row into rate limits, with a duplicate key update, where attempts update itself
	 * as well as exp, so next time a hit happens with the same keys, it'll run the duplicate key update instead
	 */
	public function hit(string $scope, string $ip, string $email, int $max, int $lockoutSeconds): void
	{
		$pdo = Db::pdo();
		$now = time();
		$exp = $now + $lockoutSeconds;
		$pdo->prepare('DELETE FROM rate_limits WHERE exp <= ?')->execute([$now]);
		$pdo->prepare(
			'INSERT INTO rate_limits (scope, ip_address, email, attempts, exp)
			VALUES (?, INET6_ATON(?), ?, 1, ?)
			ON DUPLICATE KEY UPDATE
				attempts = attempts + 1,
				exp = IF(attempts >= ?, ?, exp)'
		)->execute([$scope, $ip, $email, $exp, $max, $exp]);
	}

	public function clear(string $scope, string $ip, string $email): void
	{
		Db::pdo()->prepare(
			'DELETE FROM rate_limits WHERE scope = ? AND ip_address = INET6_ATON(?) AND email = ?'
		)->execute([$scope, $ip, $email]);
	}
}
