<?php

declare(strict_types=1);

namespace Features\User\Model;

/**
 * What a user sends to change their profile. Only the keys that are sent change.
 * An empty optional field ("") clears it. The picture works like a project thumbnail:
 *   "avatar": "data:image/...;base64,..."  a new picture,  "avatar": null  remove it,  missing  keep it.
 */
final readonly class ProfileInput
{
	private const TEXT_FIELDS = ['name', 'study_program', 'phone', 'bio', 'identity_number'];

	/** @param array<string,string|int|null> $changes column => new value; null clears an optional field */
	public function __construct(
		public array $changes,
		public ?string $avatarData,
		public bool $avatarClear,
	) {}

	/** @param array<string,mixed> $in decoded JSON body */
	public static function fromRequest(array $in): self
	{
		$changes = [];
		foreach (self::TEXT_FIELDS as $field) {
			if (!array_key_exists($field, $in)) {
				continue;
			}

			$value = '';
			if (is_string($in[$field])) {
				$value = trim($in[$field]);
			}
			if ($value === '' && $field !== 'name') {
				$changes[$field] = null;
			} else {
				$changes[$field] = $value;
			}
		}

		if (array_key_exists('cohort_year', $in)) {
			$year = $in['cohort_year'];
			if ($year === null) {
				$changes['cohort_year'] = null;
			} elseif (is_int($year)) {
				$changes['cohort_year'] = $year;
			} else {
				$changes['cohort_year'] = -1; // not a number: errors() reports it
			}
		}

		$avatarData = null;
		$avatarClear = false;
		$avatar = $in['avatar'] ?? null;
		if (array_key_exists('avatar', $in) && $avatar === null) {
			$avatarClear = true;
		} elseif (is_string($avatar) && str_starts_with($avatar, 'data:')) {
			$avatarData = $avatar;
		}
		return new self($changes, $avatarData, $avatarClear);
	}

	/** @return array<string,string> field => message; empty when valid */
	public function errors(): array
	{
		$errors = [];

		if (array_key_exists('name', $this->changes)) {
			$length = mb_strlen((string) $this->changes['name']);
			if ($length < 5 || $length > 80) {
				$errors['name'] = 'Nama harus 5-80 karakter.';
			}
		}
		if (mb_strlen((string) ($this->changes['study_program'] ?? '')) > 120) {
			$errors['study_program'] = 'Program studi maksimal 120 karakter.';
		}
		if (mb_strlen((string) ($this->changes['bio'] ?? '')) > 2000) {
			$errors['bio'] = 'Bio maksimal 2.000 karakter.';
		}

		$phone = (string) ($this->changes['phone'] ?? '');
		if (strlen($phone) > 20 || preg_match('/^[0-9+\-\s()]*$/', $phone) !== 1) {
			$errors['phone'] = 'Nomor telepon tidak valid (maksimal 20 karakter: angka, +, -, spasi).';
		}

		$idNumber = (string) ($this->changes['identity_number'] ?? '');
		if (strlen($idNumber) > 30 || preg_match('/^[A-Za-z0-9.\-]*$/', $idNumber) !== 1) {
			$errors['identity_number'] = 'NIM/NIDN tidak valid (maksimal 30 karakter: huruf, angka, titik, strip).';
		}

		if (array_key_exists('cohort_year', $this->changes) && $this->changes['cohort_year'] !== null) {
			$year = (int) $this->changes['cohort_year'];
			if ($year < 2000 || $year > (int) date('Y') + 1) {
				$errors['cohort_year'] = 'Angkatan tidak valid.';
			}
		}

		$nothingToChange = $this->changes === [] && $this->avatarData === null && !$this->avatarClear;
		if ($nothingToChange && $errors === []) {
			$errors['profile'] = 'Tidak ada perubahan yang dikirim.';
		}
		return $errors;
	}
}
