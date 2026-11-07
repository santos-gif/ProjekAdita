<?php

declare(strict_types=1);

namespace Features\Projects\Repository;

use Core\Db;
use Features\Projects\Model\AddProjectInput;
use Features\Projects\Model\Project;
use Features\Projects\Schema\ProjectSchema;

/** The `projects` table with its tags (project_tags) and members (project_members). */
final class ProjectRepository
{
	private const SELECT = 'SELECT ' . ProjectSchema::COLUMNS . ' FROM projects p ';

	public function find(int $id): ?Project
	{
		$st = Db::pdo()->prepare(self::SELECT . 'WHERE p.id = ?');
		$st->execute([$id]);
		$row = $st->fetch();
		return $row ? ProjectSchema::project($row) : null;
	}

	/** @return list<Project> the owner's submitted projects, newest first; the draft is not a project yet */
	public function listByOwner(int $ownerId): array
	{
		$st = Db::pdo()->prepare(self::SELECT . "WHERE p.owner_id = ? AND p.status <> 'draft' ORDER BY p.created_at DESC, p.id DESC");
		$st->execute([$ownerId]);

		$projects = [];
		foreach ($st->fetchAll() as $row) {
			$projects[] = ProjectSchema::project($row);
		}
		return $projects;
	}

	/** The owner's saved form, if any. There is at most one. */
	public function findDraft(int $ownerId): ?Project
	{
		$st = Db::pdo()->prepare(self::SELECT . "WHERE p.owner_id = ? AND p.status = 'draft' LIMIT 1");
		$st->execute([$ownerId]);
		$row = $st->fetch();
		return $row ? ProjectSchema::project($row) : null;
	}

	/**
	 * Submits a project: the project, its owner row and its tags are saved together or not at all.
	 * The owner's draft is used up by submitting, so it is deleted in the same transaction.
	 * @param list<int> $tagIds
	 * @return int the new project's id (status pending_moderation, the column default)
	 */
	public function create(int $ownerId, AddProjectInput $in, array $tagIds, ?string $thumbnailUrl): int
	{
		return Db::transaction(function (\PDO $pdo) use ($ownerId, $in, $tagIds, $thumbnailUrl): int {
			$pdo->prepare(
				'INSERT INTO projects (owner_id, project_name, thumbnail_url, description, requirements, selection_method)
				 VALUES (?, ?, ?, ?, ?, ?)'
			)->execute([$ownerId, $in->title, $thumbnailUrl, $in->description, $in->requirements, $in->selectionMethod]);
			$id = (int) $pdo->lastInsertId();
			// The owner is a member too; project_members.title is the role name shown on the team.
			$pdo->prepare("INSERT INTO project_members (project_id, user_id, role, title) VALUES (?, ?, 'owner', 'Owner')")
				->execute([$id, $ownerId]);
			$this->setTags($pdo, $id, $tagIds);
			$pdo->prepare("DELETE FROM projects WHERE owner_id = ? AND status = 'draft'")->execute([$ownerId]);
			return $id;
		});
	}

	/**
	 * @param list<int> $tagIds
	 * @return bool false when the project is no longer pending_moderation: nothing was changed
	 */
	public function update(int $id, AddProjectInput $in, array $tagIds, ?string $thumbnailUrl): bool
	{
		return Db::transaction(function (\PDO $pdo) use ($id, $in, $tagIds, $thumbnailUrl): bool {
			// Lock the row and read the status inside the transaction that writes: the moderator may have
			// picked the project up since the controller looked at it.
			$st = $pdo->prepare('SELECT status FROM projects WHERE id = ? FOR UPDATE');
			$st->execute([$id]);
			if ($st->fetchColumn() !== 'pending_moderation') {
				return false;
			}
			$pdo->prepare(
				'UPDATE projects SET project_name = ?, thumbnail_url = ?, description = ?, requirements = ?, selection_method = ?
				 WHERE id = ?'
			)->execute([$in->title, $thumbnailUrl, $in->description, $in->requirements, $in->selectionMethod, $id]);
			$this->setTags($pdo, $id, $tagIds);
			return true;
		});
	}

	/**
	 * Saves the owner's draft, replacing the previous one. A draft has no owner row in project_members yet.
	 * @param list<int> $tagIds
	 */
	public function saveDraft(int $ownerId, AddProjectInput $in, array $tagIds, ?string $thumbnailUrl): void
	{
		// One draft per owner: two saves at the same moment (a double click) wait for each other here
		// instead of both finding "no draft yet" and both inserting. Same named lock as the rate limiter uses.
		$pdo = Db::pdo();
		$lock = $pdo->prepare('SELECT GET_LOCK(?, 5)');
		$lock->execute(['draft:' . $ownerId]);
		if ((int) $lock->fetchColumn() !== 1) {
			throw new \RuntimeException('Another save of this draft is taking too long.');
		}
		try {
			$this->writeDraft($ownerId, $in, $tagIds, $thumbnailUrl);
		} finally {
			$pdo->prepare('SELECT RELEASE_LOCK(?)')->execute(['draft:' . $ownerId]);
		}
	}

	/** @param list<int> $tagIds */
	private function writeDraft(int $ownerId, AddProjectInput $in, array $tagIds, ?string $thumbnailUrl): void
	{
		$existing = $this->findDraft($ownerId);
		Db::transaction(function (\PDO $pdo) use ($ownerId, $in, $tagIds, $thumbnailUrl, $existing): void {
			if ($existing === null) {
				$pdo->prepare(
					"INSERT INTO projects (owner_id, project_name, thumbnail_url, description, requirements, selection_method, status)
					 VALUES (?, ?, ?, ?, ?, ?, 'draft')"
				)->execute([$ownerId, $in->title, $thumbnailUrl, $in->description, $in->requirements, $in->selectionMethod]);
				$id = (int) $pdo->lastInsertId();
			} else {
				$id = $existing->id;
				$pdo->prepare(
					'UPDATE projects SET project_name = ?, thumbnail_url = ?, description = ?, requirements = ?, selection_method = ?
					 WHERE id = ?'
				)->execute([$in->title, $thumbnailUrl, $in->description, $in->requirements, $in->selectionMethod, $id]);
			}
			$this->setTags($pdo, $id, $tagIds);
		});
	}

	/**
	 * Withdraws a project that is still pending. The status is part of the DELETE itself, so a project the admin
	 * published a moment ago is never deleted. Foreign keys cascade: tags, members and reviews go with it.
	 * @return bool false when the project is not (or no longer) pending: nothing was deleted
	 */
	public function delete(int $id): bool
	{
		$st = Db::pdo()->prepare("DELETE FROM projects WHERE id = ? AND status IN ('pending_moderation', 'pending_admin')");
		$st->execute([$id]);
		return $st->rowCount() > 0;
	}

	/** @param list<int> $tagIds replaces the project's tags */
	private function setTags(\PDO $pdo, int $projectId, array $tagIds): void
	{
		$pdo->prepare('DELETE FROM project_tags WHERE project_id = ?')->execute([$projectId]);
		$insert = $pdo->prepare('INSERT INTO project_tags (project_id, tag_id) VALUES (?, ?)');
		foreach ($tagIds as $tagId) {
			$insert->execute([$projectId, $tagId]);
		}
	}
}
