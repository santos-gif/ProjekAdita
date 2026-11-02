<?php

declare(strict_types=1);

namespace Features\Auth;

use Core\Db;

/** Failed-attempt counters in `rate_limits`, one row per (ip, email). */
final class RateLimitRepository
{
	private const MAX_LOGIN_ATTEMPTS = 3;
	private const WINDOW_SECONDS = 180;

	/** @return int|null seconds of lockout left, or null when this visitor may try */
	public function loginLockedFor(string $ip, string $email): ?int
	{
		$st = Db::pdo()->prepare(
			'SELECT ' . self::WINDOW_SECONDS . ' - TIMESTAMPDIFF(SECOND, started_at, NOW()) FROM rate_limits
			 WHERE ip_address = INET6_ATON(?) AND email = ? AND login_attempts >= ' . self::MAX_LOGIN_ATTEMPTS . '
			 AND started_at > NOW() - INTERVAL ' . self::WINDOW_SECONDS . ' SECOND'
		);
		$st->execute([$ip, $email]);
		$left = $st->fetchColumn();
		return $left === false ? null : max(1, (int) $left);
	}

	/** Counts a wrong password. The 3rd one restarts the clock, so the lockout lasts a full window from that moment. */
	public function recordLoginFailure(string $ip, string $email): void
	{
		$pdo = Db::pdo();
		// Also what keeps the table small: rows whose window has passed are of no use any more.
		$pdo->exec('DELETE FROM rate_limits WHERE started_at <= NOW() - INTERVAL ' . self::WINDOW_SECONDS . ' SECOND');
		// started_at is assigned before login_attempts so that it still sees the old count.
		$pdo->prepare(
			'INSERT INTO rate_limits (ip_address, email, login_attempts) VALUES (INET6_ATON(?), ?, 1)
			 ON DUPLICATE KEY UPDATE
			   started_at = IF(login_attempts + 1 >= ' . self::MAX_LOGIN_ATTEMPTS . ', NOW(), started_at),
			   login_attempts = login_attempts + 1'
		)->execute([$ip, $email]);
	}

	public function clearLogin(string $ip, string $email): void
	{
		Db::pdo()->prepare('DELETE FROM rate_limits WHERE ip_address = INET6_ATON(?) AND email = ?')->execute([$ip, $email]);
	}
}
