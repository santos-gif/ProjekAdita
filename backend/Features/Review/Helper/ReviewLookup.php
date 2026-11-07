<?php

declare(strict_types=1);

namespace Features\Review\Helper;

use Core\Response;
use Features\Review\Model\ReviewItem;
use Features\Review\Repository\ReviewRepository;
use Features\User\Model\User;
use Shared\Helpers\Fail;

/** Finding the project a reviewer acts on, and the refusal for a project someone else just handled. */
final class ReviewLookup
{
	public function __construct(private ReviewRepository $reviews = new ReviewRepository()) {}

	/** The project to act on. A missing one (or a draft) is a 404; nobody reviews their own project. */
	public function loadForReview(int $id, User $reviewer): ReviewItem
	{
		$item = $this->reviews->findForReview($id);
		if ($item === null) {
			Fail::notFound('Proyek');
		}
		if ($item->project->ownerId === $reviewer->id) {
			Response::fail(403, 'Anda tidak dapat meninjau proyek Anda sendiri.');
		}
		return $item;
	}

	/** The status moved between our check and our write: another reviewer got there first. */
	public static function alreadyHandled(): never
	{
		Response::fail(409, 'Proyek ini baru saja diproses oleh peninjau lain.');
	}
}
