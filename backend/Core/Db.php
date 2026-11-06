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

	/**
	 * Runs $work in a transaction: everything it does is saved together, or nothing is when it throws.
	 * $work receives the PDO connection and may return a value, which this method returns.
	 * @template T
	 * @param callable(PDO):T $work
	 * @return T
	 */
	public static function transaction(callable $work): mixed
	{
		$pdo = self::pdo();
		$pdo->beginTransaction();
		try {
			$result = $work($pdo);
			$pdo->commit();
			return $result;
		} catch (\Throwable $e) {
			$pdo->rollBack();
			throw $e;
		}
	}
}
