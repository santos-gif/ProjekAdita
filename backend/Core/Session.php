<?php

declare(strict_types=1);

namespace Core;

final class Session
{
    public const REMEMBER_SECONDS = 2592000; // 30 hari
    public const DEFAULT_SECONDS = 43200; // 12 jam

    public static function start(int $lifetime = 0): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        // Server-side data must outlive the longest cookie ("remember").
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
