<?php

declare(strict_types=1);

namespace OCP;

/**
 * Minimaler Stub für Standalone-Unit-Tests (ohne NC-Server).
 * In der echten Nextcloud wird das OCP-Interface aus dem Server geladen.
 */
interface IConfig {
	public function getSystemValue(string $key, $default = '');

	public function setSystemValue(string $key, $value): void;

	public function getAppValue(string $app, string $key, string $default = ''): string;

	public function setAppValue(string $app, string $key, string $value): void;

	public function deleteAppValue(string $app, string $key): void;

	public function getUserValue(string $userId, string $app, string $key, string $default = ''): string;

	public function setUserValue(string $userId, string $app, string $key, string $value): void;

	public function deleteUserValue(string $userId, string $app, string $key): void;
}
