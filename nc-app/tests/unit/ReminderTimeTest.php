<?php

declare(strict_types=1);

namespace OCA\Tagebuch\Tests\Unit;

use OCA\Tagebuch\Service\ReminderTime;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;

class ReminderTimeTest extends TestCase {
	public function testReminderUsesLocalEveningIncludingDaylightSavingAndLateCron(): void {
		$config = $this->createMock(IConfig::class);
		$config->method('getUserValue')->willReturnCallback(
			static fn ($uid, $app, $key, $default) => $app === 'core' ? 'Europe/Berlin' : $default
		);
		$schedule = new ReminderTime($config);
		self::assertNull($schedule->dueDate('user', new \DateTimeImmutable('2026-11-21T18:59:00Z')));
		self::assertSame('2026-11-21', $schedule->dueDate('user', new \DateTimeImmutable('2026-11-21T19:00:00Z')));
		self::assertSame('2026-06-21', $schedule->dueDate('user', new \DateTimeImmutable('2026-06-21T18:00:00Z')));
		self::assertSame('2026-11-21', $schedule->dueDate('user', new \DateTimeImmutable('2026-11-21T22:20:00Z')));
	}
}
