<?php

declare(strict_types=1);

namespace Features\Projects\Helper;

use Features\Projects\Model\AddProjectInput;
use Features\Tags\Repository\TagRepository;
use Shared\Helpers\Fail;

/** Turning the tag names of a submitted form into ids. */
final class ProjectTags
{
	public function __construct(private TagRepository $tags = new TagRepository()) {}

	/** @return list<int> ids of the chosen tags; a name outside the official list is a 422 */
	public function idsFor(AddProjectInput $in): array
	{
		$known = $this->tags->idsByName($in->tags);

		$ids = [];
		$unknown = [];
		foreach ($in->tags as $name) {
			$key = mb_strtolower($name);
			if (!isset($known[$key])) {
				$unknown[] = $name;
				continue;
			}
			$ids[] = $known[$key];
		}

		if ($unknown !== []) {
			$names = implode(', ', $unknown);
			Fail::invalid(['tags' => 'Tag tidak ada dalam daftar resmi: ' . $names . '.']);
		}
		return array_values(array_unique($ids));
	}
}
