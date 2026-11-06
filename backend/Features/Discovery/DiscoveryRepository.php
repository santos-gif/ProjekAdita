<?php

declare(strict_types=1);

namespace Features\Discovery;

use Core\Db;
use Features\Applications\Schema\ApplicationSchema;
use Features\Discovery\Model\ProjectFilter;
use Features\Discovery\Model\ProjectView;
use Features\Projects\Schema\ProjectSchema;

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
		$sql = 'SELECT ' . $this->columns() . ' ' . $from . ' ORDER BY ' . $order
			. ' LIMIT ' . $filter->perPage . ' OFFSET ' . $filter->offset();
		$st = Db::pdo()->prepare($sql);
		$st->execute(array_merge([$viewerId, $viewerId], $params));

		return ['items' => $this->views($st->fetchAll()), 'total' => $total];
	}

	/** One submitted project as this viewer sees it. A draft is private, so it is never found here. */
	public function find(int $id, int $viewerId): ?ProjectView
	{
		$sql = 'SELECT ' . $this->columns() . "
		        FROM projects p JOIN users u ON u.id = p.owner_id
		        WHERE p.id = ? AND p.status <> 'draft'";
		$st = Db::pdo()->prepare($sql);
		$st->execute([$viewerId, $viewerId, $id]);

		$row = $st->fetch();
		if ($row === false) {
			return null;
		}
		return $this->view($row);
	}

	/** @return list<ProjectView> the projects where the viewer is a manager or a member (not the owner), newest first */
	public function joined(int $viewerId): array
	{
		$sql = 'SELECT ' . $this->columns() . "
		        FROM projects p JOIN users u ON u.id = p.owner_id
		        WHERE EXISTS (SELECT 1 FROM project_members jm
		                      WHERE jm.project_id = p.id AND jm.user_id = ? AND jm.role <> 'owner')
		        ORDER BY p.id DESC";
		$st = Db::pdo()->prepare($sql);
		$st->execute([$viewerId, $viewerId, $viewerId]);
		return $this->views($st->fetchAll());
	}

	/**
	 * The columns every query selects. It has two "?" for the viewer (their role and their application),
	 * so the viewer's id is always the first two values of the query's parameters.
	 */
	private function columns(): string
	{
		return ProjectSchema::COLUMNS . ', u.name AS owner_name,
		        (SELECT m.role FROM project_members m WHERE m.project_id = p.id AND m.user_id = ?) AS my_role,
		        (SELECT ' . ApplicationSchema::OUTCOME_SQL . ' FROM project_applications a
		         WHERE a.project_id = p.id AND a.user_id = ?) AS my_application';
	}

	/**
	 * @param list<array<string,mixed>> $rows
	 * @return list<ProjectView>
	 */
	private function views(array $rows): array
	{
		$views = [];
		foreach ($rows as $row) {
			$views[] = $this->view($row);
		}
		return $views;
	}

	/** @param array<string,mixed> $row */
	private function view(array $row): ProjectView
	{
		$myRole = null;
		if ($row['my_role'] !== null) {
			$myRole = (string) $row['my_role'];
		}
		$myApplication = null;
		if ($row['my_application'] !== null) {
			$myApplication = (string) $row['my_application'];
		}

		return new ProjectView(
			ProjectSchema::project($row),
			(string) $row['owner_name'],
			$myRole,
			$myApplication,
		);
	}
}
