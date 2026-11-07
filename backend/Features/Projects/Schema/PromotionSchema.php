<?php

declare(strict_types=1);

namespace Features\Projects\Schema;

use Features\Projects\Model\PromotionRequest;

/** The `project_promotion_requests` table: what a query selects (aliases r, mu, ru), and how a row becomes a PromotionRequest. */
final class PromotionSchema
{
	private const COLUMNS = 'r.id, r.project_id, r.member_id, mu.name AS member_name, r.requested_by,
	                         ru.name AS requested_by_name, r.status, UNIX_TIMESTAMP(r.created_at) AS created_at';

	private const TABLES = 'FROM project_promotion_requests r
	                        JOIN users mu ON mu.id = r.member_id
	                        JOIN users ru ON ru.id = r.requested_by';

	/** A complete SELECT; add the WHERE and ORDER BY after it. */
	public const SELECT = 'SELECT ' . self::COLUMNS . ' ' . self::TABLES;

	/** @param array<string,mixed> $row */
	public static function request(array $row): PromotionRequest
	{
		return new PromotionRequest(
			(int) $row['id'],
			(int) $row['project_id'],
			(int) $row['member_id'],
			(string) $row['member_name'],
			(int) $row['requested_by'],
			(string) $row['requested_by_name'],
			(string) $row['status'],
			(int) $row['created_at'],
		);
	}
}
