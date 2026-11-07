<?php

declare(strict_types=1);

namespace Features\Projects\Helper;

use Core\Response;
use Features\Access\Access;
use Features\Notifications\NotificationType;
use Features\Notifications\Repository\NotificationRepository;
use Features\Projects\Model\Project;
use Features\Projects\Model\ProjectRole;
use Features\Projects\Model\PromotionResult;
use Features\Projects\Repository\PromotionRepository;
use Shared\Helpers\Fail;

/** The owner's decision on a promotion request: checks, the change itself, and telling the people it concerns. */
final class PromotionDecision
{
	public function __construct(
		private PromotionRepository $promotions = new PromotionRepository(),
		private ProjectLookup $projects = new ProjectLookup(),
		private Access $access = new Access(),
		private NotificationRepository $notifications = new NotificationRepository(),
	) {}

	/**
	 * Approves or denies the request on behalf of the project's owner and sends the notifications.
	 * Stops with 404/403/409 when it cannot be decided. The controller sends the success response.
	 * @return Project the project the request belongs to
	 */
	public function decide(int $requestId, int $ownerId, bool $approve): Project
	{
		$request = $this->promotions->find($requestId);
		if ($request === null) {
			Fail::notFound('Permintaan');
		}
		$project = $this->projects->load($request->projectId);
		$this->access->requireProjectRole($request->projectId, $ownerId, ProjectRole::Owner);

		$result = $this->promotions->decide($requestId, $ownerId, $approve);
		if ($result === PromotionResult::AlreadyDecided) {
			Response::fail(409, 'Permintaan ini sudah diputuskan.');
		}
		if ($result === PromotionResult::MemberChanged) {
			Response::fail(409, 'Anggota ini sudah tidak dapat dipromosikan (keluar, dikeluarkan, atau sudah manajer).');
		}

		if ($approve) {
			$this->notifications->add(
				$request->memberId,
				NotificationType::MEMBER_ROLE_CHANGED,
				'Anda dijadikan manajer proyek "' . $project->title . '".',
				$project->id,
			);
			$this->notifications->add(
				$request->requestedBy,
				NotificationType::PROMOTION_DECIDED,
				'Permintaan Anda disetujui: ' . $request->memberName . ' sekarang manajer proyek "' . $project->title . '".',
				$project->id,
			);
			return $project;
		}

		$this->notifications->add(
			$request->requestedBy,
			NotificationType::PROMOTION_DECIDED,
			'Permintaan Anda ditolak pemilik: ' . $request->memberName . ' tetap anggota biasa di proyek "' . $project->title . '".',
			$project->id,
		);
		return $project;
	}
}
