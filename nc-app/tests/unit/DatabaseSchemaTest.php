<?php

declare(strict_types=1);

namespace OCA\Tagebuch\Tests\Unit;

use OCA\Tagebuch\Db\Entry;
use OCA\Tagebuch\Db\Template;
use OCA\Tagebuch\Migration\Version000207Date20261121000000;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;

class DatabaseSchemaTest extends TestCase {
	public function testMigrationCoversEntityFieldsAndKeepsExistingTables(): void {
		$tables = [];
		$schema = $this->createMock(ISchemaWrapper::class);
		$schema->method('hasTable')->willReturnCallback(static function ($name) use (&$tables) {
			return isset($tables[$name]);
		});
		$schema->method('getTable')->willReturnCallback(static function ($name) use (&$tables) {
			return $tables[$name];
		});
		$schema->expects(self::exactly(2))->method('createTable')->willReturnCallback(static function ($name) use (&$tables) {
			return $tables[$name] = new class {
				public array $columns = [];
				public function addColumn(string $name, string $type, array $options): void {
					TestCase::assertArrayNotHasKey($name, $this->columns);
					$this->columns[$name] = $type;
				}
				public function hasColumn(string $name): bool {
					return isset($this->columns[$name]);
				}
				public function setPrimaryKey(array $columns): void {}
				public function addUniqueIndex(array $columns, string $name): void {}
			};
		});
		$migration = new Version000207Date20261121000000();
		$output = $this->createStub(IOutput::class);
		$migration->changeSchema($output, static fn () => $schema, []);
		// Older installations may already have entries without the encryption flag.
		unset($tables['tagebuch_entries']->columns['encrypted']);
		$migration->changeSchema($output, static fn () => $schema, []);
		foreach (['tagebuch_entries' => Entry::class, 'tagebuch_templates' => Template::class] as $name => $class) {
			$expected = ['id'];
			foreach ((new \ReflectionClass($class))->getProperties() as $property) {
				if ($property->getDeclaringClass()->getName() === $class && $property->isProtected()) {
					$expected[] = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $property->getName()));
				}
			}
			self::assertEqualsCanonicalizing($expected, array_keys($tables[$name]->columns));
		}
	}
}
