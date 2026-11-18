<?php

declare(strict_types=1);

namespace OCA\Tagebuch\Tests\Unit;

use OCA\Tagebuch\Db\Entry;
use OCA\Tagebuch\Db\Template;
use PHPUnit\Framework\TestCase;

/**
 * Stellt sicher, dass appinfo/database.xml und die Entity-Klassen synchron bleiben
 * (fehlende Spalte = SQL-Fehler beim Speichern auf dem Server).
 */
class DatabaseSchemaTest extends TestCase {
	private const APP_ROOT = __DIR__ . '/../../';

	private static function columnsOf(string $tableSuffix): array {
		$xml = simplexml_load_file(self::APP_ROOT . 'appinfo/database.xml');
		self::assertNotFalse($xml, 'database.xml nicht lesbar');
		$columns = [];
		foreach ($xml->table as $table) {
			if (str_contains((string) $table->name, $tableSuffix)) {
				foreach ($table->column as $col) {
					$columns[] = (string) $col->name;
				}
			}
		}
		sort($columns);
		return $columns;
	}

	private static function entityColumns(string $class): array {
		$cols = [];
		foreach ((new \ReflectionClass($class))->getProperties() as $prop) {
			if ($prop->isProtected() && $prop->getDeclaringClass()->getName() === $class) {
				$cols[] = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $prop->getName()));
			}
		}
		sort($cols);
		return $cols;
	}

	public function testEntriesTableMatchesEntity(): void {
		$this->assertSame(self::entityColumns(Entry::class), self::columnsOf('tagebuch_entries'));
	}

	public function testTemplatesTableMatchesEntity(): void {
		$this->assertSame(self::entityColumns(Template::class), self::columnsOf('tagebuch_templates'));
	}
}
