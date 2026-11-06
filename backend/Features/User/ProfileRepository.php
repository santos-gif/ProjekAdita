<?php

declare(strict_types=1);

namespace Features\User;

use Core\Db;
use Features\User\Model\Profile;
use PDOException;

/** Profiles: the profile columns of `users`, and the published projects a person is on. */
final class ProfileRepository
{
	private const EDITABLE = ['name', 'study_program', 'cohort_year', 'phone', 'bio', 'identity_number', 'avatar_url'];

	public function find(int $id): ?Profile
	{
		$sql = 'SELECT id, name, email, role, identity_number, study_program, cohort_year, phone, bio, avatar_url,
		               UNIX_TIMESTAMP(created_at) AS created_at
		        FROM users WHERE id = ?';
		$st = Db::pdo()->prepare($sql);
		$st->execute([$id]);

		$row = $st->fetch();
		if ($row === false) {
			return null;
		}

		return new Profile(
			(int) $row['id'],
			(string) $row['name'],
			(string) $row['email'],
			(string) $row['role'],
			$this->textOrNull($row['identity_number']),
			$this->textOrNull($row['study_program']),
			$this->numberOrNull($row['cohort_year']),
			$this->textOrNull($row['phone']),
			$this->textOrNull($row['bio']),
			$this->textOrNull($row['avatar_url']),
			(int) $row['created_at'],
			$this->projectsOf($id),
		);
	}

	/**
	 * Saves the changed columns. Only the columns in EDITABLE are ever written.
	 * @param array<string,string|int|null> $changes column => value
	 * @return bool false when the ID number belongs to someone else
	 */
	public function update(int $id, array $changes): bool
	{
		$set = [];
		$params = [];
		foreach ($changes as $column => $value) {
			if (!in_array($column, self::EDITABLE, true)) {
				continue;
			}
			$set[] = $column . ' = ?';
			$params[] = $value;
		}
		if ($set === []) {
			return true;
		}
		$params[] = $id;

		try {
			Db::pdo()->prepare('UPDATE users SET ' . implode(', ', $set) . ' WHERE id = ?')->execute($params);
		} catch (PDOException $e) {
			// 1062 = duplicate key: identity_number is unique.
			if (($e->errorInfo[1] ?? 0) === 1062) {
				return false;
			}
			throw $e;
		}
		return true;
	}

	/** @return list<array<string,mixed>> the published projects this person is on, newest first, with their role there */
	private function projectsOf(int $userId): array
	{
		$sql = "SELECT p.id, p.project_name, p.thumbnail_url, m.role, m.title
		        FROM project_members m JOIN projects p ON p.id = m.project_id
		        WHERE m.user_id = ? AND p.status = 'published'
		        ORDER BY p.published_at DESC, p.id DESC LIMIT 50";
		$st = Db::pdo()->prepare($sql);
		$st->execute([$userId]);

		$projects = [];
		foreach ($st->fetchAll() as $row) {
			$projects[] = [
				'id' => (int) $row['id'],
				'title' => (string) $row['project_name'],
				'thumbnail' => $this->textOrNull($row['thumbnail_url']),
				'role' => (string) $row['role'],
				'position' => (string) $row['title'],
			];
		}
		return $projects;
	}

	private function textOrNull(mixed $value): ?string
	{
		if ($value === null) {
			return null;
		}
		return (string) $value;
	}

	private function numberOrNull(mixed $value): ?int
	{
		if ($value === null) {
			return null;
		}
		return (int) $value;
	}
}
