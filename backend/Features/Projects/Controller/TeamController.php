<?php

declare(strict_types=1);

namespace Features\Projects\Controller;

use Core\Auth;
use Core\Request;
use Core\Response;
use Features\Access\Access;
use Features\Notifications\NotificationType;
use Features\Notifications\Repository\NotificationRepository;
use Features\Projects\Helper\ProjectLookup;
use Features\Projects\Helper\PromotionDecision;
use Features\Projects\Helper\RoleLabel;
use Features\Projects\Helper\TeamInput;
use Features\Projects\Helper\TeamView;
use Features\Projects\Model\ProjectRole;
use Features\Projects\Repository\MemberRepository;
use Features\Projects\Repository\ProjectRepository;
use Features\Projects\Repository\PromotionRepository;
use Shared\Helpers\Fail;

/**
 * The team of a published project: who is on it, their roles, leaving, removing, handing over ownership,
 * closing the project for new applications, and the manager's promotion requests (the owner decides).
 *
 * What each role may do:
 *   owner    everything below
 *   manager  remove plain members, ask the owner to promote a member, step down, leave
 *   member   leave
 */
final class TeamController
{
	public function __construct(
		private ProjectRepository $projects = new ProjectRepository(),
		private MemberRepository $members = new MemberRepository(),
		private PromotionRepository $promotions = new PromotionRepository(),
		private NotificationRepository $notifications = new NotificationRepository(),
		private Access $access = new Access(),
		private ProjectLookup $projectLookup = new ProjectLookup(),
		private TeamView $team = new TeamView(),
		private PromotionDecision $promotionDecision = new PromotionDecision(),
	) {}

	// ---------- looking ----------

	/** GET /projects/{id}/members: for anyone who may see the project (published, or they are on it). */
	public function members(int $id): void
	{
		$uid = Auth::requireUserId();
		$project = $this->projectLookup->load($id);

		$role = $this->members->roleOf($id, $uid);
		if ($project->status !== 'published' && $role === null) {
			Fail::notFound('Proyek');
		}
		Response::ok(['members' => $this->team->memberList($id)]);
	}

	// ---------- the owner manages the team ----------

	/** PATCH /projects/{id}/members/{userId} {role?, title?}: the owner makes someone a manager (or a plain member again) and renames their position. */
	public function changeMember(int $id, int $userId): void
	{
		$uid = Auth::requireUserId();
		$project = $this->projectLookup->load($id);
		$this->access->requireProjectRole($id, $uid, ProjectRole::Owner);
		ProjectLookup::requirePublished($project);

		$targetRole = $this->members->roleOf($id, $userId);
		if ($targetRole === null) {
			Fail::notFound('Anggota');
		}
		if ($targetRole === ProjectRole::Owner) {
			Response::fail(409, 'Peran pemilik diubah lewat pengalihan kepemilikan.');
		}

		$body = Request::body();
		$newRole = TeamInput::role($body);
		$newTitle = TeamInput::title($body);
		if ($newRole === null && $newTitle === null) {
			Response::fail(422, 'Data tidak valid.', ['role' => 'Kirim role dan/atau title.']);
		}

		if ($newRole !== null && $newRole !== $targetRole) {
			$changed = $this->members->changeRole($id, $userId, $targetRole, $newRole);
			if (!$changed) {
				Response::fail(409, 'Peran anggota ini baru saja berubah. Muat ulang lalu coba lagi.');
			}
			$this->notifications->add(
				$userId,
				NotificationType::MEMBER_ROLE_CHANGED,
				'Peran Anda di proyek "' . $project->title . '" sekarang: ' . RoleLabel::of($newRole) . '.',
				$id,
			);
		}
		if ($newTitle !== null) {
			$this->members->changeTitle($id, $userId, $newTitle);
		}
		Response::ok(['members' => $this->team->memberList($id)], 200, 'Anggota diperbarui.');
	}

