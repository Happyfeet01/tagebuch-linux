<?php

declare(strict_types=1);

namespace OCA\Tagebuch\Service;

use OCA\Tagebuch\AppInfo\Application;
use OCP\IConfig;

class ReminderTime {
	public function __construct(private IConfig $config) {
	}

	public function zone(string $uid): \DateTimeZone {
		foreach ([
			$this->config->getUserValue($uid, 'core', 'timezone', ''),
			$this->config->getUserValue($uid, Application::APP_ID, 'reminder_timezone', ''),
			$this->config->getSystemValueString('default_timezone', 'UTC'),
		] as $name) {
			if ($name === '') continue;
			try { return new \DateTimeZone($name); } catch (\Exception) { continue; }
		}
		return new \DateTimeZone('UTC');
	}

	public function dueDate(string $uid, \DateTimeImmutable $now): ?string {
		$local = $now->setTimezone($this->zone($uid));
		return (int)$local->format('H') >= 20 ? $local->format('Y-m-d') : null;
	}
}
