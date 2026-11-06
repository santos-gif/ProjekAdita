<?php

declare(strict_types=1);

namespace Features\Projects\Model;

/**
 * What the add/edit form sends. The same shape is used for submit, edit and draft; $draft only loosens the rules.
 * Field names are the frontend's (title, selection_method, thumbnail); the table calls the title project_name.
 *
 * The thumbnail key has three meanings, so it is split into three fields:
 *   - a "data:image/...;base64,..." string  => a new image ($thumbnailData)
 *   - null                                  => remove the image ($thumbnailClear)
 *   - the key missing, or any other string  => keep what is stored ($thumbnailUrl holds the echoed string, if any)
 */
final readonly class AddProjectInput
{
	public const SELECTION_METHODS = ['Kurasi Portofolio & CV', 'Wawancara Langsung', 'Tes Teknis', 'First Come First Served'];
	public const MAX_TAGS = 10;

	/** @param list<string> $tags */
	public function __construct(
		public string $title,
		public string $description,
		public string $requirements,
		public string $selectionMethod,
		public array $tags,
		public ?string $thumbnailData,
		public ?string $thumbnailUrl,
		public bool $thumbnailClear,
	) {}

	/** @param array<string,mixed> $in decoded JSON body */
	public static function fromRequest(array $in): self
	{
		$selectionMethod = self::SELECTION_METHODS[0];
		if (is_string($in['selection_method'] ?? null)) {
			$selectionMethod = $in['selection_method'];
		}

		// The thumbnail key means three different things (see above), so it is sorted out here.
		$thumbnailData = null;
		$thumbnailUrl = null;
		$thumbnailClear = false;
		$thumbnail = $in['thumbnail'] ?? null;
		if (array_key_exists('thumbnail', $in) && $thumbnail === null) {
			$thumbnailClear = true;
		} elseif (is_string($thumbnail) && str_starts_with($thumbnail, 'data:')) {
			$thumbnailData = $thumbnail;
		} elseif (is_string($thumbnail)) {
			$thumbnailUrl = $thumbnail;
		}

		return new self(
			self::text($in, 'title'),
			self::text($in, 'description'),
			self::text($in, 'requirements'),
			$selectionMethod,
			self::tags($in),
			$thumbnailData,
			$thumbnailUrl,
			$thumbnailClear,
		);
	}

	/** @return array<string,string> field => message; empty when valid. A draft may be unfinished. */
	public function errors(bool $draft = false): array
	{
		$errors = [];

		$titleLength = mb_strlen($this->title);
		if ($titleLength > 120) {
			$errors['title'] = 'Nama proyek maksimal 120 karakter.';
		} elseif (!$draft && $titleLength < 1) {
			$errors['title'] = 'Nama proyek tidak boleh kosong.';
		}

		$descriptionLength = mb_strlen($this->description);
		if ($descriptionLength > 10000) {
			$errors['description'] = 'Deskripsi maksimal 10.000 karakter.';
		} elseif (!$draft && $descriptionLength < 50) {
			$errors['description'] = 'Deskripsi proyek minimal harus 50 karakter.';
		}

		if (mb_strlen($this->requirements) > 5000) {
			$errors['requirements'] = 'Persyaratan maksimal 5.000 karakter.';
		}
		if (!in_array($this->selectionMethod, self::SELECTION_METHODS, true)) {
			$errors['selection_method'] = 'Metode seleksi tidak dikenal.';
		}
		if (count($this->tags) > self::MAX_TAGS) {
			$errors['tags'] = 'Maksimal ' . self::MAX_TAGS . ' tag.';
		}
		return $errors;
	}

	/** A trimmed string field; anything that is not a string counts as empty. */
	private static function text(array $in, string $key): string
	{
		$value = $in[$key] ?? null;
		if (!is_string($value)) {
			return '';
		}
		return trim($value);
	}

	/** @return list<string> the tag names, trimmed, without blanks and without the same tag twice (case ignored) */
	private static function tags(array $in): array
	{
		$raw = $in['tags'] ?? [];
		if (!is_array($raw)) {
			return [];
		}

		$tags = [];
		$seen = [];
		foreach ($raw as $tag) {
			if (!is_string($tag)) {
				continue;
			}
			$tag = trim($tag);
			if ($tag === '') {
				continue;
			}
			$key = mb_strtolower($tag);
			if (isset($seen[$key])) {
				continue;
			}
			$seen[$key] = true;
			$tags[] = $tag;
		}
		return $tags;
	}
}