	/** POST /projects/{id}/transfer-ownership {user_id}: the new owner must already be on the project; the old owner becomes a manager. */
	public function transfer(int $id): void
	{
		$uid = Auth::requireUserId();
		$project = $this->projectLookup->load($id);
		$this->access->requireProjectRole($id, $uid, ProjectRole::Owner);
		ProjectLookup::requirePublished($project);

		$body = Request::body();
		$newOwnerId = 0;
		if (is_int($body['user_id'] ?? null)) {
			$newOwnerId = $body['user_id'];
		}
		if ($newOwnerId < 1) {
			Response::fail(422, 'Data tidak valid.', ['user_id' => 'Pilih anggota yang akan menjadi pemilik.']);
		}
		if ($newOwnerId === $uid) {
			Response::fail(409, 'Anda sudah menjadi pemilik proyek ini.');
		}

		$done = $this->members->transferOwnership($id, $uid, $newOwnerId);
		if (!$done) {
			Response::fail(409, 'Pemilik baru harus anggota proyek ini.');
		}

		$this->notifications->add(
			$newOwnerId,
			NotificationType::OWNERSHIP_TRANSFERRED,
			'Anda sekarang pemilik proyek "' . $project->title . '".',
			$id,
		);
		$this->notifications->add(
			$uid,
			NotificationType::OWNERSHIP_TRANSFERRED,
			'Kepemilikan proyek "' . $project->title . '" dialihkan. Anda sekarang manajer.',
			$id,
		);
		Response::ok(['members' => $this->team->memberList($id)], 200, 'Kepemilikan dialihkan.');
	}

	/** PATCH /projects/{id}/applications-open {open}: the owner opens or closes the project for NEW applications. */
	public function setApplicationsOpen(int $id): void
	{
		$uid = Auth::requireUserId();
		$project = $this->projectLookup->load($id);
		$this->access->requireProjectRole($id, $uid, ProjectRole::Owner);

		$body = Request::body();
		if (!is_bool($body['open'] ?? null)) {
			Response::fail(422, 'Data tidak valid.', ['open' => 'Kirim open: true atau false.']);
		}

		$this->members->setAcceptingApplications($project->id, $body['open']);
		$fresh = $this->projects->find($id);
		Response::ok(['project' => $fresh?->toArray()], 200, 'Pengaturan lamaran disimpan.');
	}

	// ---------- owner and managers ----------

	/** DELETE /projects/{id}/members/{userId}: the owner removes anyone but themselves; a manager removes plain members only. */
	public function removeMember(int $id, int $userId): void
	{
		$uid = Auth::requireUserId();
		$project = $this->projectLookup->load($id);
		$myProjectRole = $this->access->requireProjectRole($id, $uid, ProjectRole::Owner, ProjectRole::Manager);
		ProjectLookup::requirePublished($project);

		if ($userId === $uid) {
			Response::fail(409, 'Untuk keluar dari proyek, gunakan "keluar dari proyek".');
		}

		$targetRole = $this->members->roleOf($id, $userId);
		if ($targetRole === null) {
			Fail::notFound('Anggota');
		}
		if ($targetRole === ProjectRole::Owner) {
			Response::fail(403, 'Pemilik proyek tidak dapat dikeluarkan.');
		}

		$allowed = [ProjectRole::Manager, ProjectRole::Member];
		if ($myProjectRole === ProjectRole::Manager) {
			$allowed = [ProjectRole::Member];
		}
		if (!in_array($targetRole, $allowed, true)) {
			Response::fail(403, 'Manajer hanya dapat mengeluarkan anggota biasa.');
		}

		$removed = $this->members->remove($id, $userId, $allowed);
		if (!$removed) {
			Response::fail(409, 'Anggota ini baru saja berubah. Muat ulang lalu coba lagi.');
		}

		$this->notifications->add(
			$userId,
			NotificationType::MEMBER_REMOVED,
			'Anda dikeluarkan dari proyek "' . $project->title . '".',
			$id,
		);
		Response::ok(['members' => $this->team->memberList($id)], 200, 'Anggota dikeluarkan.');
	}

