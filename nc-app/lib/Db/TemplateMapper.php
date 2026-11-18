<?php

declare(strict_types=1);

namespace OCA\Tagebuch\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;

/**
 * @template-extends QBMapper<Template>
 */
class TemplateMapper extends QBMapper {
	public function __construct(\OCP\IDBConnection $db) {
		parent::__construct($db, 'tagebuch_templates', Template::class);
	}

	/**
	 * @return Template[]
	 */
	public function findAllForUser(string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->orderBy('name', 'ASC');
		return $this->findEntities($qb);
	}

	public function findByName(string $userId, string $name): ?Template {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('name', $qb->createNamedParameter($name)));
		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException $e) {
			return null;
		}
	}

	public function saveTemplate(string $userId, string $name, string $body): Template {
		$existing = $this->findByName($userId, $name);
		if ($existing !== null) {
			$existing->setBody($body);
			$existing->setUpdatedAt((new \DateTimeImmutable('now'))->format('Y-m-d\TH:i:sP'));
			return $this->update($existing);
		}
		$template = new Template();
		$template->setUserId($userId);
		$template->setName($name);
		$template->setBody($body);
		$template->setUpdatedAt((new \DateTimeImmutable('now'))->format('Y-m-d\TH:i:sP'));
		return $this->insert($template);
	}
}
