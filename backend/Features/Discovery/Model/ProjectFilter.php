<?php

declare(strict_types=1);

namespace Features\Discovery\Model;

use Features\Projects\Model\AddProjectInput;

/**
 * The search and filter of GET /projects, read from the query string. Filters work together (all of them must match).
 *   q=text            title, description or the creator's name
 *   tags=a,b          the project has ALL of these tags (up to 5)
 *   selection_method  one of the four methods
 *   open=1            only projects that accept new applications
 *   study_program=    the creator's study programme
 *   sort=newest|members, page=1, per_page=12 (at most 50)
 */
final readonly class ProjectFilter
{
	public const DEFAULT_PER_PAGE = 12;
	public const MAX_PER_PAGE = 50;
	private const MAX_TAGS = 5;

	/** @param list<string> $tags */
	public function __construct(
		public string $q,
		public array $tags,
		public ?string $selectionMethod,
		public bool $openOnly,
		public ?string $studyProgram,
		public string $sort,
		public int $page,
		public int $perPage,
	) {}

	/** @param array<string,mixed> $query usually $_GET */
	public static function fromQuery(array $query): self
	{
		$selectionMethod = null;
		$wanted = self::text($query, 'selection_method', 60);
		if (in_array($wanted, AddProjectInput::SELECTION_METHODS, true)) {
			$selectionMethod = $wanted;
		}

		$studyProgram = null;
		$program = self::text($query, 'study_program', 120);
		if ($program !== '') {
			$studyProgram = $program;
		}

		$sort = 'newest';
		if (self::text($query, 'sort', 20) === 'members') {
			$sort = 'members';
		}

		return new self(
			self::text($query, 'q', 100),
			self::tags($query),
			$selectionMethod,
			self::text($query, 'open', 5) === '1',
			$studyProgram,
			$sort,
			self::number($query, 'page', 1, 1, 100000),
			self::number($query, 'per_page', self::DEFAULT_PER_PAGE, 1, self::MAX_PER_PAGE),
		);
	}

	public function offset(): int
	{
		return ($this->page - 1) * $this->perPage;
	}

	/** A trimmed, length-limited string; anything else (missing, an array) is empty. */
	private static function text(array $query, string $key, int $maxLength): string
	{
		$value = $query[$key] ?? '';
		if (!is_string($value)) {
			return '';
		}
		return mb_substr(trim($value), 0, $maxLength);
	}

	/** @return list<string> the names in ?tags=a,b, without blanks, at most MAX_TAGS */
	private static function tags(array $query): array
	{
		$tags = [];
		foreach (explode(',', self::text($query, 'tags', 500)) as $name) {
			$name = trim($name);
			if ($name === '') {
				continue;
			}
			$tags[] = $name;
			if (count($tags) === self::MAX_TAGS) {
				break;
			}
		}
		return $tags;
	}

	/** An integer from the query, kept between $min and $max; $default when it is missing or not a number. */
	private static function number(array $query, string $key, int $default, int $min, int $max): int
	{
		$value = $query[$key] ?? null;
		if (!is_string($value) || !ctype_digit($value)) {
			return $default;
		}

		$number = (int) $value;
		if ($number < $min) {
			return $min;
		}
		if ($number > $max) {
			return $max;
		}
		return $number;
	}
}
