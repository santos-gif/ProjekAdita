<?php

declare(strict_types=1);

namespace Features\Review\Model;

/** What a reviewer sends: approve (true) or reject (false), and a reason. A rejection must carry a reason. */
final readonly class ReviewInput
{
	private const MIN_REJECT_REASON = 3;
	private const MAX_REASON = 1000;

	/** @param bool|null $approve null when the body did not say true or false */
	public function __construct(
		public ?bool $approve,
		public string $reason,
	) {}

	/** @param array<string,mixed> $in decoded JSON body */
	public static function fromRequest(array $in): self
	{
		$approve = null;
		if (is_bool($in['approve'] ?? null)) {
			$approve = $in['approve'];
		}

		$reason = '';
		if (is_string($in['reason'] ?? null)) {
			$reason = trim($in['reason']);
		}
		return new self($approve, $reason);
	}

	/** @return array<string,string> field => message; empty when valid */
	public function errors(): array
	{
		$errors = [];
		$length = mb_strlen($this->reason);

		if ($this->approve === null) {
			$errors['approve'] = 'Pilih setuju (true) atau tolak (false).';
		}
		if ($this->approve === false && $length < self::MIN_REJECT_REASON) {
			$errors['reason'] = 'Alasan penolakan wajib diisi (minimal 3 karakter).';
		}
		if ($length > self::MAX_REASON) {
			$errors['reason'] = 'Alasan maksimal 1.000 karakter.';
		}
		return $errors;
	}

	/** The reason to store: null when there is none (an approval may come without one). */
	public function reasonOrNull(): ?string
	{
		if ($this->reason === '') {
			return null;
		}
		return $this->reason;
	}
}
