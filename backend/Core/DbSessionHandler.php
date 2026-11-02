<?php

declare(strict_types=1);

namespace Core;

use SessionHandlerInterface;
use SessionUpdateTimestampHandlerInterface;

/**
 * PHP sessions kept in the `sessions` table instead of files.
 * shortcut: only user_id, exp and csrf survive; a new $_SESSION key needs its own column, or it is dropped silently.
 */
final class DbSessionHandler implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface
{
	/** How long a session lasts when nobody logged in to it (Auth sets its own exp at login). */
	private const ANONYMOUS_SECONDS = 86400;

	public function open(string $path, string $name): bool
	{
		return true;
	}

	public function close(): bool
	{
		return true;
	}

	/** PHP wants the whole session as one text; rebuild it as `name|serialized-value` pairs from the columns. */
	public function read(string $id): string
	{
		$st = Db::pdo()->prepare('SELECT user_id, exp, csrf FROM sessions WHERE id = ? AND exp >= ?');
		$st->execute([$id, time()]);
		$row = $st->fetch();
		$out = '';
		foreach ($row ?: [] as $key => $value) {
			if ($value !== null) {
				$out .= $key . '|' . serialize($key === 'csrf' ? (string) $value : (int) $value);
			}
		}
		return $out;
	}

	/** $data is ignored: the three known keys are read straight from $_SESSION, which is still filled at this point. */
	public function write(string $id, string $data): bool
	{
		if ($data === '') {
			return true; // nothing to remember, so no row for a visitor who only held a cookie
		}
		$csrf = $_SESSION['csrf'] ?? null;
		Db::pdo()->prepare(
			'INSERT INTO sessions (id, user_id, exp, csrf) VALUES (?, ?, ?, ?)
			 ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), exp = VALUES(exp), csrf = VALUES(csrf)'
		)->execute([
			$id,
			isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null,
			(int) ($_SESSION['exp'] ?? time() + self::ANONYMOUS_SECONDS),
			is_string($csrf) ? $csrf : null,
		]);
		return true;
	}

	public function destroy(string $id): bool
	{
		Db::pdo()->prepare('DELETE FROM sessions WHERE id = ?')->execute([$id]);
		return true;
	}

	/** PHP calls this now and then; the time limit is each row's own exp, not $max_lifetime. */
	public function gc(int $max_lifetime): int|false
	{
		$st = Db::pdo()->prepare('DELETE FROM sessions WHERE exp < ?');
		$st->execute([time()]);
		return $st->rowCount();
	}

	/** Strict mode: an id is only accepted if its row exists and has not expired. */
	public function validateId(string $id): bool
	{
		$st = Db::pdo()->prepare('SELECT 1 FROM sessions WHERE id = ? AND exp >= ?');
		$st->execute([$id, time()]);
		return $st->fetchColumn() !== false;
	}

	/** Nothing to refresh: expiry is absolute, there is no last-activity column. */
	public function updateTimestamp(string $id, string $data): bool
	{
		return true;
	}
}
