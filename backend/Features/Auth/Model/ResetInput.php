<?php

declare(strict_types=1);

namespace Features\Auth\Model;


final readonly class ResetInput
{
	public function __construct(
		public string $email
	) {}

	/** @param array<string,mixed> $in decoded JSON body */
	public static function fromRequest(array $in)
	{
		return new self(
			is_string($in['email'] ?? null) ? strtolower(trim($in['email'])) : ''
		);
	}
	/** @return array<string,string> field => message; empty when valid */
	public function errors(): array
	{
		$errors = [];
		if (strlen($this->email) > 190 || !filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
			$errors['email'] = 'Email tidak valid.';
		}
		return $errors;
	}
}
