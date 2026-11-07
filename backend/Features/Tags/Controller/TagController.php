<?php

declare(strict_types=1);

namespace Features\Tags\Controller;

use Core\Response;
use Features\Tags\Repository\TagRepository;

final class TagController
{
	public function __construct(private TagRepository $tags = new TagRepository()) {}

	/** GET /tags: the official list for the tag picker. Public, like the list itself. */
	public function index(): void
	{
		$list = [];
		foreach ($this->tags->all() as $tag) {
			$list[] = $tag->toArray();
		}
		Response::ok(['tags' => $list]);
	}
}
