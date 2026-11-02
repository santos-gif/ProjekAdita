<?php

declare(strict_types=1);

namespace Core;

use Core\Session;

final class Auth
{
	public static function userId(): ?int
	{
		// Anonymous users gets rejected
		if (session_status() !== PHP_SESSION_ACTIVE && !isset($_COOKIE[session_name()])) {
			return null;
		}
		Session::start();
		if (!isset($_SESSION['user_id'])) {
			return null;
		}
		if (($_SESSION['exp'] ?? 0) < time()) {
			Session::destroy();
			return null;
		}
		return (int) $_SESSION['user_id'];
	}

	public static function login(int $userId, bool $remember): void
	{
		Session::regenerate($remember);
		$_SESSION['user_id'] = $userId;
		$_SESSION['exp'] = time() + ($remember ? Session::REMEMBER_SECONDS : Session::DEFAULT_SECONDS);
	}

	public static function logout(): void
	{
		Session::destroy();
	}
}
