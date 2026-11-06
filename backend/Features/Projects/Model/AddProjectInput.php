<?php

declare(strict_types=1);

namespace Features\Projects\Model;


final readonly class AddProjectInput
{
	public function __construct(
		public string $projectName,
		public string $description
	) {}

	/** @param array<string,mixed> $in decoded JSON body */
	public static function fromRequest(array $in): self
	{
		return new self("", "");
	}
	/** @return array<string,string> field => message; empty when valid */
	public function errors(): array
	{
		$errors = [];
		return $errors;
	}
}
