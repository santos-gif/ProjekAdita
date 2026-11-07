<?php

declare(strict_types=1);

namespace Features\Projects\Controller;

use Core\Auth;
use Core\Request;
use Core\Response;
use Features\Notifications\NotificationType;
use Features\Notifications\Repository\NotificationRepository;
use Features\Projects\Helper\ProjectImages;
use Features\Projects\Helper\ProjectLookup;
use Features\Projects\Helper\ProjectTags;
use Features\Projects\Model\AddProjectInput;
use Features\Projects\Repository\ProjectRepository;
use Features\User\Model\Role;
use Shared\Helpers\Fail;
use Shared\Stores\ImageStore;

/** The owner's side of projects: submit, list, edit, withdraw, and the saved draft. Everything needs a login. */
final class ProjectController
{
	/** Room for a 2 MB image as base64 (about 2.7 MB) plus the text fields; the default body limit is 64 KB. */
	private const BODY_LIMIT = 3500000;
	/** While a project is in one of these, its owner may still withdraw (delete) it. */
	private const WITHDRAWABLE = ['pending_moderation', 'pending_admin'];

	public function __construct(
		private ProjectRepository $projects = new ProjectRepository(),
		private ImageStore $thumbs = new ImageStore(),
		private NotificationRepository $notifications = new NotificationRepository(),
		private ProjectLookup $lookup = new ProjectLookup(),
		private ProjectImages $images = new ProjectImages(),
		private ProjectTags $tags = new ProjectTags(),
	) {}

	/** GET /projects/mine: all of the owner's submitted projects. The page counts, filters and searches them itself. */
	public function mine(): void
	{
		$uid = Auth::requireUserId();

		$list = [];
		foreach ($this->projects->listByOwner($uid) as $project) {
			$list[] = $project->toArray();
		}
		Response::ok(['projects' => $list]);
	}

	/** POST /projects: submit a project; it starts at pending_moderation. */
	public function create(): void
	{
		$uid = Auth::requireUserId();
		$in = AddProjectInput::fromRequest(Request::body(self::BODY_LIMIT));
		Fail::invalid($in->errors());
		$tagIds = $this->tags->idsFor($in);

		$draft = $this->projects->findDraft($uid);
		$draftImage = $this->images->draftImageToKeep($in, $draft);
		$url = $this->images->resolve($in, $draftImage);

		try {
			$id = $this->projects->create($uid, $in, $tagIds, $url);
		} catch (\Throwable $e) {
			// Nothing was saved, so an image written for this request is not needed.
			if ($url !== $draftImage) {
				$this->thumbs->remove($url);
			}
			throw $e;
		}

		// create() deleted the draft row. Its image goes too, unless the new project took it over.
		if ($draft !== null && $draft->thumbnailUrl !== $url) {
			$this->thumbs->remove($draft->thumbnailUrl);
		}

		// The project is saved for good now, so the moderators can be told about it.
		$this->notifications->addForRole(
			Role::Moderator,
			NotificationType::PROJECT_SUBMITTED,
			'Proyek baru menunggu peninjauan: ' . $in->title,
			$id,
		);
		Response::ok(['project' => $this->projects->find($id)?->toArray()], 201, 'Proyek berhasil diajukan.');
	}

	/** PATCH /projects/{id}: edit while the moderator has not looked at it yet. */
	public function update(int $id): void
	{
		$project = $this->lookup->owned($id, Auth::requireUserId());
		if ($project->status !== 'pending_moderation') {
			Response::fail(409, 'Proyek tidak bisa diubah lagi karena sudah dalam peninjauan.');
		}

		$in = AddProjectInput::fromRequest(Request::body(self::BODY_LIMIT));
		Fail::invalid($in->errors());
		$tagIds = $this->tags->idsFor($in);
		$url = $this->images->resolve($in, $project->thumbnailUrl);
		$imageIsNew = $url !== $project->thumbnailUrl;

		try {
			$saved = $this->projects->update($id, $in, $tagIds, $url);
		} catch (\Throwable $e) {
			if ($imageIsNew) {
				$this->thumbs->remove($url);
			}
			throw $e;
		}

		if (!$saved) {
			// The status changed between our check above and the write: nothing was saved.
			if ($imageIsNew) {
				$this->thumbs->remove($url);
			}
			Response::fail(409, 'Proyek tidak bisa diubah lagi karena sudah dalam peninjauan.');
		}

		if ($imageIsNew) {
			$this->thumbs->remove($project->thumbnailUrl);
		}
		Response::ok(['project' => $this->projects->find($id)?->toArray()], 200, 'Perubahan proyek disimpan.');
	}

	/** DELETE /projects/{id}: the owner withdraws a submission that is still pending. No body is read. */
	public function destroy(int $id): void
	{
		$project = $this->lookup->owned($id, Auth::requireUserId());
		if (!in_array($project->status, self::WITHDRAWABLE, true)) {
			Response::fail(409, 'Proyek yang sudah dipublikasikan atau ditolak tidak bisa dibatalkan.');
		}

		$deleted = $this->projects->delete($id);
		if (!$deleted) {
			Response::fail(409, 'Proyek yang sudah dipublikasikan atau ditolak tidak bisa dibatalkan.');
		}
		$this->thumbs->remove($project->thumbnailUrl);
		Response::ok(null, 200, 'Pengajuan proyek dibatalkan.');
	}

	/** GET /projects/draft: the saved form, or {"project": null}. */
	public function draft(): void
	{
		$uid = Auth::requireUserId();
		$draft = $this->projects->findDraft($uid);
		Response::ok(['project' => $draft?->toArray()]);
	}

	/** PUT /projects/draft: save the form as it is; a draft may be unfinished. */
	public function saveDraft(): void
	{
		$uid = Auth::requireUserId();
		$in = AddProjectInput::fromRequest(Request::body(self::BODY_LIMIT));
		Fail::invalid($in->errors(draft: true));
		$tagIds = $this->tags->idsFor($in);

		$oldDraft = $this->projects->findDraft($uid);
		$currentUrl = null;
		if ($oldDraft !== null) {
			$currentUrl = $oldDraft->thumbnailUrl;
		}
		$url = $this->images->resolve($in, $currentUrl);
		$imageIsNew = $url !== $currentUrl;

		try {
			$this->projects->saveDraft($uid, $in, $tagIds, $url);
		} catch (\Throwable $e) {
			if ($imageIsNew) {
				$this->thumbs->remove($url);
			}
			throw $e;
		}

		if ($imageIsNew) {
			$this->thumbs->remove($currentUrl);
		}
		$saved = $this->projects->findDraft($uid);
		Response::ok(['project' => $saved?->toArray()], 200, 'Draf proyek disimpan.');
	}
}
