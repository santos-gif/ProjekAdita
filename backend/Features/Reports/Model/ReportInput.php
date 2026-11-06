<?php

declare(strict_types=1);

namespace Features\Reports\Model;

/** What a reporter sends: why they report the project. */
final readonly class ReportInput
{
	private const MIN_REASON = 5;
	private const MAX_REASON = 1000;

	public function __construct(public string $reason) {}

	/** @param array<string,mixed> $in decoded JSON body */
	public static function fromRequest(array $in): self
	{
		$reason = '';
		if (is_string($in['reason'] ?? null)) {
			$reason = trim($in['reason']);
		}
		return new self($reason);
	}

	/** @return array<string,string> field => message; empty when valid */
	public function errors(): array
	{
		$length = mb_strlen($this->reason);
		if ($length < self::MIN_REASON || $length > self::MAX_REASON) {
			return ['reason' => 'Alasan laporan harus 5-1.000 karakter.'];
		}
		return [];
	}
}
