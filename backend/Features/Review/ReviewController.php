<?php

declare(strict_types=1);

namespace Features\Review;

use Core\Request;
use Core\Response;
use Features\Access\Access;
use Features\Notifications\NotificationRepository;
use Features\Notifications\NotificationType;
use Features\Projects\MemberRepository;
use Features\Reports\ReportRepository;
use Features\Review\Model\ReviewInput;
use Features\Review\Model\ReviewItem;
use Features\User\Model\Role;
use Features\User\Model\User;

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
	) {}

	// ---------- moderator (moderators and admins) ----------

	/** GET /moderation/projects: submissions waiting for the first stage, the oldest first. */
	public function moderationQueue(): void
	{
		$this->access->requireRole(Role::Moderator, Role::Admin);
		$this->sendQueue('pending_moderation');
	}

	/** GET /moderation/projects/{id}: one submission in full, with its review history. */
	public function moderationShow(int $id): void
	{
		$this->access->requireRole(Role::Moderator, Role::Admin);
		$this->sendDetail($id);
	}

	/** POST /moderation/projects/{id}/review {approve, reason}: stage 1. */
	public function moderationReview(int $id): void
	{
		$reviewer = $this->access->requireRole(Role::Moderator, Role::Admin);
		$in = $this->readInput();
		$item = $this->loadForReview($id, $reviewer);

		if ($item->project->status !== 'pending_moderation') {
			Response::fail(409, 'Proyek ini tidak sedang menunggu moderasi.');
		}

		if ($in->approve === true) {
			$this->passToAdmin($item, $reviewer, $in);
		}
		$this->reject($item, $reviewer, 'moderation', $in->reason);
	}

	/** GET /moderation/history: what I decided. */
	public function moderationHistory(): void
	{
		$reviewer = $this->access->requireRole(Role::Moderator, Role::Admin);

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
		$this->access->requireRole(Role::Admin);

		$status = $_GET['status'] ?? 'pending_admin';
		if ($status !== 'pending_admin' && $status !== 'pending_moderation') {
			$status = 'pending_admin';
		}
		$this->sendQueue($status);
	}

	/** GET /admin/projects/{id} */
	public function adminShow(int $id): void
	{
		$this->access->requireRole(Role::Admin);
		$this->sendDetail($id);
	}

	/**
	 * POST /admin/projects/{id}/review {approve, reason}
	 * Approve = publish, and only from stage 2 (approval always passes both stages).
	 * Reject = straight away from either stage.
	 */
	public function adminReview(int $id): void
	{
		$reviewer = $this->access->requireRole(Role::Admin);
		$in = $this->readInput();
		$item = $this->loadForReview($id, $reviewer);
		$status = $item->project->status;

		if ($in->approve === false) {
			if ($status !== 'pending_moderation' && $status !== 'pending_admin') {
				Response::fail(409, 'Hanya pengajuan yang sedang ditinjau yang bisa ditolak.');
			}
			$this->reject($item, $reviewer, 'admin', $in->reason);
		}

		if ($status !== 'pending_admin') {
			Response::fail(409, 'Persetujuan melewati dua tahap: moderator lebih dulu, lalu admin.');
		}
		$this->publish($item, $reviewer, $in);
	}

	/** POST /admin/projects/{id}/takedown {reason}: a published project goes back to rejected. */
	public function takedown(int $id): void
	{
		$admin = $this->access->requireRole(Role::Admin);

		$body = Request::body();
		$body['approve'] = false; // a takedown is always a rejection, so it always needs a reason
		$in = ReviewInput::fromRequest($body);
		$this->failOnErrors($in->errors());

		$item = $this->loadForReview($id, $admin);
		if ($item->project->status !== 'published') {
			Response::fail(409, 'Hanya proyek yang sudah dipublikasikan yang bisa diturunkan.');
		}

		$moved = $this->reviews->transition($id, 'published', 'rejected', $admin->id, 'admin', false, $in->reason);
		if (!$moved) {
			$this->alreadyHandled();
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

	// ---------- the steps ----------

	/** Stage 1 approved: the project waits for an admin. */
	private function passToAdmin(ReviewItem $item, User $reviewer, ReviewInput $in): never
	{
		$id = $item->project->id;
		$title = $item->project->title;

		$moved = $this->reviews->transition($id, 'pending_moderation', 'pending_admin', $reviewer->id, 'moderation', true, $in->reasonOrNull());
		if (!$moved) {
			$this->alreadyHandled();
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
		$this->sendResult($id, 'Proyek diteruskan ke admin.');
	}

	/** Stage 2 approved: the project is public. */
	private function publish(ReviewItem $item, User $reviewer, ReviewInput $in): never
	{
		$id = $item->project->id;

		$moved = $this->reviews->transition($id, 'pending_admin', 'published', $reviewer->id, 'admin', true, $in->reasonOrNull());
		if (!$moved) {
			$this->alreadyHandled();
		}

		$this->notifications->add(
			$item->project->ownerId,
			NotificationType::PROJECT_PUBLISHED,
			'Proyek "' . $item->project->title . '" telah dipublikasikan.',
			$id,
		);
		$this->sendResult($id, 'Proyek dipublikasikan.');
	}

	/** A rejection at either stage: final, with the reason shown to the owner. */
	private function reject(ReviewItem $item, User $reviewer, string $stage, string $reason): never
	{
		$id = $item->project->id;

		$moved = $this->reviews->transition($id, $item->project->status, 'rejected', $reviewer->id, $stage, false, $reason);
		if (!$moved) {
			$this->alreadyHandled();
		}

		$this->notifications->add(
			$item->project->ownerId,
			NotificationType::PROJECT_REJECTED,
			'Proyek "' . $item->project->title . '" ditolak: ' . $reason,
			$id,
		);
		$this->sendResult($id, 'Proyek ditolak.');
	}

	// ---------- small helpers ----------

	private function readInput(): ReviewInput
	{
		$in = ReviewInput::fromRequest(Request::body());
		$this->failOnErrors($in->errors());
		return $in;
	}

	/** @param array<string,string> $errors */
	private function failOnErrors(array $errors): void
	{
		if ($errors !== []) {
			Response::fail(422, 'Data tidak valid.', $errors);
		}
	}

	/** The project to act on. A missing one (or a draft) is a 404; nobody reviews their own project. */
	private function loadForReview(int $id, User $reviewer): ReviewItem
	{
		$item = $this->reviews->findForReview($id);
		if ($item === null) {
			Response::fail(404, 'Proyek tidak ditemukan.');
		}
		if ($item->project->ownerId === $reviewer->id) {
			Response::fail(403, 'Anda tidak dapat meninjau proyek Anda sendiri.');
		}
		return $item;
	}

	private function alreadyHandled(): never
	{
		Response::fail(409, 'Proyek ini baru saja diproses oleh peninjau lain.');
	}

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
			Response::fail(404, 'Proyek tidak ditemukan.');
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
