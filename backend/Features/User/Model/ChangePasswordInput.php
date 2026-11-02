<?php

declare(strict_types=1);

namespace Features\User\Model;

final readonly class ChangePasswordInput
{
	public function __construct(
		public string $currentPassword,
		public string $newPassword,
		public string $confirmNewPassword
	) {}


	public static function fromRequest(array $in)
	{
		return new self(
			is_string($in['currentPassword'] ?? null) ? $in['currentPassword'] : '',
			is_string($in['newPassword'] ?? null) ? $in['newPassword'] : '',
			is_string($in['confirmNewPassword'] ?? null) ? $in['confirmNewPassword'] : ''
		);
	}
	/** @return array<string,string> */
	public function errors(): array
	{
		$errors = [];
		if (strlen($this->newPassword) < 8 || strlen($this->newPassword) > 72) {
			$errors['newPassword'] = 'Kata sandi harus 8-72 karakter.';
		}
		if (!hash_equals($this->newPassword, $this->confirmNewPassword)) {
			$errors['confirmNewPassword'] = 'Konfirmasi kata sandi tidak cocok.';
		}
		if (hash_equals($this->newPassword, $this->currentPassword)) {
			$errors["newPasswordSameAsCurrent"] = "Kata sandi baru tidak boleh sama dengan kata sandi sekarang";
		}
		return $errors;
	}
}