	/** POST /projects/{id}/members/{userId}/promotion-requests: a manager asks the owner to make a member a manager. */
	public function requestPromotion(int $id, int $userId): void
	{
		$uid = Auth::requireUserId();
		$project = $this->projectLookup->load($id);
		$this->access->requireProjectRole($id, $uid, ProjectRole::Manager);
		ProjectLookup::requirePublished($project);

		$targetRole = $this->members->roleOf($id, $userId);
		if ($targetRole === null) {
			Fail::notFound('Anggota');
		}
		if ($targetRole !== ProjectRole::Member) {
			Response::fail(409, 'Hanya anggota biasa yang dapat dipromosikan.');
		}

		$requestId = $this->promotions->request($id, $userId, $uid);
		if ($requestId === null) {
			Response::fail(409, 'Permintaan untuk anggota ini masih menunggu keputusan pemilik.');
		}

		$this->notifications->add(
			$project->ownerId,
			NotificationType::PROMOTION_REQUESTED,
			$this->team->nameOf($uid) . ' meminta ' . $this->team->nameOf($userId) . ' dijadikan manajer di proyek "' . $project->title . '".',
			$id,
		);
		$request = $this->promotions->find($requestId);
		Response::ok(['request' => $request?->toArray()], 201, 'Permintaan dikirim ke pemilik.');
	}

	// ---------- the owner decides on promotion requests ----------

	/** GET /projects/{id}/promotion-requests: the requests waiting for the owner. */
	public function promotionRequests(int $id): void
	{
		$uid = Auth::requireUserId();
		$this->projectLookup->load($id);
		$this->access->requireProjectRole($id, $uid, ProjectRole::Owner);

		$list = [];
		foreach ($this->promotions->pendingFor($id) as $request) {
			$list[] = $request->toArray();
		}
		Response::ok(['requests' => $list]);
	}

	/** POST /promotion-requests/{id}/approve: the member becomes a manager. */
	public function approvePromotion(int $requestId): void
	{
		$uid = Auth::requireUserId();
		$project = $this->promotionDecision->decide($requestId, $uid, true);
		Response::ok(['members' => $this->team->memberList($project->id)], 200, 'Anggota dijadikan manajer.');
	}

	/** POST /promotion-requests/{id}/deny */
	public function denyPromotion(int $requestId): void
	{
		$uid = Auth::requireUserId();
		$this->promotionDecision->decide($requestId, $uid, false);
		Response::ok(null, 200, 'Permintaan ditolak.');
	}

	// ---------- anyone on the team ----------

	/** POST /projects/{id}/leave: leave the project. The owner cannot; they hand over ownership first. */
	public function leave(int $id): void
	{
		$uid = Auth::requireUserId();
		$project = $this->projectLookup->load($id);

		$myProjectRole = $this->access->requireProjectRole($id, $uid, ProjectRole::Manager, ProjectRole::Member, ProjectRole::Owner);
		if ($myProjectRole === ProjectRole::Owner) {
			Response::fail(409, 'Pemilik tidak dapat keluar. Alihkan kepemilikan terlebih dahulu.');
		}

		$removed = $this->members->remove($id, $uid, [$myProjectRole]);
		if (!$removed) {
			Response::fail(409, 'Peran Anda baru saja berubah. Muat ulang lalu coba lagi.');
		}

		$leaders = $this->members->userIdsWithRoles($id, [ProjectRole::Owner, ProjectRole::Manager]);
		$this->notifications->addForUsers(
			$leaders,
			NotificationType::MEMBER_LEFT,
			$this->team->nameOf($uid) . ' keluar dari proyek "' . $project->title . '".',
			$id,
		);
		Response::ok(null, 200, 'Anda keluar dari proyek.');
	}

	/** POST /projects/{id}/step-down: a manager becomes a plain member again. */
	public function stepDown(int $id): void
	{
		$uid = Auth::requireUserId();
		$project = $this->projectLookup->load($id);
		$this->access->requireProjectRole($id, $uid, ProjectRole::Manager);

		$changed = $this->members->changeRole($id, $uid, ProjectRole::Manager, ProjectRole::Member);
		if (!$changed) {
			Response::fail(409, 'Peran Anda baru saja berubah. Muat ulang lalu coba lagi.');
		}

		$this->notifications->add(
			$project->ownerId,
			NotificationType::MEMBER_ROLE_CHANGED,
			$this->team->nameOf($uid) . ' mundur dari manajer proyek "' . $project->title . '".',
			$id,
		);
		Response::ok(['members' => $this->team->memberList($id)], 200, 'Anda sekarang anggota biasa.');
	}
}
