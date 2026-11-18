<?php

declare(strict_types=1);

namespace OCA\Tagebuch\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;

/**
 * @template-extends QBMapper<Entry>
 */
class EntryMapper extends QBMapper {
	public function __construct(\OCP\IDBConnection $db) {
		parent::__construct($db, 'tagebuch_entries', Entry::class);
	}

	/**
	 * @return Entry[]
	 */
	public function findAll(string $userId, ?string $from = null, ?string $to = null): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
		if ($from !== null && $from !== '') {
			$qb->andWhere($qb->expr()->gte('entry_date', $qb->createNamedParameter($from)));
		}
		if ($to !== null && $to !== '') {
			$qb->andWhere($qb->expr()->lte('entry_date', $qb->createNamedParameter($to)));
		}
		$qb->orderBy('entry_date', 'DESC')
			->addOrderBy('entry_time', 'DESC');
		return $this->findEntities($qb);
	}

	/**
	 * @return Entry[]
	 */
	public function findForDay(string $userId, string $date): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('entry_date', $qb->createNamedParameter($date)))
			->orderBy('entry_time', 'ASC');
		return $this->findEntities($qb);
	}

	public function findOne(string $userId, string $date, ?string $time): ?Entry {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('entry_date', $qb->createNamedParameter($date)));
		if ($time === null || $time === '') {
			$qb->andWhere($qb->expr()->isNull('entry_time'));
		} else {
			$qb->andWhere($qb->expr()->eq('entry_time', $qb->createNamedParameter($time)));
		}
		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException $e) {
			return null;
		}
	}

	public function saveEntry(Entry $entry): Entry {
		if ($entry->getId() === null) {
			return $this->insert($entry);
		}
		return $this->update($entry);
	}

	public function deleteOne(string $userId, string $date, ?string $time): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('entry_date', $qb->createNamedParameter($date)));
		if ($time === null || $time === '') {
			$qb->andWhere($qb->expr()->isNull('entry_time'));
		} else {
			$qb->andWhere($qb->expr()->eq('entry_time', $qb->createNamedParameter($time)));
		}
		return $qb->executeStatement() > 0;
	}

	/**
	 * @return Entry[]
	 */
	public function search(string $userId, string $query, ?string $tag, ?string $since, ?string $until, ?int $minRating): array {
		$entries = $this->findAll($userId, $since, $until);
		$q = mb_strtolower(trim($query));
		return array_values(array_filter($entries, static function (Entry $e) use ($q, $tag, $minRating): bool {
			if ($tag !== null && $tag !== '') {
				$tags = json_decode($e->getTags(), true) ?: [];
				if (!in_array(mb_strtolower($tag), array_map('mb_strtolower', $tags), true)) {
					return false;
				}
			}
			if ($minRating !== null && ($e->getRating() === null || $e->getRating() < $minRating)) {
				return false;
			}
			if ($q !== '') {
				$location = $e->getLocation();
				$locationName = '';
				if (is_string($location) && $location !== '') {
					$decoded = json_decode($location, true);
					$locationName = is_array($decoded) ? (string) ($decoded['name'] ?? '') : '';
				}
				$haystack = mb_strtolower(
					$e->getHeading() . "\n" . $e->getBody() . "\n" . implode(' ', json_decode($e->getTags(), true) ?: [])
					. "\n" . $e->getTrackers() . "\n" . $locationName
				);
				if (!str_contains($haystack, $q)) {
					return false;
				}
			}
			return true;
		}));
	}
}
