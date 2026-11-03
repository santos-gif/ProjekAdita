<?php

declare(strict_types=1);

namespace Features\Auth\Model;

/** Second step of a password reset: the emailed code plus the new password. */
final readonly class ResetConfirmInput
{
	public function __construct(
		public string $email,
		public string $code,
		public string $newPassword,
		public string $confirmNewPassword,
	) {}

	/** @param array<string,mixed> $in decoded JSON body */
	public static function fromRequest(array $in): self
	{
		return new self(
			is_string($in['email'] ?? null) ? strtolower(trim($in['email'])) : '',
			is_string($in['code'] ?? null) ? trim($in['code']) : '',
			is_string($in['newPassword'] ?? null) ? $in['newPassword'] : '',
			is_string($in['confirmNewPassword'] ?? null) ? $in['confirmNewPassword'] : '',
		);
	}

	/** @return array<string,string> field => message; empty when valid */
	public function errors(): array
	{
		$errors = [];
		if (strlen($this->email) > 190 || !filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
			$errors['email'] = 'Email tidak valid.';
		}
		if (preg_match('/^\d{6}$/', $this->code) !== 1) {
			$errors['code'] = 'Kode harus 6 digit.';
		}
		// bcrypt only uses the first 72 bytes, hence a byte-length cap.
		if (strlen($this->newPassword) < 8 || strlen($this->newPassword) > 72) {
			$errors['newPassword'] = 'Kata sandi harus 8-72 karakter.';
		}
		if (!hash_equals($this->newPassword, $this->confirmNewPassword)) {
			$errors['confirmNewPassword'] = 'Konfirmasi kata sandi tidak cocok.';
		}
		return $errors;
	}
}
