<?php

declare(strict_types=1);

namespace OCP\AppFramework\Db;

/**
 * Minimaler Stub fuer Standalone-Unit-Tests (ohne NC-Server).
 */
abstract class QBMapper {
	protected $db;
	private string $tableName;

	public function __construct($db, string $tableName, ?string $entityClass = null) {
		$this->db = $db;
		$this->tableName = $tableName;
	}

	public function getTableName(): string {
		return $this->tableName;
	}
}
