<?php

declare(strict_types=1);

namespace Core;

use Core\Env;

final class Session
{
	public const REMEMBER_SECONDS = 2592000; // 30 hari
	public const DEFAULT_SECONDS = 43200; // 12 jam

	public const NAME = 'app_session';

	private static bool $handlerSet = false;

	public static function start(int $lifetime = 0): void
	{
		if (session_status() === PHP_SESSION_ACTIVE) {
			return;
		}
		if (!self::$handlerSet) {
			session_set_save_handler(new DbSessionHandler(), true);
			self::$handlerSet = true;
		}

		// Server-side data must outlive the longest cookie ("remember").
		session_name(self::NAME);
		ini_set('session.gc_maxlifetime', (string) self::REMEMBER_SECONDS);
		ini_set('session.use_strict_mode', '1');
		ini_set('session.use_only_cookies', '1');
		session_set_cookie_params([
			'lifetime' => $lifetime,
			'path' => '/',
			'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
				|| in_array(strtolower(Env::get('APP_COOKIE_SECURE')), ['1', 'true'], true),
			'httponly' => true,
			'samesite' => 'Lax',
		]);
		session_start();
	}

	/** New session id (fixation defence); cookie lifetime can only change while no session is open. */
	public static function regenerate(bool $remember): void
	{
		self::start();
		$id = session_id();
		session_write_close();
		session_id($id);
		self::start($remember ? self::REMEMBER_SECONDS : 0);
		session_regenerate_id(true);
	}

	/** Logs a user out everywhere (password reset): deletes every session row that belongs to them. */
	public static function destroyAllFor(int $userId): void
	{
		Db::pdo()->prepare('DELETE FROM sessions WHERE user_id = ?')->execute([$userId]);
	}

	public static function destroy(): void
	{
		self::start();
		$_SESSION = [];
		session_destroy();
		// Fresh anonymous session (strict mode drops the dead id) so Csrf::token() still works;
		// its Set-Cookie replaces the old cookie.
		self::start();
	}
}
