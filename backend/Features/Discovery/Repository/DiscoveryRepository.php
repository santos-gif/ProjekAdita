<?php

declare(strict_types=1);

namespace Features\Discovery\Repository;

use Core\Db;
use Features\Discovery\Helper\ProjectViews;
use Features\Discovery\Model\ProjectFilter;
use Features\Discovery\Model\ProjectView;
use Features\Discovery\Schema\ProjectViewSchema;

/** Reading projects for people other than the owner: the search, the detail, "projects I joined". */
final class DiscoveryRepository
{
	/**
	 * @return array{items: list<ProjectView>, total: int} one page of published projects, and how many match in all
	 */
	public function search(int $viewerId, ProjectFilter $filter): array
	{
		$where = ["p.status = 'published'"];
		$params = [];

		if ($filter->q !== '') {
			// % and _ typed by the visitor are ordinary characters, not wildcards.
			$like = '%' . addcslashes($filter->q, '%_\\') . '%';
			$where[] = '(p.project_name LIKE ? OR p.description LIKE ? OR u.name LIKE ?)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}
		foreach ($filter->tags as $tag) {
			$where[] = 'EXISTS (SELECT 1 FROM project_tags pt JOIN tags t ON t.id = pt.tag_id WHERE pt.project_id = p.id AND t.name = ?)';
			$params[] = $tag;
		}
		if ($filter->selectionMethod !== null) {
			$where[] = 'p.selection_method = ?';
			$params[] = $filter->selectionMethod;
		}
		if ($filter->openOnly) {
			$where[] = 'p.accepting_applications = 1';
		}
		if ($filter->studyProgram !== null) {
			$where[] = 'u.study_program = ?';
			$params[] = $filter->studyProgram;
		}

		$whereSql = implode(' AND ', $where);
		$from = 'FROM projects p JOIN users u ON u.id = p.owner_id WHERE ' . $whereSql;

		$count = Db::pdo()->prepare('SELECT COUNT(*) ' . $from);
		$count->execute($params);
		$total = (int) $count->fetchColumn();

		$order = 'p.published_at DESC, p.id DESC';
		if ($filter->sort === 'members') {
			$order = 'members_count DESC, p.id DESC';
		}

		// perPage and offset are integers made by ProjectFilter, so they are safe to put in the text.
		$sql = 'SELECT ' . ProjectViewSchema::COLUMNS . ' ' . $from . ' ORDER BY ' . $order
			. ' LIMIT ' . $filter->perPage . ' OFFSET ' . $filter->offset();
		$st = Db::pdo()->prepare($sql);
		$st->execute(array_merge([$viewerId, $viewerId], $params));

		return ['items' => ProjectViews::fromRows($st->fetchAll()), 'total' => $total];
	}

	/** One submitted project as this viewer sees it. A draft is private, so it is never found here. */
	public function find(int $id, int $viewerId): ?ProjectView
	{
		$sql = 'SELECT ' . ProjectViewSchema::COLUMNS . "
		        FROM projects p JOIN users u ON u.id = p.owner_id
		        WHERE p.id = ? AND p.status <> 'draft'";
		$st = Db::pdo()->prepare($sql);
		$st->execute([$viewerId, $viewerId, $id]);

		$row = $st->fetch();
		if ($row === false) {
			return null;
		}
		return ProjectViewSchema::view($row);
	}

	/** @return list<ProjectView> the projects where the viewer is a manager or a member (not the owner), newest first */
	public function joined(int $viewerId): array
	{
		$sql = 'SELECT ' . ProjectViewSchema::COLUMNS . "
		        FROM projects p JOIN users u ON u.id = p.owner_id
		        WHERE EXISTS (SELECT 1 FROM project_members jm
		                      WHERE jm.project_id = p.id AND jm.user_id = ? AND jm.role <> 'owner')
		        ORDER BY p.id DESC";
		$st = Db::pdo()->prepare($sql);
		$st->execute([$viewerId, $viewerId, $viewerId]);
		return ProjectViews::fromRows($st->fetchAll());
	}
}
