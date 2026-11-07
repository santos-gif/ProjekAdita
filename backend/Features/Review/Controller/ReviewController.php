<?php

declare(strict_types=1);

namespace Features\Review\Controller;

use Core\Request;
use Core\Response;
use Features\Access\Access;
use Features\Notifications\NotificationType;
use Features\Notifications\Repository\NotificationRepository;
use Features\Projects\Repository\MemberRepository;
use Features\Reports\Repository\ReportRepository;
use Features\Review\Helper\ReviewDecision;
use Features\Review\Helper\ReviewInputReader;
use Features\Review\Helper\ReviewLookup;
use Features\Review\Model\ReviewInput;
use Features\Review\Repository\ReviewRepository;
use Features\User\Model\Role;
use Shared\Helpers\Fail;

/**
 * The review of submitted projects, in two stages:
 *   1. a moderator (or an admin acting as one) approves: pending_moderation -> pending_admin
 *   2. an admin approves: pending_admin -> published
 * Rejecting needs a reason. A moderator may reject at stage 1; an admin may reject at either stage, straight away.
 * An admin can also take a published project down.
 */
final class ReviewController
{
	public function __construct(
		private ReviewRepository $reviews = new ReviewRepository(),
		private MemberRepository $members = new MemberRepository(),
		private NotificationRepository $notifications = new NotificationRepository(),
		private Access $access = new Access(),
		private ReportRepository $reports = new ReportRepository(),
		private ReviewDecision $decisions = new ReviewDecision(),
		private ReviewLookup $lookup = new ReviewLookup(),
	) {}

	// ---------- moderator (moderators and admins) ----------

	/** GET /moderation/projects: submissions waiting for the first stage, the oldest first. */
	public function moderationQueue(): void
	{
		$this->access->requireAccountRole(Role::Moderator, Role::Admin);
		$this->sendQueue('pending_moderation');
	}

	/** GET /moderation/projects/{id}: one submission in full, with its review history. */
	public function moderationShow(int $id): void
	{
		$this->access->requireAccountRole(Role::Moderator, Role::Admin);
		$this->sendDetail($id);
	}

	/** POST /moderation/projects/{id}/review {approve, reason}: stage 1. */
	public function moderationReview(int $id): void
	{
		$reviewer = $this->access->requireAccountRole(Role::Moderator, Role::Admin);
		$in = ReviewInputReader::read();
		$item = $this->lookup->loadForReview($id, $reviewer);

		if ($item->project->status !== 'pending_moderation') {
			Response::fail(409, 'Proyek ini tidak sedang menunggu moderasi.');
		}

		if ($in->approve === true) {
			$this->decisions->passToAdmin($item, $reviewer, $in);
			$this->sendResult($id, 'Proyek diteruskan ke admin.');
		}
		$this->decisions->reject($item, $reviewer, 'moderation', $in->reason);
		$this->sendResult($id, 'Proyek ditolak.');
	}

	/** GET /moderation/history: what I decided. */
	public function moderationHistory(): void
	{
		$reviewer = $this->access->requireAccountRole(Role::Moderator, Role::Admin);

		$list = [];
		foreach ($this->reviews->byReviewer($reviewer->id) as $entry) {
			$list[] = $entry->toArray();
		}
		Response::ok(['reviews' => $list]);
	}

	// ---------- admin ----------

	/** GET /admin/projects?status=pending_admin|pending_moderation: the queue (the final stage by default). */
	public function adminQueue(): void
	{
		$this->access->requireAccountRole(Role::Admin);

		$status = $_GET['status'] ?? 'pending_admin';
		if ($status !== 'pending_admin' && $status !== 'pending_moderation') {
			$status = 'pending_admin';
		}
		$this->sendQueue($status);
	}

	/** GET /admin/projects/{id} */
	public function adminShow(int $id): void
	{
		$this->access->requireAccountRole(Role::Admin);
		$this->sendDetail($id);
	}

	/**
	 * POST /admin/projects/{id}/review {approve, reason}
	 * Approve = publish, and only from stage 2 (approval always passes both stages).
	 * Reject = straight away from either stage.
	 */
	public function adminReview(int $id): void
	{
		$reviewer = $this->access->requireAccountRole(Role::Admin);
		$in = ReviewInputReader::read();
		$item = $this->lookup->loadForReview($id, $reviewer);
		$status = $item->project->status;

		if ($in->approve === false) {
			if ($status !== 'pending_moderation' && $status !== 'pending_admin') {
				Response::fail(409, 'Hanya pengajuan yang sedang ditinjau yang bisa ditolak.');
			}
			$this->decisions->reject($item, $reviewer, 'admin', $in->reason);
			$this->sendResult($id, 'Proyek ditolak.');
		}

		if ($status !== 'pending_admin') {
			Response::fail(409, 'Persetujuan melewati dua tahap: moderator lebih dulu, lalu admin.');
		}
		$this->decisions->publish($item, $reviewer, $in);
		$this->sendResult($id, 'Proyek dipublikasikan.');
	}

	/** POST /admin/projects/{id}/takedown {reason}: a published project goes back to rejected. */
	public function takedown(int $id): void
	{
		$admin = $this->access->requireAccountRole(Role::Admin);

		$body = Request::body();
		$body['approve'] = false; // a takedown is always a rejection, so it always needs a reason
		$in = ReviewInput::fromRequest($body);
		Fail::invalid($in->errors());

		$item = $this->lookup->loadForReview($id, $admin);
		if ($item->project->status !== 'published') {
			Response::fail(409, 'Hanya proyek yang sudah dipublikasikan yang bisa diturunkan.');
		}

		$moved = $this->reviews->transition($id, 'published', 'rejected', $admin->id, 'admin', false, $in->reason);
		if (!$moved) {
			ReviewLookup::alreadyHandled();
		}

		// Every report of this project that was still being handled has now been acted upon.
		$this->reports->markActioned($id, $admin->id);

		$title = $item->project->title;
		$this->notifications->addForUsers(
			$this->members->userIdsOf($id),
			NotificationType::PROJECT_TAKEN_DOWN,
			'Proyek "' . $title . '" diturunkan: ' . $in->reason,
			$id,
		);
		$this->sendResult($id, 'Proyek diturunkan.');
	}

	// ---------- the answers ----------

	private function sendQueue(string $status): never
	{
		$list = [];
		foreach ($this->reviews->queue($status) as $item) {
			$list[] = $item->toArray();
		}
		Response::ok(['projects' => $list]);
	}

	private function sendDetail(int $id): never
	{
		$item = $this->reviews->findForReview($id);
		if ($item === null) {
			Fail::notFound('Proyek');
		}

		$history = [];
		foreach ($this->reviews->historyOf($id) as $entry) {
			$history[] = $entry->toArray();
		}
		Response::ok(['project' => $item->toArray(), 'reviews' => $history]);
	}

	/** The answer after a decision: the project as it is now. */
	private function sendResult(int $id, string $message): never
	{
		$item = $this->reviews->findForReview($id);
		$project = null;
		if ($item !== null) {
			$project = $item->toArray();
		}
		Response::ok(['project' => $project], 200, $message);
	}
}
