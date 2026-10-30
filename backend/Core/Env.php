<?php

declare(strict_types=1);

namespace Core;

final class Env
{
    /** @var array<string,string>|null */
    private static ?array $file = null;

    public static function get(string $key, string $default = ''): string
    {
        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }
        $value = self::file()[$key] ?? null;
        if ($value === null && in_array($key, ['DB_USER', 'DB_NAME'], true) && self::get('APP_ENV') === 'production') {
            throw new \RuntimeException("Missing required env var {$key} in production.");
        }
        return $value ?? $default;
    }

    /** @return array<string,string> */
    private static function file(): array
    {
        if (self::$file !== null) {
            return self::$file;
        }
        self::$file = [];
        // Which file is loaded is decided by the real environment only, so APP_ENV need not live in the file.
        $path = dirname(__DIR__, 2) . (getenv('APP_ENV') === 'production' ? '/.env.prod' : '/.env.dev');
        $lines = is_readable($path) ? file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : false;
        foreach ($lines ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }
            [$k, $v] = explode('=', $line, 2);
            self::$file[trim($k)] = trim(trim($v), "\"'");
        }
        return self::$file;
    }
}
