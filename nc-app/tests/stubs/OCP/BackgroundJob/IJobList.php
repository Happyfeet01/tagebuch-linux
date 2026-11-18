<?php

declare(strict_types=1);

namespace OCP\BackgroundJob;

/**
 * Minimaler Stub fuer Standalone-Unit-Tests (ohne NC-Server).
 */
interface IJobList {
	public function add(string $class, $argument = null): void;

	public function has(string $class, $argument = null): bool;

	public function remove(string $class, $argument = null): void;
}
