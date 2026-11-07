<?php

declare(strict_types=1);

namespace Features\Review\Helper;

use Features\Notifications\NotificationType;
use Features\Notifications\Repository\NotificationRepository;
use Features\Review\Model\ReviewInput;
use Features\Review\Model\ReviewItem;
use Features\Review\Repository\ReviewRepository;
use Features\User\Model\Role;
use Features\User\Model\User;

/**
 * The three decisions a reviewer can make: move the status, then tell the people it concerns.
 * Each one stops with 409 when another reviewer got there first; otherwise it returns, and the controller answers.
 */
final class ReviewDecision
{
	public function __construct(
		private ReviewRepository $reviews = new ReviewRepository(),
		private NotificationRepository $notifications = new NotificationRepository(),
	) {}

	/** Stage 1 approved: the project waits for an admin. */
	public function passToAdmin(ReviewItem $item, User $reviewer, ReviewInput $in): void
	{
		$id = $item->project->id;
		$title = $item->project->title;

		$moved = $this->reviews->transition($id, 'pending_moderation', 'pending_admin', $reviewer->id, 'moderation', true, $in->reasonOrNull());
		if (!$moved) {
			ReviewLookup::alreadyHandled();
		}

		$this->notifications->addForRole(
			Role::Admin,
			NotificationType::PROJECT_MODERATED,
			'Proyek menunggu persetujuan akhir: ' . $title,
			$id,
		);
		$this->notifications->add(
			$item->project->ownerId,
			NotificationType::PROJECT_MODERATED,
			'Proyek "' . $title . '" lolos moderasi dan menunggu persetujuan admin.',
			$id,
		);
	}

	/** Stage 2 approved: the project is public. */
	public function publish(ReviewItem $item, User $reviewer, ReviewInput $in): void
	{
		$id = $item->project->id;

		$moved = $this->reviews->transition($id, 'pending_admin', 'published', $reviewer->id, 'admin', true, $in->reasonOrNull());
		if (!$moved) {
			ReviewLookup::alreadyHandled();
		}

		$this->notifications->add(
			$item->project->ownerId,
			NotificationType::PROJECT_PUBLISHED,
			'Proyek "' . $item->project->title . '" telah dipublikasikan.',
			$id,
		);
	}

	/** A rejection at either stage: final, with the reason shown to the owner. */
	public function reject(ReviewItem $item, User $reviewer, string $stage, string $reason): void
	{
		$id = $item->project->id;

		$moved = $this->reviews->transition($id, $item->project->status, 'rejected', $reviewer->id, $stage, false, $reason);
		if (!$moved) {
			ReviewLookup::alreadyHandled();
		}

		$this->notifications->add(
			$item->project->ownerId,
			NotificationType::PROJECT_REJECTED,
			'Proyek "' . $item->project->title . '" ditolak: ' . $reason,
			$id,
		);
	}
}
