<?php

declare(strict_types=1);

namespace Core;

use PDO;
use Core\Env;

final class Db
{
	private static ?PDO $pdo = null;

	public static function pdo(): PDO
	{
		return self::$pdo ??= new PDO(
			sprintf(
				'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
				Env::get('DB_HOST'),
				Env::get('DB_PORT'),
				Env::get('DB_NAME')
			),
			Env::get('DB_USER'),
			Env::get('DB_PASS'),
			[
				PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
				PDO::ATTR_EMULATE_PREPARES => false,
				PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
			]
		);
	}
}
