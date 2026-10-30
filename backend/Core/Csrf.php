<?php

declare(strict_types=1);

namespace Core;

final class Csrf
{
    public static function token(): string
    {
        Session::start();
        return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    }

    /** Rejects with 419 unless the X-CSRF-Token header matches the session token. */
    public static function guard(): void
    {
        $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!is_string($sent) || $sent === '' || !hash_equals(self::token(), $sent)) {
            Response::fail(419, 'Token CSRF tidak valid. Muat ulang halaman lalu coba lagi.');
        }
    }
}
