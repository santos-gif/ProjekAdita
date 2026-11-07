<?php

declare(strict_types=1);

namespace Features\Tags\Repository;

use Core\Db;
use Features\Tags\Model\Tag;
use Features\Tags\Schema\TagSchema;

/** The `tags` table: the official list a project may choose from (seeded in schema.sql). */
final class TagRepository
{
	/** @return list<Tag> in seed order (study programme by study programme) */
	public function all(): array
	{
		$rows = Db::pdo()->query('SELECT ' . TagSchema::COLUMNS . ' FROM tags ORDER BY id')->fetchAll();

		$tags = [];
		foreach ($rows as $row) {
			$tags[] = TagSchema::tag($row);
		}
		return $tags;
	}

	/**
	 * @param list<string> $names tag names as the client wrote them (a project has at most 10, checked before this)
	 * @return array<string,int> lower-cased name AS WRITTEN => tag id; a name that is not official is simply missing
	 */
	public function idsByName(array $names): array
	{
		// One equality per name, so the database decides what "the same name" means: its collation ignores case and
		// accents ("ui/ux design" and "Machíne Learning" find their official tag). The map is keyed by what the client
		// wrote, so the caller's lookup can never miss a name that matched here.
		$st = Db::pdo()->prepare('SELECT id FROM tags WHERE name = ?');
		$ids = [];
		foreach ($names as $name) {
			$st->execute([$name]);
			$id = $st->fetchColumn();
			if ($id !== false) {
				$ids[mb_strtolower($name)] = (int) $id;
			}
		}
		return $ids;
	}
}
