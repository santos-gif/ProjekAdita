<?php

declare(strict_types=1);

namespace Features\Projects;

use Core\Auth;
use Core\Request;
use Core\Response;
use Features\Notifications\NotificationRepository;
use Features\Notifications\NotificationType;
use Features\Projects\Model\Project;
use Features\Projects\Model\ProjectRole;
use Features\Projects\Model\PromotionResult;
use Features\User\UserRepository;

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
	private const MIN_TITLE = 2;
	private const MAX_TITLE = 60;

	public function __construct(
		private ProjectRepository $projects = new ProjectRepository(),
		private MemberRepository $members = new MemberRepository(),
		private PromotionRepository $promotions = new PromotionRepository(),
		private NotificationRepository $notifications = new NotificationRepository(),
		private UserRepository $users = new UserRepository(),
	) {}

	// ---------- looking ----------

	/** GET /projects/{id}/members: for anyone who may see the project (published, or they are on it). */
	public function members(int $id): void
	{
		$uid = Auth::requireUserId();
		$project = $this->loadProject($id);

		$role = $this->members->roleOf($id, $uid);
		if ($project->status !== 'published' && $role === null) {
			$this->notFound();
		}
		Response::ok(['members' => $this->memberList($id)]);
	}

	// ---------- the owner manages the team ----------

	/** PATCH /projects/{id}/members/{userId} {role?, title?}: the owner makes someone a manager (or a plain member again) and renames their position. */
	public function changeMember(int $id, int $userId): void
	{
		$uid = Auth::requireUserId();
		$project = $this->loadProject($id);
		$this->requireRole($id, $uid, ProjectRole::Owner);
		$this->requirePublished($project);

		$targetRole = $this->members->roleOf($id, $userId);
		if ($targetRole === null) {
			Response::fail(404, 'Anggota tidak ditemukan.');
		}
		if ($targetRole === ProjectRole::Owner) {
			Response::fail(409, 'Peran pemilik diubah lewat pengalihan kepemilikan.');
		}

		$body = Request::body();
		$newRole = $this->readRole($body);
		$newTitle = $this->readTitle($body);
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
				'Peran Anda di proyek "' . $project->title . '" sekarang: ' . $this->roleLabel($newRole) . '.',
				$id,
			);
		}
		if ($newTitle !== null) {
			$this->members->changeTitle($id, $userId, $newTitle);
		}
		Response::ok(['members' => $this->memberList($id)], 200, 'Anggota diperbarui.');
	}

	/** POST /projects/{id}/transfer-ownership {user_id}: the new owner must already be on the project; the old owner becomes a manager. */
	public function transfer(int $id): void
	{
		$uid = Auth::requireUserId();
		$project = $this->loadProject($id);
		$this->requireRole($id, $uid, ProjectRole::Owner);
		$this->requirePublished($project);

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
		Response::ok(['members' => $this->memberList($id)], 200, 'Kepemilikan dialihkan.');
	}

	/** PATCH /projects/{id}/applications-open {open}: the owner opens or closes the project for NEW applications. */
	public function setApplicationsOpen(int $id): void
	{
		$uid = Auth::requireUserId();
		$project = $this->loadProject($id);
		$this->requireRole($id, $uid, ProjectRole::Owner);

		$body = Request::body();
		if (!is_bool($body['open'] ?? null)) {
			Response::fail(422, 'Data tidak valid.', ['open' => 'Kirim open: true atau false.']);
		}

		$this->members->setAcceptingApplications($project->id, $body['open']);
		$fresh = $this->projects->find($id);
		Response::ok(['project' => $this->asArray($fresh)], 200, 'Pengaturan lamaran disimpan.');
	}

	// ---------- owner and managers ----------

	/** DELETE /projects/{id}/members/{userId}: the owner removes anyone but themselves; a manager removes plain members only. */
	public function removeMember(int $id, int $userId): void
	{
		$uid = Auth::requireUserId();
		$project = $this->loadProject($id);
		$myRole = $this->requireRole($id, $uid, ProjectRole::Owner, ProjectRole::Manager);
		$this->requirePublished($project);

		if ($userId === $uid) {
			Response::fail(409, 'Untuk keluar dari proyek, gunakan "keluar dari proyek".');
		}

		$targetRole = $this->members->roleOf($id, $userId);
		if ($targetRole === null) {
			Response::fail(404, 'Anggota tidak ditemukan.');
		}
		if ($targetRole === ProjectRole::Owner) {
			Response::fail(403, 'Pemilik proyek tidak dapat dikeluarkan.');
		}

		$allowed = [ProjectRole::Manager, ProjectRole::Member];
		if ($myRole === ProjectRole::Manager) {
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
		Response::ok(['members' => $this->memberList($id)], 200, 'Anggota dikeluarkan.');
	}

	/** POST /projects/{id}/members/{userId}/promotion-requests: a manager asks the owner to make a member a manager. */
	public function requestPromotion(int $id, int $userId): void
	{
		$uid = Auth::requireUserId();
		$project = $this->loadProject($id);
		$this->requireRole($id, $uid, ProjectRole::Manager);
		$this->requirePublished($project);

		$targetRole = $this->members->roleOf($id, $userId);
		if ($targetRole === null) {
			Response::fail(404, 'Anggota tidak ditemukan.');
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
			$this->nameOf($uid) . ' meminta ' . $this->nameOf($userId) . ' dijadikan manajer di proyek "' . $project->title . '".',
			$id,
		);
		$request = $this->promotions->find($requestId);
		Response::ok(['request' => $this->requestArray($request)], 201, 'Permintaan dikirim ke pemilik.');
	}

	// ---------- the owner decides on promotion requests ----------

	/** GET /projects/{id}/promotion-requests: the requests waiting for the owner. */
	public function promotionRequests(int $id): void
	{
		$uid = Auth::requireUserId();
		$this->loadProject($id);
		$this->requireRole($id, $uid, ProjectRole::Owner);

		$list = [];
		foreach ($this->promotions->pendingFor($id) as $request) {
			$list[] = $request->toArray();
		}
		Response::ok(['requests' => $list]);
	}

	/** POST /promotion-requests/{id}/approve: the member becomes a manager. */
	public function approvePromotion(int $requestId): void
	{
		$this->decidePromotion($requestId, true);
	}

	/** POST /promotion-requests/{id}/deny */
	public function denyPromotion(int $requestId): void
	{
		$this->decidePromotion($requestId, false);
	}

	// ---------- anyone on the team ----------

	/** POST /projects/{id}/leave: leave the project. The owner cannot; they hand over ownership first. */
	public function leave(int $id): void
	{
		$uid = Auth::requireUserId();
		$project = $this->loadProject($id);

		$role = $this->requireRole($id, $uid, ProjectRole::Manager, ProjectRole::Member, ProjectRole::Owner);
		if ($role === ProjectRole::Owner) {
			Response::fail(409, 'Pemilik tidak dapat keluar. Alihkan kepemilikan terlebih dahulu.');
		}

		$removed = $this->members->remove($id, $uid, [$role]);
		if (!$removed) {
			Response::fail(409, 'Peran Anda baru saja berubah. Muat ulang lalu coba lagi.');
		}

		$leaders = $this->members->userIdsWithRoles($id, [ProjectRole::Owner, ProjectRole::Manager]);
		$this->notifications->addForUsers(
			$leaders,
			NotificationType::MEMBER_LEFT,
			$this->nameOf($uid) . ' keluar dari proyek "' . $project->title . '".',
			$id,
		);
		Response::ok(null, 200, 'Anda keluar dari proyek.');
	}

	/** POST /projects/{id}/step-down: a manager becomes a plain member again. */
	public function stepDown(int $id): void
	{
		$uid = Auth::requireUserId();
		$project = $this->loadProject($id);
		$this->requireRole($id, $uid, ProjectRole::Manager);

		$changed = $this->members->changeRole($id, $uid, ProjectRole::Manager, ProjectRole::Member);
		if (!$changed) {
			Response::fail(409, 'Peran Anda baru saja berubah. Muat ulang lalu coba lagi.');
		}

		$this->notifications->add(
			$project->ownerId,
			NotificationType::MEMBER_ROLE_CHANGED,
			$this->nameOf($uid) . ' mundur dari manajer proyek "' . $project->title . '".',
			$id,
		);
		Response::ok(['members' => $this->memberList($id)], 200, 'Anda sekarang anggota biasa.');
	}

	// ---------- helpers ----------

	private function decidePromotion(int $requestId, bool $approve): void
	{
		$uid = Auth::requireUserId();

		$request = $this->promotions->find($requestId);
		if ($request === null) {
			Response::fail(404, 'Permintaan tidak ditemukan.');
		}
		$project = $this->loadProject($request->projectId);
		$this->requireRole($request->projectId, $uid, ProjectRole::Owner);

		$result = $this->promotions->decide($requestId, $uid, $approve);
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
			Response::ok(['members' => $this->memberList($project->id)], 200, 'Anggota dijadikan manajer.');
		}

		$this->notifications->add(
			$request->requestedBy,
			NotificationType::PROMOTION_DECIDED,
			'Permintaan Anda ditolak pemilik: ' . $request->memberName . ' tetap anggota biasa di proyek "' . $project->title . '".',
			$project->id,
		);
		Response::ok(null, 200, 'Permintaan ditolak.');
	}

	/** The project, or a 404 when it does not exist. A draft is private to its owner, so it never counts. */
	private function loadProject(int $id): Project
	{
		$project = $this->projects->find($id);
		if ($project === null) {
			$this->notFound();
		}
		if ($project->status === 'draft') {
			$this->notFound();
		}
		return $project;
	}

	/**
	 * The role the user has on the project, provided it is one of $allowed.
	 * Someone who is not on the project learns nothing (404); someone with the wrong role gets a 403.
	 */
	private function requireRole(int $projectId, int $userId, ProjectRole ...$allowed): ProjectRole
	{
		$role = $this->members->roleOf($projectId, $userId);
		if ($role === null) {
			$this->notFound();
		}
		if (!in_array($role, $allowed, true)) {
			Response::fail(403, 'Anda tidak memiliki wewenang untuk tindakan ini.');
		}
		return $role;
	}

	/** Team changes only make sense while the project is public. */
	private function requirePublished(Project $project): void
	{
		if ($project->status !== 'published') {
			Response::fail(409, 'Proyek ini sedang tidak dipublikasikan.');
		}
	}

	private function notFound(): never
	{
		Response::fail(404, 'Proyek tidak ditemukan.');
	}

	/** @return list<array<string,mixed>> */
	private function memberList(int $projectId): array
	{
		$list = [];
		foreach ($this->members->listFor($projectId) as $member) {
			$list[] = $member->toArray();
		}
		return $list;
	}

	/** @param array<string,mixed> $body @return ProjectRole|null the role asked for (manager or member), null when the key is absent */
	private function readRole(array $body): ?ProjectRole
	{
		if (!array_key_exists('role', $body)) {
			return null;
		}
		if ($body['role'] === 'manager') {
			return ProjectRole::Manager;
		}
		if ($body['role'] === 'member') {
			return ProjectRole::Member;
		}
		Response::fail(422, 'Data tidak valid.', ['role' => 'Peran harus "manager" atau "member".']);
	}

	/** @param array<string,mixed> $body @return string|null the new position title, null when the key is absent */
	private function readTitle(array $body): ?string
	{
		if (!array_key_exists('title', $body)) {
			return null;
		}

		$title = '';
		if (is_string($body['title'])) {
			$title = trim($body['title']);
		}
		$length = mb_strlen($title);
		if ($length < self::MIN_TITLE || $length > self::MAX_TITLE) {
			Response::fail(422, 'Data tidak valid.', ['title' => 'Posisi harus 2-60 karakter.']);
		}
		return $title;
	}

	private function roleLabel(ProjectRole $role): string
	{
		if ($role === ProjectRole::Owner) {
			return 'pemilik';
		}
		if ($role === ProjectRole::Manager) {
			return 'manajer';
		}
		return 'anggota';
	}

	private function nameOf(int $userId): string
	{
		$user = $this->users->findPublicById($userId);
		if ($user === null) {
			return 'Seseorang';
		}
		return $user->name;
	}

	/** @return array<string,mixed>|null */
	private function asArray(?Project $project): ?array
	{
		if ($project === null) {
			return null;
		}
		return $project->toArray();
	}

	/** @return array<string,mixed>|null */
	private function requestArray(?Model\PromotionRequest $request): ?array
	{
		if ($request === null) {
			return null;
		}
		return $request->toArray();
	}
}
