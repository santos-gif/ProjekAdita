<?php

declare(strict_types=1);

namespace Features\Tags\Model;

/** One official tag. toArray() is the only JSON shape of a tag. */
final readonly class Tag
{
	public function __construct(
		public int $id,
		public string $name,
		public string $category,
	) {}

	/** @return array{name:string,category:string} */
	public function toArray(): array
	{
		return ['name' => $this->name, 'category' => $this->category];
	}
}
