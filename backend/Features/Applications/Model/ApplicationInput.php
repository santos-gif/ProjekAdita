<?php

declare(strict_types=1);

namespace Features\Applications\Model;

/** What an applicant sends: the role they want on the team and a message to the project's leaders. */
final readonly class ApplicationInput
{
	private const MIN_TITLE = 2;
	private const MAX_TITLE = 60;
	private const MAX_MESSAGE = 1000;

	public function __construct(
		public string $desiredTitle,
		public string $message,
	) {}

	/** @param array<string,mixed> $in decoded JSON body */
	public static function fromRequest(array $in): self
	{
		$title = '';
		if (is_string($in['desired_title'] ?? null)) {
			$title = trim($in['desired_title']);
		}

		$message = '';
		if (is_string($in['message'] ?? null)) {
			$message = trim($in['message']);
		}
		return new self($title, $message);
	}

	/** @return array<string,string> field => message; empty when valid */
	public function errors(): array
	{
		$errors = [];

		$titleLength = mb_strlen($this->desiredTitle);
		if ($titleLength < self::MIN_TITLE || $titleLength > self::MAX_TITLE) {
			$errors['desired_title'] = 'Posisi yang diinginkan harus 2-60 karakter.';
		}
		if (mb_strlen($this->message) > self::MAX_MESSAGE) {
			$errors['message'] = 'Pesan maksimal 1.000 karakter.';
		}
		return $errors;
	}
}
