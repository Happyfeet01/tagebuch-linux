<?php

declare(strict_types=1);

namespace OCP\AppFramework\Db;

/**
 * Minimaler Stub der OCP-Entity für Standalone-Unit-Tests (magische Getter/Setter via __call).
 */
class Entity implements \JsonSerializable {
	public $id;

	public function addType(string $name, string $type): void {}

	public function __call(string $methodName, array $args) {
		if (str_starts_with($methodName, 'set')) {
			$property = lcfirst(substr($methodName, 3));
			$this->$property = $args[0] ?? null;
			return $this;
		}
		if (str_starts_with($methodName, 'get')) {
			$property = lcfirst(substr($methodName, 3));
			return $this->$property ?? null;
		}
		throw new \BadMethodCallException('Unknown method ' . $methodName);
	}

	public function jsonSerialize(): array {
		return [];
	}
}
