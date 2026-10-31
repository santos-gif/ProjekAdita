<?php

declare(strict_types=1);

namespace Features\Auth\Model;

final readonly class RegisterInput
{
	public function __construct(
		public string $name,
		public string $email,
		public string $password,
		public string $confirmPassword,
	) {}

	/** @param array<string,mixed> $in decoded JSON body */
	public static function fromRequest(array $in): self
	{
		return new self(
			is_string($in['name'] ?? null) ? trim($in['name']) : '',
			is_string($in['email'] ?? null) ? strtolower(trim($in['email'])) : '',
			is_string($in['password'] ?? null) ? $in['password'] : '',
			is_string($in['confirmPassword'] ?? null) ? $in['confirmPassword'] : '',
		);
	}

	/** @return array<string,string> field => message; empty when valid */
	public function errors(): array
	{
		$errors = [];
		$len_name = mb_strlen($this->name);
		if ($len_name < 5 || $len_name > 80) {
			$errors['name'] = 'Nama harus 5-80 karakter.';
		}
		if (strlen($this->email) > 190 || !filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
			$errors['email'] = 'Email tidak valid.';
		}
		// bcrypt only uses the first 72 bytes, hence a byte-length cap.
		if (strlen($this->password) < 8 || strlen($this->password) > 72) {
			$errors['password'] = 'Kata sandi harus 8-72 karakter.';
		}
		if (!hash_equals($this->password, $this->confirmPassword)) {
			$errors['confirmPassword'] = 'Konfirmasi kata sandi tidak cocok.';
		}
		return $errors;
	}
}
