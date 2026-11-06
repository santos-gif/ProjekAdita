<?php

declare(strict_types=1);

namespace Features\Projects;

use Core\Auth;
use Core\Request;
use Core\Response;
use Features\Projects\Model\AddProjectInput;
use Features\Notifications\NotificationRepository;
use Features\Notifications\NotificationType;
use Features\Projects\Model\Project;
use Features\Tags\TagRepository;
use Features\User\Model\Role;

/** The owner's side of projects: submit, list, edit, withdraw, and the saved draft. Everything needs a login. */
final class ProjectController
{
	/** Room for a 2 MB image as base64 (about 2.7 MB) plus the text fields; the default body limit is 64 KB. */
	private const BODY_LIMIT = 3500000;
	/** While a project is in one of these, its owner may still withdraw (delete) it. */
	private const WITHDRAWABLE = ['pending_moderation', 'pending_admin'];

	public function __construct(
		private ProjectRepository $projects = new ProjectRepository(),
		private TagRepository $tags = new TagRepository(),
		private ThumbnailStore $thumbs = new ThumbnailStore(),
		private NotificationRepository $notifications = new NotificationRepository(),
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
		$this->reject($in->errors());
		$tagIds = $this->tagIds($in);

		$draft = $this->projects->findDraft($uid);
		$draftImage = $this->draftImageToKeep($in, $draft);
		$url = $this->resolveThumbnail($in, $draftImage);

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
		Response::ok(['project' => $this->asArray($this->projects->find($id))], 201, 'Proyek berhasil diajukan.');
	}

	/** PATCH /projects/{id}: edit while the moderator has not looked at it yet. */
	public function update(int $id): void
	{
		$project = $this->owned($id);
		if ($project->status !== 'pending_moderation') {
			Response::fail(409, 'Proyek tidak bisa diubah lagi karena sudah dalam peninjauan.');
		}

		$in = AddProjectInput::fromRequest(Request::body(self::BODY_LIMIT));
		$this->reject($in->errors());
		$tagIds = $this->tagIds($in);
		$url = $this->resolveThumbnail($in, $project->thumbnailUrl);
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
		Response::ok(['project' => $this->asArray($this->projects->find($id))], 200, 'Perubahan proyek disimpan.');
	}

	/** DELETE /projects/{id}: the owner withdraws a submission that is still pending. No body is read. */
	public function destroy(int $id): void
	{
		$project = $this->owned($id);
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
		Response::ok(['project' => $this->asArray($draft)]);
	}

	/** PUT /projects/draft: save the form as it is; a draft may be unfinished. */
	public function saveDraft(): void
	{
		$uid = Auth::requireUserId();
		$in = AddProjectInput::fromRequest(Request::body(self::BODY_LIMIT));
		$this->reject($in->errors(draft: true));
		$tagIds = $this->tagIds($in);

		$oldDraft = $this->projects->findDraft($uid);
		$currentUrl = null;
		if ($oldDraft !== null) {
			$currentUrl = $oldDraft->thumbnailUrl;
		}
		$url = $this->resolveThumbnail($in, $currentUrl);
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
		Response::ok(['project' => $this->asArray($saved)], 200, 'Draf proyek disimpan.');
	}

	/** The project, if it exists and belongs to the logged-in user. Anything else is a 404: no probing for ids. */
	private function owned(int $id): Project
	{
		$uid = Auth::requireUserId();
		$project = $this->projects->find($id);

		if ($project === null) {
			$this->notFound();
		}
		if ($project->ownerId !== $uid) {
			$this->notFound();
		}
		if ($project->status === 'draft') {
			$this->notFound();
		}
		return $project;
	}

	private function notFound(): never
	{
		Response::fail(404, 'Proyek tidak ditemukan.');
	}

	/** @return array<string,mixed>|null the project as JSON, or null when there is none */
	private function asArray(?Project $project): ?array
	{
		if ($project === null) {
			return null;
		}
		return $project->toArray();
	}

	/** @param array<string,string> $errors */
	private function reject(array $errors): void
	{
		if ($errors !== []) {
			Response::fail(422, 'Data tidak valid.', $errors);
		}
	}

	/**
	 * A form that was loaded from the draft sends the draft's image URL back.
	 * Then the new project takes that image over. Returns that URL, or null when it does not apply.
	 */
	private function draftImageToKeep(AddProjectInput $in, ?Project $draft): ?string
	{
		if ($draft === null) {
			return null;
		}
		if ($in->thumbnailUrl === null) {
			return null;
		}
		if ($in->thumbnailUrl !== $draft->thumbnailUrl) {
			return null;
		}
		return $draft->thumbnailUrl;
	}

	/** @return list<int> ids of the chosen tags; a name outside the official list is a 422 */
	private function tagIds(AddProjectInput $in): array
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
			Response::fail(422, 'Data tidak valid.', ['tags' => 'Tag tidak ada dalam daftar resmi: ' . $names . '.']);
		}
		return array_values(array_unique($ids));
	}

	/**
	 * The thumbnail URL to store. A new image is checked and written now (a bad one is a 422 before anything is saved);
	 * null removes it; otherwise the current one stays. The caller deletes the replaced file once the database agrees.
	 */
	private function resolveThumbnail(AddProjectInput $in, ?string $current): ?string
	{
		if ($in->thumbnailData !== null) {
			return $this->saveNewThumbnail($in->thumbnailData);
		}
		if ($in->thumbnailClear) {
			return null;
		}
		return $current;
	}

	/** @return string the URL of the file just written */
	private function saveNewThumbnail(string $dataUrl): string
	{
		$image = $this->thumbs->decode($dataUrl);
		if ($image === null) {
			Response::fail(422, 'Data tidak valid.', ['thumbnail' => 'Thumbnail harus gambar JPG, PNG, atau WEBP (maks. 2MB).']);
		}
		return $this->thumbs->write($image);
	}
}
